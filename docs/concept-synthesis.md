# AstroTherapia — concept & development record

*Last updated: 2026-07-07. This is a current-state document — sections get rewritten in place as decisions change, not appended to. Git history covers "how we got here" if it's ever needed; this file only needs to say what's true now.*

This is the "concept file" — the canonical, human-editable record of AstroTherapia's brand positioning, content strategy, and site content plan. When referred to as "the concept file" (or similar), read/update this file. It's kept in sync with the assistant's own session memory, but if the two ever disagree, **this file wins** — it's the one you can open and edit directly.

## Brand foundation

AstroTherapia — astrology + *therapeia* (Greek: care, healing). The single differentiator everything else is built on: astrology explains **why**, not **what will happen**.

Two lines are locked — reuse verbatim, don't paraphrase:

- **Hero headline / manifesto title:** "Understanding the Why Behind Your Choices"
- **Hero subhead / positioning statement:** "Your birth chart is the key to help you understand why you think, feel, and choose the way you do — so you can make your next decision with clarity, not guesswork."

Voice rules that follow from this:
- Never "you will" — always "you tend to," "this often shows up as"
- Replace fear/warning hooks with curiosity
- Close prediction-adjacent lines with agency: the chart shows the pattern, you choose the response
- Translate every astrological term into plain language in the same breath

## The archetype hybrid (chosen content strategy)

Three brand structures were considered — *the psychological astrologer* (depth, slow reach), *life themes* (reach, dilution risk), *the AstroTherapia method* (funnel-first). The chosen direction blends the first two: the four life-theme pillars carry the reach, archetypal patterns carry the depth.

| Pillar | Example archetypes |
|---|---|
| Relationships | Mirror seeker, Guardian, Free spirit |
| Values & money | Builder, Provider, Risk-taker |
| Health & energy | Warrior, Healer, Dreamer |
| Decisions & timing | Strategist, Wanderer, Phoenix |

Working name: **"The AstroTherapia Archetypes"** (alt: "The Inner Cast") — not finalized.

Standing guardrail: archetypes are a *current pattern*, never a fixed identity — "you tend to run the Guardian pattern," not "you are a Guardian." The placement mapping above is illustrative, pending the astrologer's own chart logic.

## Site content plan

About, Home, and Services currently run hardcoded placeholder copy from a generic demo theme — no CMS behind it. Journal is the one page already real: a database-backed post system (en/ro translations, CKEditor body, draft/published status) with a working admin editor at `/admin/posts`.

| Page | Role | Structure |
|---|---|---|
| **Homepage** | Fast orientation → booking or quiz | Confirmed closest to right shape already. Target ~150–220 words total: hero, one-line differentiator, four-pillar teaser, 2 condensed quotes, journal preview, closing CTA. |
| **About** | Trust & philosophy | Hero → astrology intro → pillars overview → manifesto teaser (full essay lives on Journal) → practitioner bio → FAQ → testimonials. |
| **Services** | Bookable offerings | Tabs restructured to the four pillars, Natal Chart Analysis featured as "start here." Two pillars (Values & money, Health & energy) currently have no matching service — a real gap. |
| **Journal** | Personal-voice blog | Not the pillar engine — reflections, current-sky commentary, session notes, with archetypes as one thread, not the organizing grid. Hosts the pinned manifesto. |

Flagged, undecided: 8 of the current 16 services (tarot, energy healing) and "Daily Horoscope" sit outside the why-not-what positioning entirely — keep, cut, or separate into their own area is still open.

## Drafted copy

Staged in `content/pages/`, held as copy — not yet wired into live templates.

| File | Title | Status | Length |
|---|---|---|---|
| `content/pages/about-manifesto.md` | "Understanding the Why Behind Your Choices" | draft | ~760 words, ~4 min read |
| `content/pages/about-astrology-intro.md` | "Why Knowing Your Patterns Can Change What Happens Next" (sits right after the About hero) | draft | ~170 words, ~45 sec read |
| `content/pages/about-faq.md` | 7 new FAQs, replacing 5 zodiac-trivia placeholders (worst offender: "twin flame," fated-soulmate language) | draft | 7 entries, ~2 min read |

