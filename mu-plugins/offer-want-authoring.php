<?php
/**
 * Plugin Name: Sovereign Org Profiles — Offer/Want Authoring
 * Description: PROTOTYPE of the dynamic Offer-or-Want datatype for sovereign-org-profiles.
 *   Registers an `offer_want` CPT + the offers_wants schema fields, and emits the post on
 *   TWO channels from one authored record: (A) a Murmurations-valid JSON artifact at a stable
 *   URL, and (B) a DEDICATED RSS feed carrying standard elements for any reader plus a custom
 *   `sop:` namespace for aggregators (graceful degradation). Offers/wants ride their own feed,
 *   not the main Journal feed. LAN sandbox proves shape/validity; external index+push legs
 *   need the public staging clone. Working namespace name — canonical naming deferred.
 *   Spec: "ACF Offer-or-Want Authoring Spec — WordPress Partner Sites".
 * Version: 0.1.0-proto
 * Author:  sovereign-org-profiles
 * License: MIT
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

const SOP_OW_CPT   = 'offer_want';
const SOP_OW_NS    = 'sop';
// Working namespace URI — placeholder; canonical naming/versioning deferred until the
// end-to-end dataflow is proven (see spec §5 / open-questions §2).
const SOP_OW_NSURI = 'https://sovereign-org-profiles.net/ns/sop/0.1';
const SOP_OW_SCHEMA = 'offers_wants_schema-v0.1.0';

/* ---- 1. Custom post type + its own feed --------------------------------- */
add_action( 'init', function () {
	register_post_type( SOP_OW_CPT, array(
		'labels' => array(
			'name'          => 'Offers & Wants',
			'singular_name' => 'Offer or Want',
			'menu_name'     => 'Offers & Wants',
			'add_new_item'  => 'Add New Offer or Want',
			'edit_item'     => 'Edit Offer or Want',
		),
		'description'  => 'A specific offer or want published into the regenerative-economy matching layer.',
		'public'       => true,
		'has_archive'  => true,               // gives the dedicated /offer-want/feed/
		'show_in_rest' => true,               // Gutenberg + REST/MCP authoring
		'menu_icon'    => 'dashicons-randomize',
		'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail', 'author' ),
		'taxonomies'   => array( 'post_tag' ), // schema `tags`
		'rewrite'      => array( 'slug' => 'offer-want', 'with_front' => false ),
	) );

	// offers_wants_schema fields, as REST-exposed post meta (single meta, one per field).
	$meta = array(
		'ow_exchange_type'     => 'string',   // offer | want          (required)
		'ow_transaction_type'  => 'array',    // borrow-lend, ...       (required)
		'ow_description'       => 'string',   // 1-3 sentences         (required)
		'ow_geographic_scope'  => 'string',   // local|regional|...    (required)
		'ow_item_type'         => 'string',   // good | service
		'ow_image'             => 'string',
		'ow_details_url'       => 'string',
		'ow_lat'               => 'number',
		'ow_lon'               => 'number',
		'ow_expires_at'        => 'integer',  // unix ts
		'ow_contact_email'     => 'string',
		'ow_contact_form'      => 'string',
	);
	foreach ( $meta as $key => $type ) {
		$args = array(
			'type'          => $type,
			'single'        => true,
			'show_in_rest'  => true,
			'auth_callback' => function () { return current_user_can( 'edit_posts' ); },
		);
		if ( 'array' === $type ) {
			$args['show_in_rest'] = array(
				'schema' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			);
		}
		register_post_meta( SOP_OW_CPT, $key, $args );
	}
}, 10 );

// Flush rewrite rules once so the pretty archive/feed URLs resolve (idempotent guard).
add_action( 'init', function () {
	if ( get_option( 'sop_ow_flushed' ) !== '0.1.0-proto' ) {
		flush_rewrite_rules( false );
		update_option( 'sop_ow_flushed', '0.1.0-proto' );
	}
}, 20 );

