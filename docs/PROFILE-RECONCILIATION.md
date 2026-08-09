# Profile reconciliation — Phase A of #19 (#20)

*Recorded 2026-08-09. Verified against the live Murmurations validator
(`https://index.murmurations.network/v2/validate`) and the two indexed
Tier 0 profiles this session — evidence, not recollection.*

## Context

§1 of #19 is decided: **Tier 2 (each org's own domain) is canonical;
this repo is the Tier 1 staging and validation ground.** The repo
profiles are what get deployed to the org domains in Phase B, so this
is where the divergence between the repo copies and the indexed Tier 0
copies is resolved, field by field.

The three org profiles previously **failed** validation on one repeated
defect: relationships were encoded as
`{"relationship_type": …, "related_url": …}`, but
`organizations_schema-v1.0.0` requires `predicate_url` and `object_url`.

## Sources compared

| Org | Repo copy | Tier 0 (indexed) copy |
|---|---|---|
| VdL | `profiles/vdl-murmurations-profile.json` | `murmurmaps…/profiles/ndfsflxk9jquy2xp49lf9z35` |
| MVF | `profiles/mud-valley-murmurations-profile.json` | `murmurmaps…/profiles/s9cmjiadwl5yypbnmaogrd3a` |
| AND (Novas Descobertas) | `profiles/novas-descobertas-murmurations-profile.json` | **none — no Tier 0 copy exists** |

The Index returns nothing for Novas Descobertas by tag, by name, or by
`primary_url` (confirmed independently by the Planner). The repo is its
**sole source**, so there is nothing to reconcile for AND — only the
validity/tag/rss fixes apply. Casa Vale da Lama has no profile at all
yet (Phase B).

## Guiding rule

**Repo content wins across the board.** For VdL the repo copy is a
**verified strict superset** of Tier 0 (identical fields *plus*
`full_address`, `header_image`, `image`), so keeping it discards
nothing. The Tier 0 copies are MurmurMaps-hosted and get retired in
Phase B. Three network-critical fixes are layered on top of every org
profile, and there is **one** field where Tier 0 wins.

> The issue-body divergence table listed `nickname` / `mission` /
> `status` / `country_iso_3166` as absent from the repo copies. That was
> incorrect and is retracted by its author — the repo carries all of
> them. The field lists above are the verified ones.

## Network-critical fixes (all three org profiles)

| Fix | Value | Why |
|---|---|---|
| `relationships` encoding | `{"predicate_url": "https://schema.org/affiliation", "object_url": "<target primary_url>"}` | Required by `organizations_schema-v1.0.0`; `schema.org/affiliation` matches the Tier 0 copies. Considered `schema.org/parentOrganization` for the MVF/AND → VdL "host" edges — rejected: VdL co-hosts independent legal entities, so *affiliation* is truer and safer than *parent*. |
| `barlavento-eco` tag | prepended to `tags` | The membership test both live barlavento.eco maps filter on. Valid-but-untagged = invisible. |
| `rss`, trailing slash | `…/feed/` on all three | Trailing slash is WordPress's canonical feed URL (bare `/feed` 302-redirects to `/feed/`); matches the Tier 0 copies and avoids the exact trailing-slash mismatch that caused #12. VdL previously had `…/feed` (no slash); MVF and AND had no `rss` at all. |

`object_url` values match each target's `primary_url` exactly so the
edges resolve: `https://valedalama.net`, `https://mudvalley.org`,
`https://novasdescobertas.org/en/`, `https://casavaledalama.com`.

## Per-field decisions where the copies genuinely differed

| Org | Field | Winner | Rationale |
|---|---|---|---|
| VdL | `name` | **Tier 0 — "Quinta Vale da Lama"** (keep `nickname: "VdL"`) | The one field where Tier 0 wins. Evidence: the site's own `<title>` is *"Regenerative Organic Farm • Quinta Vale da Lama"* (formal name); its `og:site_name` is *"Vale da Lama"* (brand). `nickname` carries the brand short-form, and the Index already holds "Quinta Vale da Lama", so consumers that have seen the node get continuity, not an apparent rename. |
| VdL | `relationships` (which edges) | **Repo — all 3** (MVF, AND, Casa) | Tier 0 carries only the MVF edge; the fuller relationship graph is the point of the project. Casa's edge won't resolve until Casa's Phase B profile lands — kept knowingly. |
| VdL | `description`, `mission`, `tags`, `full_address`, `image`, `header_image`, `contact_form` | **Repo** | Strict superset of Tier 0; no reason to regress discoverability or drop contact affordances. |
| MVF | `contact` email | **Repo — `info@mudvalley.org`** | A generic org inbox is a better public contact than Tier 0's personal `walt@mudvalley.org`. |
| MVF | `description`, `mission`, `tags`, `full_address` | **Repo** | Richer than Tier 0. |
| AND | all fields | **Repo (sole source)** | No Tier 0 copy to reconcile against. |

## Phase B follow-through (not in this issue)

- Deploy these files to each org domain at `/murmurations-profile.json`.
- Register the Tier 2 URLs with the Index Updater.
- Retire the Tier 0 nodes so no org has two registered copies.
