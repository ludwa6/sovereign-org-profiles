<?php
/**
 * Plugin Name: VdL RSS Cloud (spec-compliant)
 * Description: Emits a spec-correct <cloud> element and implements the RSS Cloud
 *              REST handshake (register + challenge verification + async publish
 *              notification + subscription lifecycle) so a real external
 *              aggregator (e.g. FeedLand) can subscribe to this site's feed and
 *              be pushed within seconds of a publish.
 * Version:     1.0.0
 * Author:      sovereign-org-profiles
 * License:     MIT
 *
 * ---------------------------------------------------------------------------
 * WHAT THIS IS
 * ---------------------------------------------------------------------------
 * A single-file, portable WordPress must-use plugin implementing the RSS Cloud
 * REST interface: https://www.rssboard.org/rsscloud-interface
 *
 * It replaces the throwaway 2026-07-01 sandbox rig (custom ?vdl_rsscloud=register
 * path, no verification). Differences that matter for production:
 *   - standard <cloud protocol="http-post"> advertised in the feed;
 *   - standard register endpoint returning <notifyResult success=.. msg=..>;
 *   - challenge/verification before a subscription activates (anti-abuse);
 *   - non-blocking notification on publish (scheduled event), with timeouts and
 *     dead-callback pruning (no retry storms);
 *   - subscription expiry (~24h) + renewal + daily garbage collection;
 *   - per-feed subscriber cap, per-IP registration rate limit, SSRF guard.
 *
 * ---------------------------------------------------------------------------
 * INSTALL
 * ---------------------------------------------------------------------------
 *   1. Copy this file to  wp-content/mu-plugins/rsscloud.php  on the target site
 *      (create the mu-plugins/ directory if it does not exist). Must-use plugins
 *      auto-activate; there is nothing to enable in wp-admin.
 *   2. Confirm the site can make OUTBOUND HTTP requests (some shared hosts block
 *      this — it is the real blocker; verify with ICDsoft for VdL). Without
 *      outbound HTTP, verification and notification cannot fire.
 *
 * ---------------------------------------------------------------------------
 * VERIFY ON STAGING (before promoting to production)
 * ---------------------------------------------------------------------------
 *   1. <cloud> present:
 *        curl -sL https://STAGING/feed/ | grep -i cloud
 *      Expect a <cloud domain=".." port=".." path="/wp-json/rsscloud/v1/please-notify"
 *      registerProcedure="" protocol="http-post"/> line.
 *   2. End-to-end round-trip: stand up a tiny listener that (a) echoes the
 *      ?challenge= value on GET and (b) logs POST bodies, register it against the
 *      endpoint, publish a post, and confirm a POST with body `url=<feed>` arrives
 *      within seconds.
 *   3. TRUE interop proof (the evidence for Dave Winer): have FeedLand (account
 *      `waltvdl`) subscribe to the staging feed and confirm it receives the push.
 *
 * ---------------------------------------------------------------------------
 * OPEN DIALECT QUESTION  (read before trusting verification against FeedLand)
 * ---------------------------------------------------------------------------
 * The rssboard REST spec verifies a subscriber by calling its callback with a
 * `challenge` and requiring the challenge echoed back. Dave Winer's own rssCloud
 * (which FeedLand speaks) historically did NOT use a challenge echo. Which dialect
 * FeedLand actually expects is the still-open question from the Walt<->Dave thread.
 * This plugin defaults to STRICT challenge verification; if FeedLand does not echo
 * the challenge, its subscription will be rejected. Resolve by testing against
 * FeedLand, then adjust via the `rsscloud_verify_subscriber` filter (see below)
 * rather than deleting the check — leaving the endpoint open is a bombing vector.
 *
 * ---------------------------------------------------------------------------
 * FILTERS
 * ---------------------------------------------------------------------------
 *   rsscloud_verify_subscriber  (bool $verified, string $callback, string $challenge, string $feed)
 *       Override the verification result once FeedLand's dialect is confirmed.
 *   rsscloud_allow_private_callbacks  (bool $allow)
 *       Default false. Set true only on a trusted LAN sandbox to allow callbacks
 *       that resolve to private/loopback addresses.
 *
 * @package vdl-rsscloud
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

const VDL_RSSCLOUD_OPTION       = 'vdl_rsscloud_subscriptions'; // stored subscriptions
const VDL_RSSCLOUD_TTL          = 86400;   // subscription lifetime, seconds (~24h per spec)
const VDL_RSSCLOUD_MAX_PER_FEED = 1000;    // subscriber cap per feed
const VDL_RSSCLOUD_RL_MAX       = 30;      // max registrations per IP per window
const VDL_RSSCLOUD_RL_WINDOW    = 3600;    // rate-limit window, seconds
const VDL_RSSCLOUD_HTTP_TIMEOUT = 4;       // outbound request timeout, seconds
const VDL_RSSCLOUD_MAX_FAILS    = 3;       // drop a callback after this many failed notifies
const VDL_RSSCLOUD_NOTIFY_HOOK  = 'vdl_rsscloud_notify';
const VDL_RSSCLOUD_GC_HOOK      = 'vdl_rsscloud_gc';

/* -------------------------------------------------------------------------
 * 1. Advertise <cloud> in the RSS2 feed head.
 * ---------------------------------------------------------------------- */

