# Design — Sovereign Org Profiles

*This document is the **why** (the problem) and the **how you start** (the
on-ramp philosophy). The `README` is the **what** — the model and the repo
layout. Read this if you want to understand the reasoning, or if you're a
non-technical person trying to get listed today.*

---

## The problem this addresses

There is a persistent structural gap in regenerative economies: actors with
complementary needs cannot find each other without surrendering their data to
a platform. Land that lacks good stewardship sits adjacent to good stewards who
lack access to land. The same gap appears in every adjacent form — skills
without community, resources without recipients, offers without discoverable
wants.

Existing platforms don't close this gap. They either require a central
intermediary, introduce extractive incentives, or fail on the **cold-start
problem**: there's no first user with a burning need, so the network never
ignites. The pattern is always the same — distributed actors with complementary
needs who cannot find each other without giving up sovereignty over their own
information.

---

## The proposed stack

Two existing, already-running protocols — **composed, not built from scratch.**

### Murmurations — stable identity + relationships

[Murmurations](https://murmurations.network) is a distributed data-sharing
protocol for the regenerative economy. Each node (org, person, project) hosts a
JSON profile at its own URL. A shared index stores the URL and a hash — **not**
the data. Aggregators query the index to build maps and directories.

The protocol includes three profile types:

- **Organisation** — stable identity: name, location, mission, tags,
  relationships, contact
- **Person** — individual profiles
- **Offer or Want** — goods, services, land, skills offered or sought

The `relationships` field enables explicit, machine-readable links between
nodes — a hub profile can name its affiliated organizations as nodes; a
land-offer profile can name its relationship to the offering org.

Key properties:

- **Sovereign:** data lives at your URL, not in a central database
- **Pull-based:** the index fetches you; you don't push to it (with an optional
  nudge on update)
- **Eventually consistent:** not real-time; index polling cadence is hours to
  ~a day
- **No gate, no form, no approval:** host a JSON file, register the URL, you're in

### RSS — the dynamic update stream

Murmurations profiles are designed for relatively stable identity data. Dynamic
events — a new offer, a want fulfilled, a seasonal availability — need a
different layer. RSS is that layer: a pull-based, widely-supported feed format
already built into WordPress and most CMSes, and followed by aggregators and
feed readers.

The composition:

- **Murmurations profile** = who you are, where you are, what you offer/want
  structurally
- **RSS feed** = what's happening now — new offers/wants as they emerge, updates,
  news

An aggregator can follow both: index query for discovery, RSS subscription for
live updates. No platform intermediary at either layer.

### RSS Cloud — from polling to push

RSS 2.0 includes an optional `<cloud>` element
([RSS Cloud](https://en.wikipedia.org/wiki/RSS_Cloud)) that converts the feed
layer from pull to push. Subscribers register a callback URL; when the feed
updates, the endpoint notifies them directly. Latency collapses from hours to
seconds or minutes — which makes the stack viable for time-sensitive matching
(an offer that opens for a 72-hour window), not just stable identity.

RSS Cloud is **peer-to-peer** — the feed's own endpoint notifies subscribers,
with no hub intermediary. A cleaner fit for "gardens, not platforms" than
hub-based alternatives.

- **WordPress:** RSS Cloud support is native and built in. No configuration; the
  `<cloud>` element is already present in the feed.
- **WebFlow (and other hosts without `<cloud>`):** two paths —
  - **Webhook bridge (preferred):** a thin service receives the CMS's
    publish-webhook and pings registered RSS Cloud subscribers. One small,
    reusable piece of shared infrastructure, peer-to-peer.
  - **WebSub (fallback):** routes push through a hub rather than peer-to-peer.
    The sovereignty cost is the hub intermediary; acceptable if the hub is
    community-operated and open-source.

### Why this is different from previous attempts

The graveyard of sovereign-identity projects (Solid, Holochain-for-social,
various Web3 plays) shares one failure mode: **building the full stack before
finding the first user with a burning need.**

Murmurations + RSS avoids this because:

1. Both protocols are **already running** — real nodes, real aggregators, real
   index infrastructure.
2. The **Offer or Want** schema type was built for exactly this matching use
   case.
3. The minimal viable contribution is a JSON file and an RSS feed — both of
   which most orgs already have (WordPress ships RSS; the JSON is an afternoon
   of work).
4. It **composes** with what others are already building, rather than replacing
   it.

This is the *gardens, not platforms* principle applied to infrastructure:
composable primitives owned by their operators, not a platform owned by someone
else.

---

## Who this is for, and how to start

The protocol is free and running. So why doesn't every org have a profile? Because
the moment a non-technical person reads *"write a JSON file and host it at a URL
you control,"* they stop. **That stopping point is the cold-start problem.**
Lowering it is the most useful thing this project can do.

### The core tension: simple vs. sovereign

You cannot maximize both at once. The easiest path puts your profile on
someone else's domain; full sovereignty asks you to control a domain and a host.
So the goal is **not** a magic tool that is both — it's an **on-ramp** that lets
you start easy and become more sovereign on your own timeline, without ever
redoing your work.

### A graduated on-ramp

The concrete steps — the three tiers (0 listed-today, 1 sovereign-ish,
2 fully-sovereign), the one Index-Updater registration step, and the
copy-an-example shortcut — live in **[`GET-LISTED.md`](./GET-LISTED.md)**, the
plain-language guide for a non-technical newcomer. This document keeps the
*reasoning*; that one keeps the *recipe*, so there's a single canonical
step-list to maintain.

### Migration is the feature

Here is the part bare Murmurations doesn't foreground, and the reason this
on-ramp is honest rather than a sovereignty trap:

**Moving up a tier is just re-submitting a new URL to the index.** Your profile
*content* never changes — only where it lives. Start easy, become sovereign
later, and the work you do today carries forward unchanged — so the
ease↔sovereignty tradeoff above is a *sequence*, not a fork. The step-by-step
migration walkthrough is in [`GET-LISTED.md`](./GET-LISTED.md#migration-is-the-feature).

> *(The index stores URL + hash, so a tier change = a fresh submission plus
> retiring the previous URL. Confirm the current re-point / delete mechanics
> against the Murmurations docs before documenting them as a guaranteed path.)*

### What this project does NOT build

This is a scope guardrail — encode it, so the repo stays composable and doesn't
drift into rebuilding what already runs:

- **No JSON editor.** The MurmurMaps Profile Generator already does form → JSON.
- **No hosting service.** MurmurMaps already hosts for the website-less; GitHub
  Pages / Netlify / WordPress cover the sovereign tiers.
- **No new index or aggregator.** Murmurations' index and the existing
  aggregators (and maps like Kumu, networks like Bloom) already consume the data.

What this project **does** build is the thing that doesn't exist yet: a
**plain-language, opinionated on-ramp** — the tier guide above, the honest
ease↔sovereignty framing, and the migration path — plus a small set of **worked
example profiles** a newcomer can copy. The only tooling that could later earn
its place is a "starter kit" that collapses Tier 1→2 in one move (emit the JSON
*and* a GitHub-Pages-with-custom-domain host), and only once the guide proves
the demand.

---

## Open questions

- Does the chosen aggregator/network (e.g. Bloom) read from the Murmurations
  index, or require a separate submission?
- Can a Kumu map be configured to pull from Murmurations rather than manual
  entry — making the map a view over a sovereign substrate?
- For the dynamic layer: community-operated WebSub hub first (simpler), or the
  peer-to-peer webhook→RSS-Cloud bridge (more sovereign)? Depends on which
  non-WordPress hosts join first.
- What governance decides which nodes/tags define a given bioregional or
  thematic aggregator?
