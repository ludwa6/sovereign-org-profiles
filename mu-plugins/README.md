# mu-plugins

Must-use plugins for sovereign-org-profiles partner sites. Drop-in,
single-file, no activation step — WordPress loads everything in
`wp-content/mu-plugins/` automatically.

| File | What it does |
|---|---|
| `rsscloud.php` | Spec-compliant RSS Cloud endpoint (register + challenge + async publish notification) so an external aggregator (e.g. FeedLand) can subscribe to the site's feed. |
| `offer-want-authoring.php` | Dynamic Offer-or-Want datatype: an `offer_want` CPT + ACF authoring form, emitting each record on two channels — a Murmurations-valid JSON artifact (`…/offer-want/{slug}/?murmurations=1`) and a dedicated RSS feed with a custom `sop:` namespace. |

## `offer-want-authoring.php` dependencies

The plugin degrades gracefully without these — the CPT, the JSON
artifact, and the RSS feed all work — but the **authoring form** needs
them:

- **[Advanced Custom Fields](https://wordpress.org/plugins/advanced-custom-fields/)**
  (free) — renders the Offer-or-Want form. If absent, the `acf/*` hooks
  no-op; you'd be authoring via the block editor / REST instead.
- **[acf-openstreetmap-field](https://github.com/mcguffin/acf-openstreetmap-field)**
  (free, no API key) — the map picker for geolocation. If absent, the
  `open_street_map` field won't render and the pin→`ow_lat`/`ow_lon`
  hook simply records nothing (geolocation stays optional either way).

Install both on a target site with wp-cli:

```sh
wp plugin install advanced-custom-fields acf-openstreetmap-field --activate
```

## Scope / caveats

- **LAN-proven only.** JSON shape validated against Murmurations' own
  validator and the feed is well-formed XML, but live index
  *registration* and external *push* still need the public staging clone.
- The `sop:` namespace name + URI are working placeholders — canonical
  naming/versioning is deferred (change the `SOP_OW_NS` / `SOP_OW_NSURI`
  constants). Likewise the exchange-type vocabulary (barter → "Gift /
  swap") is a prototype compromise pending staff review.