/* ---- shared: build the offers_wants profile array from a post ----------- */
function sop_ow_build_profile( $post_id ) {
	$m = function ( $k ) use ( $post_id ) { return get_post_meta( $post_id, $k, true ); };

	$profile = array(
		'linked_schemas'   => array( SOP_OW_SCHEMA ),
		'exchange_type'    => (string) $m( 'ow_exchange_type' ),
		'transaction_type' => array_values( array_filter( (array) $m( 'ow_transaction_type' ) ) ),
		'tags'             => wp_get_post_tags( $post_id, array( 'fields' => 'names' ) ),
		'title'            => get_the_title( $post_id ),
		'description'      => (string) ( $m( 'ow_description' ) ?: get_the_excerpt( $post_id ) ),
		'geographic_scope' => (string) $m( 'ow_geographic_scope' ),
	);

	$contact = array();
	if ( $m( 'ow_contact_email' ) ) { $contact['email'] = $m( 'ow_contact_email' ); }
	if ( $m( 'ow_contact_form' ) )  { $contact['contact_form'] = $m( 'ow_contact_form' ); }
	if ( $contact ) { $profile['contact_details'] = $contact; }

	if ( $m( 'ow_item_type' ) )   { $profile['item_type'] = (string) $m( 'ow_item_type' ); }
	if ( $m( 'ow_image' ) ) {
		// ACF image field stores an attachment ID; a plain text field stores a URL. Accept both.
		$img = $m( 'ow_image' );
		$url = is_numeric( $img ) ? wp_get_attachment_url( (int) $img ) : (string) $img;
		if ( $url ) { $profile['image'] = $url; }
	}
	if ( $m( 'ow_details_url' ) ) { $profile['details_url'] = (string) $m( 'ow_details_url' ); }
	if ( '' !== $m( 'ow_lat' ) && '' !== $m( 'ow_lon' ) ) {
		$profile['geolocation'] = array( 'lat' => (float) $m( 'ow_lat' ), 'lon' => (float) $m( 'ow_lon' ) );
	}
	// expires_at → unix ts. Accept a unix int (seed/REST) or ACF date-picker 'Ymd'.
	$exp = $m( 'ow_expires_at' );
	if ( '' !== $exp && null !== $exp ) {
		if ( is_numeric( $exp ) && strlen( (string) (int) $exp ) >= 10 ) {
			$ts = (int) $exp;
		} elseif ( preg_match( '/^\d{8}$/', (string) $exp ) ) {
			$d  = DateTime::createFromFormat( 'Ymd', (string) $exp );
			$ts = $d ? $d->getTimestamp() : 0;
		} else {
			$ts = strtotime( (string) $exp ) ?: 0;
		}
		if ( $ts ) { $profile['expires_at'] = $ts; }
	}

	return $profile;
}

/* ---- 2. Channel A: JSON artifact at a stable URL ------------------------ */
add_filter( 'query_vars', function ( $vars ) { $vars[] = 'murmurations'; return $vars; } );