add_action( 'rss2_head', 'vdl_rsscloud_emit_cloud_element' );

function vdl_rsscloud_emit_cloud_element() {
	$home = wp_parse_url( home_url() );
	if ( empty( $home['host'] ) ) {
		return;
	}
	$host   = $home['host'];
	$scheme = isset( $home['scheme'] ) ? $home['scheme'] : 'http';
	// Explicit port if the site URL carries one, else the scheme default.
	$port = isset( $home['port'] ) ? (int) $home['port'] : ( 'https' === $scheme ? 443 : 80 );
	$path = '/wp-json/rsscloud/v1/please-notify';

	printf(
		'<cloud domain="%s" port="%d" path="%s" registerProcedure="" protocol="http-post"/>' . "\n",
		esc_attr( $host ),
		$port,
		esc_attr( $path )
	);
}

/* -------------------------------------------------------------------------
 * 2. Register endpoint (pleaseNotify) — RSS Cloud REST handshake.
 * ---------------------------------------------------------------------- */

add_action( 'rest_api_init', function () {
	register_rest_route(
		'rsscloud/v1',
		'/please-notify',
		array(
			'methods'             => 'POST',
			'callback'            => 'vdl_rsscloud_handle_register',
			'permission_callback' => '__return_true', // public endpoint by design; abuse is gated by verification + caps.
		)
	);
} );

/**
 * Handle a pleaseNotify registration.
 *
 * Standard params: notifyProcedure, port, path, protocol, domain (optional →
 * REMOTE_ADDR), url1..urlN. Responds with <notifyResult success=.. msg=..>.
 */
