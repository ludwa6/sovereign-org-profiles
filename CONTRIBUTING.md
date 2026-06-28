# Contributing

This repo is built in the open — the history of how the tools are made is meant
to be as visible as the tools themselves. Contributions are welcome from
technical and **non-technical** people alike, and you can do everything from
your web browser. The only prerequisite is a free [GitHub account](https://github.com/signup).

New to the project? Start with the [`README`](README.md) for the model and
[`docs/GET-LISTED.md`](docs/GET-LISTED.md) for the plain-language on-ramp.

---

## Propose a change from your browser (no terminal, no git install)

GitHub lets you edit any file and open a pull request entirely in the web UI. If
you don't have write access, GitHub **automatically forks** the repo to your
account first — you don't have to do anything special.

**To edit an existing file:**

1. Open the file here on GitHub and click the **pencil icon** (✏️ *Edit this file*).
2. Make your changes in the editor.
3. Click **Commit changes…**, write a short note about what you changed, and
   choose **Create a new branch for this commit and start a pull request**.
4. Click **Propose changes**, then **Create pull request**. Done — a maintainer
   will review it.

**To add a new file** (e.g. an example profile for your own org):

1. From the repo home, click **Add file → Create new file**.
2. Type the file path and name (see naming conventions below), paste your
   content, and follow the same **Commit → new branch → pull request** flow.

That's it. No clone, no command line.

> Prefer the terminal? Fork, branch, and open a PR with `git` or the
> [`gh` CLI](https://cli.github.com/) (`gh pr create`) as usual. Both paths land
> in the same place.

---

## What's most useful to contribute

- **New worked example profiles.** A real, valid profile from your own
  organization is the single most valuable contribution — it helps the next
  newcomer copy rather than start blank. Both kinds welcome:
  - an **Organisation profile** (who you are, where, what you're about, plus an
    optional `relationships` field), or
  - an **Offer-or-Want profile** (a specific offer or need for the matching
    layer).
- **Doc improvements and translations.** Clearer onboarding in
  [`docs/GET-LISTED.md`](docs/GET-LISTED.md), or a translation for your
  language community, directly lowers the barrier this project exists to lower.
- **Schema extensions / custom ontologies.** Murmurations is open to custom
  schemas; proposals and examples are welcome.
- **Dynamic-layer tooling.** RSS / RSS Cloud bridges, aggregator queries, and
  similar composable pieces (see [`docs/DESIGN.md`](docs/DESIGN.md)).

**Before proposing new tooling, please read
[*What this project does NOT build*](docs/DESIGN.md#what-this-project-does-not-build).**
We deliberately compose existing, running tools (the MurmurMaps Profile
Generator, MurmurMaps hosting, the Murmurations index) rather than rebuild them.
A PR that reinvents one of those is likely to be declined — not because it's bad
work, but because it pulls the project off its "gardens, not platforms" footing.

---

## Adding an example profile

- Name it after your org: `your-org-murmurations-profile.json` for an
  Organisation profile, or `your-org-offer-murmurations-profile.json` for an
  Offer-or-Want. Put it in [`/profiles/`](profiles/).
- Start from an existing file in [`/profiles/`](profiles/) so the shape matches,
  or generate one with the
  [MurmurMaps Profile Generator](https://murmurmaps.murmurations.network/profile-generator).
- Validate before you open the PR: the generator will tell you if a field is
  wrong, and the
  [Index Updater](https://murmurmaps.murmurations.network/index-updater) checks
  your profile when you register its URL.
- Example profiles in this repo are **reference copies**. Your canonical profile
  still lives at a URL *you* control — see
  [`docs/GET-LISTED.md`](docs/GET-LISTED.md).

---

## How review works

- Keep pull requests **small and focused** — one profile, one doc fix, one
  feature. Small PRs get reviewed and merged faster.
- A maintainer reviews on GitHub and merges with the **Merge pull request**
  button. We may use AI-assisted review to give quick first-pass feedback;
  a human makes the call.
- Be kind and assume good faith. This is a collaboration across very different
  levels of technical comfort, and that's the point.
- This repo is built across two coordinated Claude surfaces — a **Planner** and
  a **Coder**. If you're curious how that division works, see
  [*Cross-surface collaboration*](AGENTS.md#cross-surface-collaboration-planner--coder)
  in `AGENTS.md`.

By contributing, you agree that your contributions are licensed under the
project's [MIT License](LICENSE.md).