add_action( 'template_redirect', function () {
	if ( ! get_query_var( 'murmurations' ) || ! is_singular( SOP_OW_CPT ) ) { return; }
	$profile = sop_ow_build_profile( get_queried_object_id() );
	header( 'Content-Type: application/json; charset=utf-8' );
	echo wp_json_encode( $profile, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
	exit;
} );

/* ---- 3. Channel B: dedicated feed — namespace + custom elements ---------- */
// Is the current feed request the offers/wants feed?
function sop_ow_is_ow_feed() {
	return is_feed() && ( is_post_type_archive( SOP_OW_CPT ) || SOP_OW_CPT === get_query_var( 'post_type' ) );
}

// Declare the custom namespace on <rss> — only on the offers/wants feed.
add_action( 'rss2_ns', function () {
	if ( sop_ow_is_ow_feed() ) {
		echo 'xmlns:' . SOP_OW_NS . '="' . esc_attr( SOP_OW_NSURI ) . '"' . "\n";
	}
} );

// Human-useful <description> for standard readers: prefix with the exchange in plain words.
add_filter( 'the_excerpt_rss', function ( $excerpt ) {
	if ( ! sop_ow_is_ow_feed() ) { return $excerpt; }
	$id   = get_the_ID();
	$ex   = get_post_meta( $id, 'ow_exchange_type', true );
	$desc = get_post_meta( $id, 'ow_description', true );
	$verb = ( 'want' === $ex ) ? 'Want' : 'Offer';
	return trim( $verb . ' — ' . ( $desc ?: wp_strip_all_tags( $excerpt ) ) );
} );

// Machine-matchable structure in the custom namespace — ignored by generic readers.
add_action( 'rss2_item', function () {
	if ( ! sop_ow_is_ow_feed() ) { return; }
	$id = get_the_ID();
	$p  = sop_ow_build_profile( $id );
	$ns = SOP_OW_NS . ':';
	$emit = function ( $tag, $val ) use ( $ns ) {
		echo "\t\t<{$ns}{$tag}>" . esc_html( $val ) . "</{$ns}{$tag}>\n";
	};
	foreach ( $p['linked_schemas'] as $s ) { $emit( 'linked_schemas', $s ); }
	$emit( 'exchange_type', $p['exchange_type'] );
	foreach ( $p['transaction_type'] as $t ) { $emit( 'transaction_type', $t ); }
	$emit( 'geographic_scope', $p['geographic_scope'] );
	if ( isset( $p['item_type'] ) )   { $emit( 'item_type', $p['item_type'] ); }
	if ( isset( $p['details_url'] ) )  { $emit( 'details_url', $p['details_url'] ); }
	if ( isset( $p['expires_at'] ) )   { $emit( 'expires_at', $p['expires_at'] ); }
	if ( isset( $p['geolocation'] ) ) {
		echo "\t\t<{$ns}geolocation lat=\"" . esc_attr( $p['geolocation']['lat'] )
			. "\" lon=\"" . esc_attr( $p['geolocation']['lon'] ) . "\" />\n";
	}
	if ( isset( $p['contact_details']['email'] ) )        { $emit( 'contact_email', $p['contact_details']['email'] ); }
	if ( isset( $p['contact_details']['contact_form'] ) ) { $emit( 'contact_form', $p['contact_details']['contact_form'] ); }
} );

/* ---- 4. ACF authoring form (field names == the ow_* meta keys) ----------- */
// Registered in PHP so it's portable/Git-ready (spec §7); no click-config to reproduce.
add_action( 'acf/init', function () {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) { return; }
	acf_add_local_field_group( array(
		'key'      => 'group_sop_offer_want',
		'title'    => 'Offer or Want details',
		'location' => array( array( array(
			'param' => 'post_type', 'operator' => '==', 'value' => SOP_OW_CPT,
		) ) ),
		'menu_order'  => 0,
		'position'    => 'normal',
		'style'       => 'default',
		'active'      => true,
		'description' => 'Maps to ' . SOP_OW_SCHEMA . '. Field names = the ow_* meta keys the JSON/feed mappers read.',
		'fields'      => array(
			array(
				'key' => 'field_ow_exchange_type', 'label' => 'Offer or Want?', 'name' => 'ow_exchange_type',
				'type' => 'radio', 'required' => 1, 'layout' => 'horizontal',
				'choices' => array( 'offer' => 'Offer', 'want' => 'Want' ),
			),
			array(
				'key' => 'field_ow_item_type', 'label' => 'Is this a good or a service?', 'name' => 'ow_item_type',
				'type' => 'radio', 'layout' => 'horizontal', 'allow_null' => 1,
				'instructions' => 'A good is a physical thing; a service is work, a skill, or someone’s time (e.g. carpentry).',
				'choices' => array( 'good' => 'Good (a thing)', 'service' => 'Service (work / skill / time)' ),
			),
			array(
				'key' => 'field_ow_transaction_type', 'label' => 'How would it be exchanged?', 'name' => 'ow_transaction_type',
				'type' => 'checkbox', 'required' => 1,
				'instructions' => 'Tick all that apply — works for goods and services. For work/skills: “Gift / swap” = volunteer, pro-bono, or barter; “Buy / sell” = paid work (e.g. hiring a carpenter). (Barter/skill-swap maps to “Gift / swap” for now — a dedicated value is a tracked design question.)',
				'choices' => array(
					'receive-donate' => 'Gift / swap — no money changes hands (incl. barter & volunteering)',
					'borrow-lend'    => 'Borrow / lend — returned after use',
					'rent-lease'     => 'Rent / hire — for a fee, time-based',
					'buy-sell'       => 'Buy / sell — for payment (incl. paid services)',
				),
			),
			array(
				'key' => 'field_ow_description', 'label' => 'Short description', 'name' => 'ow_description',
				'type' => 'textarea', 'required' => 1, 'rows' => 3,
				'instructions' => '1–3 sentences. This is the machine-readable summary (separate from the full post body).',
			),
			array(
				'key' => 'field_ow_geographic_scope', 'label' => 'Geographic scope', 'name' => 'ow_geographic_scope',
				'type' => 'select', 'required' => 1, 'ui' => 1,
				'choices' => array(
					'local' => 'Local', 'regional' => 'Regional', 'national' => 'National', 'international' => 'International',
				),
			),
			array(
				'key' => 'field_ow_details_url', 'label' => 'More-info page (optional)', 'name' => 'ow_details_url',
				'type' => 'url',
				'instructions' => 'Link to a page on your own site with fuller detail about this offer/want, if you have one. Leave blank otherwise.',
			),
			array(
				'key' => 'field_ow_image', 'label' => 'Image', 'name' => 'ow_image',
				'type' => 'image', 'return_format' => 'id', 'library' => 'all', 'preview_size' => 'medium',
			),
			array(
				'key' => 'field_ow_geo', 'label' => 'Location (optional)', 'name' => 'ow_geo',
				'type' => 'open_street_map', 'return_format' => 'raw',
				'instructions' => 'Search, or click the map to drop a pin — only if a precise point matters (a specific site or pickup spot). The coarse area is already covered by Scope above; leave blank otherwise.',
				'max_markers' => 1,
				'center_lat'  => 37.1361, 'center_lng' => -8.6421, 'zoom' => 9, // near VdL / Algarve
			),
			array(
				'key' => 'field_ow_expires_at', 'label' => 'Expires on', 'name' => 'ow_expires_at',
				'type' => 'date_picker', 'display_format' => 'd/m/Y', 'return_format' => 'Ymd', 'first_day' => 1,
				'instructions' => 'Optional. After this date the offer/want is stale.',
			),
			array(
				'key' => 'field_ow_contact_msg', 'label' => 'How can people reach you?', 'name' => '',
				'type' => 'message',
				'message' => 'Give **at least one** — an email address, or a link to a contact form on your site (an alternative if you’d rather not publish an email).',
			),
			array(
				'key' => 'field_ow_contact_email', 'label' => 'Contact email', 'name' => 'ow_contact_email',
				'type' => 'email', 'wrapper' => array( 'width' => '50' ),
			),
			array(
				'key' => 'field_ow_contact_form', 'label' => 'Contact-form page (URL)', 'name' => 'ow_contact_form',
				'type' => 'url', 'wrapper' => array( 'width' => '50' ),
				'instructions' => 'A page on your site with a contact form — instead of, or as well as, an email.',
			),
		),
	) );
} );