function vdl_rsscloud_handle_register( WP_REST_Request $request ) {
	$ip = vdl_rsscloud_client_ip();

	if ( vdl_rsscloud_rate_limited( $ip ) ) {
		return vdl_rsscloud_notify_result( false, 'Rate limit exceeded; try again later.' );
	}

	$protocol = strtolower( trim( (string) $request->get_param( 'protocol' ) ) );
	if ( 'http-post' !== $protocol ) {
		return vdl_rsscloud_notify_result( false, 'Only the http-post (REST) protocol is supported by this endpoint.' );
	}

	$port = (int) $request->get_param( 'port' );
	$path = (string) $request->get_param( 'path' );
	if ( $port < 1 || $port > 65535 || '' === $path || '/' !== substr( $path, 0, 1 ) ) {
		return vdl_rsscloud_notify_result( false, 'Missing or invalid port/path.' );
	}

	// domain is optional; fall back to the caller's source IP per spec.
	$domain = trim( (string) $request->get_param( 'domain' ) );
	if ( '' === $domain ) {
		$domain = $ip;
	}
	$domain = preg_replace( '/[^A-Za-z0-9.\-]/', '', $domain );
	if ( '' === $domain ) {
		return vdl_rsscloud_notify_result( false, 'Invalid domain.' );
	}

	// Collect the feed URLs the subscriber wants (url1, url2, ...).
	$feeds = array();
	foreach ( $request->get_params() as $key => $value ) {
		if ( preg_match( '/^url\d+$/', (string) $key ) ) {
			$feeds[] = esc_url_raw( (string) $value );
		}
	}
	$feeds = array_values( array_filter( array_unique( $feeds ) ) );
	if ( empty( $feeds ) ) {
		return vdl_rsscloud_notify_result( false, 'No feed URL (url1) supplied.' );
	}

	// http-post callbacks are HTTP; use HTTPS only when the caller nominates 443.
	$scheme   = ( 443 === $port ) ? 'https' : 'http';
	$callback = sprintf( '%s://%s:%d%s', $scheme, $domain, $port, $path );

	if ( ! vdl_rsscloud_callback_is_safe( $callback ) ) {
		return vdl_rsscloud_notify_result( false, 'Callback host is not permitted.' );
	}

	$store    = vdl_rsscloud_get_store();
	$verified = 0;
	foreach ( $feeds as $feed ) {
		// Only accept subscriptions to feeds this site actually serves.
		if ( ! vdl_rsscloud_is_own_feed( $feed ) ) {
			continue;
		}
		if ( vdl_rsscloud_feed_at_capacity( $store, $feed ) ) {
			return vdl_rsscloud_notify_result( false, 'Subscriber capacity reached for this feed.' );
		}
		// On success this returns the redirect-resolved callback (e.g. a
		// port-80 registration that 301s to https is stored as its https
		// form), or false on failure.
		$resolved = vdl_rsscloud_verify_subscriber( $callback, $feed );
		if ( false === $resolved ) {
			return vdl_rsscloud_notify_result( false, 'Subscriber verification failed (challenge not echoed).' );
		}
		$key           = md5( $resolved . '|' . $feed );
		$now           = time();
		$store[ $key ] = array(
			'callback' => $resolved,
			'feed'     => $feed,
			'created'  => isset( $store[ $key ]['created'] ) ? $store[ $key ]['created'] : $now,
			'expires'  => $now + VDL_RSSCLOUD_TTL, // re-registration renews.
			'fails'    => 0,
		);
		++$verified;
	}

	if ( 0 === $verified ) {
		return vdl_rsscloud_notify_result( false, 'None of the supplied url1..n match a feed served by this site.' );
	}

	vdl_rsscloud_put_store( $store );
	vdl_rsscloud_bump_rate( $ip );

	return vdl_rsscloud_notify_result( true, 'Subscriber registered; you will be notified on publish.' );
}

/**
 * Verify the caller controls the callback: GET it with a random challenge and a
 * `url` param, following up to a few redirects, expecting HTTP 200 echoing the
 * challenge. A subscriber may advertise a port-80 (http) callback yet serve it
 * over https behind a 301 (this is how FeedLand registers); following the
 * redirect reaches that echo, and the redirect-resolved URL is what we store so
 * later notify POSTs go straight to https (a 301 on POST drops the body).
 *
 * Returns the effective callback string (scheme://host:port/path) on success —
 * the redirect-resolved form when a redirect was followed, otherwise the
 * originally-registered callback — or false on failure. Overridable once
 * FeedLand's dialect is confirmed (see the OPEN DIALECT QUESTION note above).
 *
 * @return string|false
 */
function vdl_rsscloud_verify_subscriber( $callback, $feed ) {
	$challenge = wp_generate_password( 24, false );
	$probe     = add_query_arg(
		array(
			'challenge' => $challenge,
			'url'       => rawurlencode( $feed ),
		),
		$callback
	);

	$response = wp_remote_get(
		$probe,
		array(
			'timeout'     => VDL_RSSCLOUD_HTTP_TIMEOUT,
			'redirection' => 3,
		)
	);

	$verified = false;
	$resolved = $callback;
	if ( ! is_wp_error( $response )
		&& 200 === (int) wp_remote_retrieve_response_code( $response )
		&& hash_equals( $challenge, trim( wp_remote_retrieve_body( $response ) ) )
	) {
		// Normalise the URL WP actually landed on (post-redirect) back to a
		// bare scheme://host:port/path callback, dropping the challenge query.
		$final = vdl_rsscloud_rebuild_callback( vdl_rsscloud_final_url( $response, $probe ) );
		// Re-run the SSRF guard on the final host: an http→https redirect can
		// legitimately change the host, and we will POST to it on publish.
		if ( '' !== $final && vdl_rsscloud_callback_is_safe( $final ) ) {
			$verified = true;
			$resolved = $final;
		}
	}

	/**
	 * Filter the verification result. Return true to accept a subscriber whose
	 * dialect does not echo the challenge (e.g. classic Winer rssCloud), once
	 * you have confirmed reachability another way.
	 */
	$verified = (bool) apply_filters( 'rsscloud_verify_subscriber', $verified, $callback, $challenge, $feed );

	return $verified ? $resolved : false;
}

