# Get listed

*The plain-language on-ramp. If you run an organization and want it
discoverable on the regenerative-economy map — without handing your data to a
platform — start here. No code required to begin.*

> This is the **how you start** guide. For *why* the project exists and how the
> protocols fit together, see [`DESIGN.md`](./DESIGN.md).

---

## What "getting listed" actually is

You publish one small **Organisation profile** — a JSON file that says who you
are, where you are, and what you're about — somewhere on the web, and you
register that file's URL with the Murmurations index. From then on, maps,
directories, and matching services can find you by reading your file directly.
Nobody copies your data into a database you don't control.

That's the whole thing. The only real decision is *where the file lives* — and
that decision is reversible, so you can start in the easiest possible place
today and become more sovereign later without redoing any work.

---

## The one tension: simple vs. sovereign

You cannot maximize both at once.

- The **simplest** path puts your profile on someone else's domain. Fast, free,
  no website needed — but the URL points at their host.
- **Full sovereignty** means the file lives on a domain *you* control. More
  setup, but the canonical address is yours.

So this isn't a magic tool that's both. It's an **on-ramp**: start easy, move
toward sovereign on your own timeline. The migration costs you nothing (see
[Migration is the feature](#migration-is-the-feature) below) — so there's no
reason to wait until you can do the "proper" version.

---

## The three tiers

Pick the row that matches where you are today. You can move up later.

| Tier | Generate the JSON | Host it | Sovereignty | Who it's for |
|---|---|---|---|---|
| **0 — Listed today** | [MurmurMaps Profile Generator](https://murmurmaps.murmurations.network/profile-generator) (a form, no raw JSON) | MurmurMaps hosting | URL on *their* domain | Anyone with zero website and no time. In the index this afternoon. |
| **1 — Sovereign-ish, still simple** | Same MurmurMaps form | A dead-simple host you control: GitHub Pages, Netlify Drop, any static file host | You own the file; URL on a generic host | People who can follow a short guide and want to own their data. |
| **2 — Fully sovereign** | MurmurMaps form, or the Murmurations WordPress plugin | Your own domain: WordPress + plugin, or static files under `your-org.example/murmurations/` | Canonical URL on a domain you control | Orgs with a website (or willing to point a domain). |

Prefer to start from a real example rather than a blank form? Copy one of the
example templates in [`/profiles/`](../profiles/), change the fields to match
your org, and host the result — that's Tier 1 or 2 in one move. For complete,
real profiles to learn from, see the Vale da Lama network profiles under
[`/live/`](../live/).

This repo keeps its own profiles at Tier 1, over **GitHub Pages of this repo**,
as a **staging and validation** ground — they resolve as fetchable JSON at
stable project-controlled URLs, for example
<https://ludwa6.github.io/sovereign-org-profiles/live/vdl-murmurations-profile.json>.
But per the canonical-source decision in
[#19](https://github.com/ludwa6/sovereign-org-profiles/issues/19), the
**canonical** copy for each of our own orgs will live on that org's **own
domain** (Tier 2), and those Tier-2 URLs are what get registered with the Index
Updater below — the Tier-1 copies here are **not** registered. The full staging
list is in the [README "Live profiles"](../README.md#live-profiles) section.

---

## Where the file lives — the path convention

Once you host on your own domain (Tier 2), the only real decision is the
URL. Two layouts are valid:

- **Recommended — a `/murmurations/` directory.** Put your organisation
  profile at `https://your-org.example/murmurations/profile.json`, and
  any additional profiles alongside it:

  ```
  your-org.example/murmurations/profile.json
  your-org.example/murmurations/offer-volunteering.json
  your-org.example/murmurations/offer-internship.json
  ```

  This scales to any number of profiles — an Organisation profile plus
  one or more Offer-or-Wants — without inventing a second convention
  later. It is the layout this project's own orgs use.

- **Single-file alternative.** If you publish exactly one profile, the
  flat form `https://your-org.example/murmurations-profile.json` is
  simpler and still valid.

> **Your profile's URL is its identity.** The index hashes the URL, so
> changing it later means registering a **new** node and retiring the old
> one — not an in-place edit. Pick a path you can keep. And prefer your
> **bare domain** for `primary_url` (`https://your-org.example`, not
> `https://your-org.example/en/`): language- or section-paths are exactly
> what churns.

---

## The one step that actually puts you on the map

Whatever tier you chose, the file isn't discoverable until you **register its
URL with the Murmurations Index Updater**:

> <https://murmurmaps.murmurations.network/index-updater>

Paste your profile's URL, submit. The index stores your URL plus a set of
searchable fields — **not the file's full contents** — and aggregators query
the index and then fetch your profile from your URL. (Polling is
eventually-consistent: expect hours, not seconds, before you appear.)

---

## Migration is the feature

This is the part that makes "start easy" honest rather than a trap.

**Moving up a tier is just re-submitting a new URL to the index.** Your profile
*content* never changes — only where it lives.

1. Start at Tier 0 today.
2. Next month you get a domain. Host the **same JSON** there.
3. Submit the new URL to the Index Updater; retire the old one.

No rework. The thing you build this afternoon is the thing you keep.

> *(The index stores URL + hash, so a tier change is a fresh submission plus
> retiring the previous URL. The exact re-point / delete mechanics are being
> confirmed against the Murmurations docs — see the open questions in
> [`DESIGN.md`](./DESIGN.md#open-questions).)*

---

## Once you're listed (optional next steps)

None of these are required to be on the map. Add them when they're useful.

- **Relationships.** Add a `relationships` field to your Organisation profile to
  link to other nodes — a hub naming its affiliated orgs, for example. Each edge
  is `{"predicate_url": "…", "object_url": "…"}`. See the example template in
  [`/profiles/`](../profiles/) or the live VdL profile in [`/live/`](../live/).
- **Offer-or-Want.** Publish a separate Offer-or-Want profile to put a specific
  offer (land, skills, a resource) or a specific need into the matching layer.
- **A live update stream.** Wire your RSS feed so time-sensitive changes (a new
  offer, a 72-hour availability window) propagate without re-editing your
  profile. See [`DESIGN.md`](./DESIGN.md) for the RSS / RSS Cloud paths.