## Facebook page audit

Audited 2026-07-07: [facebook.com/astrotherapia.ro](https://www.facebook.com/astrotherapia.ro/) (765 followers, not logged in — only the intro panel and one post were reachable, which is itself a signal).

| Element | Finding |
|---|---|
| Name | "Astro Therapia" (two words) vs. the locked "AstroTherapia" (one word) everywhere else. |
| Bio (Romanian) | Adjacent, not aligned — therapeutic-flavored ("blockages," "inner balance," "direction") but never states the actual differentiator (astrology explains *why*, not *what*). Reads as generic wellness copy. |
| Voice | The one visible post (Jun 21, 2025, solstice) is written in a mystical, incantatory register — "the Sky embraces the Earth," "the sacred fire of life," "may your soul…" This is the same register the "twin flame" FAQ was cut for; it contradicts the site's why-not-what, plain-spoken voice rules entirely. |
| Activity | Only one post loads without logging in, dated over a year before today — reads inactive, which undercuts the original goal of making website content easy to promote on Facebook. |
| "Online classes" tag | Not represented anywhere in the current services/pillars plan — unconfirmed whether it's current or stale. |

**Proposed fixes** (drafted in English, ready to translate):
- Rename the page to "AstroTherapia" (no space); Facebook may gate this behind a review step.
- New bio: *"AstroTherapia helps you understand why you think, feel, and choose the way you do. Not predictions — patterns, so your next decision comes from clarity, not guesswork."*
- Apply the same voice rules to future posts as the website (no fate/destiny language); example solstice post in the corrected register: *"The solstice is a good moment to notice what you've been pushing through instead of resting — not because the sky asks for a ritual, but because the shift in light tends to surface that pattern. What would it look like to actually rest this week?"*
- Tie posting cadence to the Journal plan above — cross-post each "the sky right now" entry to Facebook when it publishes.

## Workflow & tooling

- **`content/pages/`** — staging convention for page copy. Each file front-mattered with `page`, `slot`, `locale`, `status`, `title`, `word_count`, `read_time`.
- **VS Code** — installed, opened on the project with drafts in tabs, for hand-editing outside the chat.
- **Session memory** (assistant-side, not in the repo) — mirrors the sections above for quick recall across sessions; this file is the source of truth if they ever diverge.
- **This file** — the one place to look for "what's the current brand/content plan," independent of chat history.

## Open items

- [ ] Hero subhead in code is stale — `app/Models/SiteSetting.php:42` still has the old placeholder line, not yet swapped for the locked positioning statement.
- [ ] Practitioner name & bio undefined — current testimonial placeholders say "Alice." Needed for the manifesto's first-person voice and the About bio section.
- [ ] Archetype placements are illustrative only — the chart mechanics behind each archetype (e.g. Venus–Saturn for Guardian) are drafts, yours to finalize as the astrologer.
- [ ] Framework name not finalized — "The AstroTherapia Archetypes" vs. "The Inner Cast."
- [ ] Tarot & energy-healing services undecided — 8 of 16 current services, plus "Daily Horoscope," conflict with the brand; cut, or split into a separate area.
- [ ] Two pillar gaps — Values & money and Health & energy have no real astrology service yet; two concepts were sketched but not finalized.
- [ ] Romanian translation not started — the site is bilingual (en/ro); all drafted copy so far is English only.
- [ ] Nothing is wired in yet — all three drafts are staged files, not yet in the About template or the Journal database.
- [ ] Facebook page name, bio, and post voice are out of sync with the brand — see the Facebook page audit above; fixes drafted, not yet applied.
- [ ] Confirm whether "Online classes" (currently on the Facebook page) is a real, current offering — if so it needs a place in the services/pillars plan.