/**
 * Pull the final URL WP followed (after any redirects) out of an HTTP response,
 * falling back to $fallback when the underlying response object is unavailable.
 */
function vdl_rsscloud_final_url( $response, $fallback ) {
	if ( isset( $response['http_response'] ) && is_object( $response['http_response'] )
		&& method_exists( $response['http_response'], 'get_response_object' )
	) {
		$obj = $response['http_response']->get_response_object();
		if ( $obj && ! empty( $obj->url ) ) {
			return (string) $obj->url;
		}
	}
	return $fallback;
}

/** Normalise a URL to a bare scheme://host:port/path callback (no query/fragment). */
function vdl_rsscloud_rebuild_callback( $url ) {
	$p = wp_parse_url( (string) $url );
	if ( empty( $p['host'] ) || empty( $p['scheme'] ) ) {
		return '';
	}
	$scheme = $p['scheme'];
	$port   = isset( $p['port'] ) ? (int) $p['port'] : ( 'https' === $scheme ? 443 : 80 );
	$path   = isset( $p['path'] ) && '' !== $p['path'] ? $p['path'] : '/';
	return sprintf( '%s://%s:%d%s', $scheme, $p['host'], $port, $path );
}

/* -------------------------------------------------------------------------
 * 3. Notify subscribers on publish — async, non-blocking.
 * ---------------------------------------------------------------------- */

add_action( 'transition_post_status', 'vdl_rsscloud_on_transition', 10, 3 );

function vdl_rsscloud_on_transition( $new_status, $old_status, $post ) {
	// Fire only on a genuine transition INTO publish, for public post types.
	if ( 'publish' !== $new_status || 'publish' === $old_status ) {
		return;
	}
	$type = get_post_type_object( $post->post_type );
	if ( ! $type || empty( $type->public ) ) {
		return;
	}
	$feed = get_feed_link();
	// Schedule immediately but out-of-band so the publish request is not slowed.
	if ( ! wp_next_scheduled( VDL_RSSCLOUD_NOTIFY_HOOK, array( $feed ) ) ) {
		wp_schedule_single_event( time(), VDL_RSSCLOUD_NOTIFY_HOOK, array( $feed ) );
	}
}

add_action( VDL_RSSCLOUD_NOTIFY_HOOK, 'vdl_rsscloud_dispatch_notifications', 10, 1 );

/**
 * POST `url=<feed>` to every verified, unexpired subscriber of $feed. Prune
 * expired subscriptions and drop callbacks that fail repeatedly.
 */
function vdl_rsscloud_dispatch_notifications( $feed ) {
	$store   = vdl_rsscloud_prune_expired( vdl_rsscloud_get_store() );
	$changed = false;

	foreach ( $store as $key => $sub ) {
		// Trailing-slash-insensitive match: WP's get_feed_link() yields the
		// pretty permalink WITH a slash (.../feed/), while an aggregator such as
		// FeedLand normalises and stores the subscription WITHOUT one (.../feed).
		if ( rtrim( $sub['feed'], '/' ) !== rtrim( $feed, '/' ) ) {
			continue;
		}
		$response = wp_remote_post(
			$sub['callback'],
			array(
				'timeout'     => VDL_RSSCLOUD_HTTP_TIMEOUT,
				'redirection' => 0,
				'blocking'    => true, // we are in cron; capture failures to prune dead callbacks.
				// Notify with the subscriber's OWN registered feed URL, not the
				// site-canonical $feed, so the notified string is byte-identical
				// to what the subscriber stored and matches against.
				'body'        => array( 'url' => $sub['feed'] ),
			)
		);

		$code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
		if ( $code >= 200 && $code < 300 ) {
			if ( 0 !== $store[ $key ]['fails'] ) {
				$store[ $key ]['fails'] = 0;
				$changed                = true;
			}
			continue;
		}

		// Failure: count it, and drop the callback once it is clearly dead.
		$store[ $key ]['fails'] = (int) $store[ $key ]['fails'] + 1;
		$changed                = true;
		if ( $store[ $key ]['fails'] >= VDL_RSSCLOUD_MAX_FAILS ) {
			unset( $store[ $key ] );
		}
	}

	if ( $changed ) {
		vdl_rsscloud_put_store( $store );
	}
}

