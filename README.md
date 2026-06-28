# Sovereign Org Profiles

An open, composable way for organizations to publish the information that
defines them — once, at a URL they control — so that any network or agent,
human or machine, can discover and map it without that data being captured by
a platform.

This repository holds the **model, the protocols, and worked examples**. It is
*not* the canonical home of any organization's data. The canonical source for
each org lives at a URL on a domain that org controls, where it can be
syndicated to and mapped by any aggregator that asks.

---

## The idea in one paragraph

There is one piece of information that is fundamental to an organization — its
**profile** (who it is, where it is, what it's about) — and two that are
optional: **relations** (machine-readable links to other orgs) and **offers &
needs** (what it can give, what it's looking for). When each org publishes
these at its own canonical URL in an open, standard format, the network effects
that platforms normally hoard become a commons: maps, directories, and matching
services can all read the same sovereign sources, and the org never has to
maintain its identity in five different databases that drift out of date.

---

## Scope (this pilot)

- **Organizations, not people.** Person profiles are deliberately out of scope
  for now (that thread is being coordinated separately, around community
  legibility and activity-sharing). Orgs first.
- **One required object, two optional.**
  - **Profile** *(required)* — stable identity: name, location, mission, tags,
    contact.
  - **Relations** *(optional)* — explicit, machine-readable links to other
    nodes (a hub naming its affiliated orgs; an offer naming its offering org).
  - **Offers & Needs** *(optional)* — goods, services, land, skills offered or
    sought.
- **Canonical source = one org-controlled URL.** Everything else is a copy or a
  view. The committed JSON files in this repo are **reference examples**, not
  sources of truth.

---

## The stack

Two existing, already-running protocols — composed, not built from scratch.

### Murmurations — stable identity + relationships

[Murmurations](https://murmurations.network) is a distributed data-sharing
protocol for the regenerative economy. Each node hosts a JSON profile at its
own URL; a shared index stores the URL and a hash — **not** the data.
Aggregators query the index to build maps and directories. It is sovereign
(data lives at your URL), pull-based (the index fetches you), and gate-free
(host a JSON file, register the URL, you're in).

Data format, schema, and exchange protocols follow
<https://docs.murmurations.network/about/introduction.html> — open source and
**open to custom ontologies**, which is where this pilot can extend the base
schema as the network's needs become clear.

### RSS (+ RSS Cloud) — the dynamic layer

Profiles are for relatively stable identity. Time-sensitive changes — a new
land offer, a 72-hour availability window — need a stream. RSS is that layer
(native to WordPress and most CMSes), and **RSS Cloud** turns it from polling
to push: subscribers register a callback and get pinged on update, peer-to-peer,
with no hub intermediary. Latency collapses from hours to seconds.

> See `docs/` for the WordPress (native RSS Cloud) vs. WebFlow (webhook bridge
> or WebSub hub) paths, lifted from the project's design note.

---

## Who reads these profiles

The same sovereign sources are designed to be syndicated to and mapped by, among
others:

- **[murmurations.network](https://murmurations.network)** — the index +
  aggregator infrastructure
- **[BloomNetwork.earth](https://bloomnetwork.earth)** — regenerative community
  network
- **[CoFundEco on Kumu](https://kumu.io/bp8/cofundeco)** — relationship map

No aggregator owns the data; each one reads from the canonical URLs.

---

## What's in this repo

```
/profiles/    Example Murmurations profile JSONs (VdL, Mud Valley, Novas Descobertas)
/schema/      AEO/GEO assets per org — schema.org HTML + llms.txt
/docs/        The model, the RSS/RSS-Cloud paths, links to Murmurations docs
/README.md    You are here
```

The three worked examples come from the Vale da Lama bioregional network. They
exist so a newcomer can see a complete, real profile rather than an abstract
schema — copy one, change the fields, host it at your own URL, register it.

---

## How to participate

1. Copy an example from `profiles/` and edit it for your org.
2. Host it at a stable URL on a domain you control
   (e.g. `https://your-org.example/murmurations-profile.json`).
3. Register that URL with the Murmurations index.
4. *(Optional)* Add `relations` and an `offers/needs` profile.
5. *(Optional)* Wire your RSS feed for live updates (see `docs/`).

You never hand your data to this repo. You publish it where you control it; the
network comes to you.

---

## Why this isn't another platform

Previous sovereign-identity efforts (Solid, various Web3 plays) tended to build
the whole stack before finding a first user with a burning need. This pilot
composes two protocols that are **already running with real nodes and real
aggregators**, and its minimum viable contribution is a JSON file plus an RSS
feed — things most orgs already have. It's the "gardens, not platforms"
principle applied to infrastructure: composable primitives owned by their
operators.

---

## Collaboration

This repo is built in the open as a multi-agent, multi-human collaboration —
the history of how the tools are made is meant to be as visible as the tools
themselves. Contributions (new example profiles, schema extensions, aggregator
queries, bridge tooling) are welcome via pull request.