// Split the OSM map picker's dropped pin into the ow_lat/ow_lon meta the mappers read.
// Only records a point when a marker is actually placed (geolocation stays optional).
add_action( 'acf/save_post', function ( $post_id ) {
	if ( get_post_type( $post_id ) !== SOP_OW_CPT ) { return; }
	$geo = get_field( 'ow_geo', $post_id );
	$lat = $lng = null;
	if ( is_array( $geo ) && ! empty( $geo['markers'] ) && is_array( $geo['markers'] ) ) {
		$mk = reset( $geo['markers'] );
		if ( isset( $mk['lat'], $mk['lng'] ) && '' !== $mk['lat'] && '' !== $mk['lng'] ) {
			$lat = $mk['lat'];
			$lng = $mk['lng'];
		}
	}
	if ( null !== $lat && null !== $lng ) {
		update_post_meta( $post_id, 'ow_lat', (float) $lat );
		update_post_meta( $post_id, 'ow_lon', (float) $lng );
	} else {
		delete_post_meta( $post_id, 'ow_lat' );
		delete_post_meta( $post_id, 'ow_lon' );
	}
}, 20 );

// Enforce the schema's rule: an offer/want needs at least one contact method.
add_action( 'acf/validate_save_post', function () {
	$acf = isset( $_POST['acf'] ) ? (array) $_POST['acf'] : array();
	// Only act on our form (both contact keys present in the submission).
	if ( ! isset( $acf['field_ow_contact_email'] ) && ! isset( $acf['field_ow_contact_form'] ) ) { return; }
	$email = trim( (string) ( $acf['field_ow_contact_email'] ?? '' ) );
	$form  = trim( (string) ( $acf['field_ow_contact_form'] ?? '' ) );
	if ( '' === $email && '' === $form ) {
		acf_add_validation_error( 'field_ow_contact_email', 'Please give at least one contact method — an email or a contact-form URL.' );
	}
} );