/* -------------------------------------------------------------------------
 * 4. Subscription lifecycle — daily garbage collection.
 * ---------------------------------------------------------------------- */

add_action( 'init', function () {
	if ( ! wp_next_scheduled( VDL_RSSCLOUD_GC_HOOK ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', VDL_RSSCLOUD_GC_HOOK );
	}
} );

add_action( VDL_RSSCLOUD_GC_HOOK, function () {
	$store = vdl_rsscloud_get_store();
	$kept  = vdl_rsscloud_prune_expired( $store );
	if ( count( $kept ) !== count( $store ) ) {
		vdl_rsscloud_put_store( $kept );
	}
} );

/* -------------------------------------------------------------------------
 * Helpers.
 * ---------------------------------------------------------------------- */

function vdl_rsscloud_get_store() {
	$store = get_option( VDL_RSSCLOUD_OPTION, array() );
	return is_array( $store ) ? $store : array();
}

function vdl_rsscloud_put_store( array $store ) {
	update_option( VDL_RSSCLOUD_OPTION, $store, false );
}

function vdl_rsscloud_prune_expired( array $store ) {
	$now = time();
	foreach ( $store as $key => $sub ) {
		if ( empty( $sub['expires'] ) || $sub['expires'] < $now ) {
			unset( $store[ $key ] );
		}
	}
	return $store;
}

function vdl_rsscloud_feed_at_capacity( array $store, $feed ) {
	$count = 0;
	foreach ( $store as $sub ) {
		if ( $sub['feed'] === $feed ) {
			++$count;
		}
	}
	return $count >= VDL_RSSCLOUD_MAX_PER_FEED;
}

/** Only accept subscriptions to feeds served by this site (host must match). */
function vdl_rsscloud_is_own_feed( $feed ) {
	$f = wp_parse_url( $feed );
	$h = wp_parse_url( home_url() );
	if ( empty( $f['host'] ) || empty( $h['host'] ) ) {
		return false;
	}
	return strtolower( $f['host'] ) === strtolower( $h['host'] );
}

/** SSRF guard: reject callbacks resolving to private/loopback ranges by default. */
function vdl_rsscloud_callback_is_safe( $callback ) {
	$parts = wp_parse_url( $callback );
	if ( empty( $parts['host'] ) || empty( $parts['scheme'] ) || ! in_array( $parts['scheme'], array( 'http', 'https' ), true ) ) {
		return false;
	}

	if ( apply_filters( 'rsscloud_allow_private_callbacks', false ) ) {
		return true;
	}

	$host = $parts['host'];
	$ip   = filter_var( $host, FILTER_VALIDATE_IP ) ? $host : gethostbyname( $host );
	if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
		return false; // unresolvable → reject.
	}

	// Reject anything not in the public range (blocks loopback/private/reserved).
	return (bool) filter_var(
		$ip,
		FILTER_VALIDATE_IP,
		FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
	);
}

function vdl_rsscloud_client_ip() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
	return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
}

function vdl_rsscloud_rate_limited( $ip ) {
	$count = (int) get_transient( 'vdl_rsscloud_rl_' . md5( $ip ) );
	return $count >= VDL_RSSCLOUD_RL_MAX;
}

function vdl_rsscloud_bump_rate( $ip ) {
	$key   = 'vdl_rsscloud_rl_' . md5( $ip );
	$count = (int) get_transient( $key );
	set_transient( $key, $count + 1, VDL_RSSCLOUD_RL_WINDOW );
}

/**
 * Build and send a spec <notifyResult> as text/xml. Short-circuits the REST
 * serializer so the aggregator receives raw XML, not a JSON envelope.
 */
function vdl_rsscloud_notify_result( $success, $msg ) {
	$xml = sprintf(
		'<?xml version="1.0"?>' . "\n" . '<notifyResult success="%s" msg="%s"/>',
		$success ? 'true' : 'false',
		esc_attr( $msg )
	);
	if ( ! headers_sent() ) {
		header( 'Content-Type: text/xml; charset=UTF-8' );
		status_header( 200 );
	}
	echo $xml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static XML, message escaped above.
	exit;
}
