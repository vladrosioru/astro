# AstroTherapia — concept & development record

*Last updated: 2026-07-26. This is a current-state document — sections get rewritten in place as decisions change, not appended to. Git history covers "how we got here" if it's ever needed; this file only needs to say what's true now.*

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

**Deliberate exception:** the site footer (every page) quotes *"Every man and every woman is a Star"* (Aleister Crowley, *Liber AL vel Legis*). This is the one intentional nod to occultism on the site, kept on purpose — decided 2026-07-07. Not a voice-rule violation to flag again.

## Messaging pillars

A quick-reference table to check new copy against before it ships. Adapted 2026-07-07 from external brand research the user brought in, harmonized with wording already locked elsewhere.

| Message | Content angle |
|---|---|
| Insight over prediction — "I don't tell you what happens. I help you understand why it happens." | Myth-busting content that debunks fatalistic astrology |
| Chart as mirror — "A birth chart isn't a prediction. It's a mirror." (already the manifesto's own line) | Use the metaphor consistently across posts and pages |
| Empowered decisions — "Understanding your patterns is how you change them." | Anonymized case-study style posts |
| Timing with agency — "Transits show the season you're in — you still choose how to move through it." | Retrogrades/eclipses reframed as reflection points, not warnings. This is also where the old Decisions & timing pillar's value lives now — see below. |
| Complementary, not clinical | Never claim to replace psychotherapy; "complementary tool" framing (tightens the About FAQ's therapy-comparison answer — see Open items) |

## The archetype hybrid (chosen content strategy)

Three brand structures were considered — *the psychological astrologer* (depth, slow reach), *life themes* (reach, dilution risk), *the AstroTherapia method* (funnel-first). The chosen direction blends the first two: the five life-theme pillars carry the reach, archetypal patterns carry the depth.

Originally four pillars; on 2026-07-07 **Decisions & timing was replaced** with two life-domain pillars, **Career & purpose** and **Identity & growth** — the stronger structural argument being that relationships/money/career/health/identity are all genuine life domains, while "decisions & timing" was really a cross-cutting lens ("why now") rather than a domain of its own. That lens didn't disappear — it now lives in the "timing with agency" messaging pillar above, applicable inside any of the five.

| Pillar | Example archetypes |
|---|---|
| Relationships | Mirror seeker, Guardian, Free spirit |
| Values & money | Builder, Provider, Risk-taker |
| Career & purpose | Strategist, Craftsman, Wanderer |
| Health & energy | Warrior, Healer, Dreamer |
| Identity & growth | Chameleon, Outsider, Phoenix |

Working name: **"The AstroTherapia Archetypes"** (alt: "The Inner Cast") — not finalized.

Standing guardrail: archetypes are a *current pattern*, never a fixed identity — "you tend to run the Guardian pattern," not "you are a Guardian." The placement mapping above is illustrative, pending the astrologer's own chart logic.

**Secondary taglines** (not locked — rotate freely for ad copy, FB bio variants; never replace the locked headline/subhead above):
- "Astrology that explains, not predicts."
- "Understand your why. Choose your next."
- "Your chart, your patterns, your power to choose."
- "Not a prescription — a map of possibility." (pull-quote use)

## Services: five-pillar flip cards (built 2026-07-26)

Proposed 2026-07-07, built 2026-07-26: replace/supplement the current 6 generic "Astrology" cards on Services with 5 cards, one per pillar, each flipping on click to reveal the astrological method behind it. Mockup saved at [`docs/mockups/services-five-pillar-flip-cards.html`](mockups/services-five-pillar-flip-cards.html) — a real, standalone, self-contained HTML file, open it directly in any browser to try the flip interaction. The live version ships as the Archetypes tab on Services (`resources/views/pages/services.blade.php`, `.svc-pentagon`/`.flip-card` in `public/css/services.css`, flip wiring in `public/js/services.js`), restyled with the site's actual design tokens instead of the mockup's own demo colors.

**Why only 1 of the current 6 cards maps to a pillar:** Relationship Analysis → Relationships. Natal Chart Analysis is foundational (touches all five). Progressions & Solar Returns, Elective & Horary Charts, Yearly Horoscope, and Astro Travel are all cross-cutting timing content — the old Decisions & timing pillar's territory, now dissolved into the "timing with agency" messaging point instead of its own pillar.

**Layout logic — genuinely astrological, not arbitrary:** houses group into angular (1st, 4th, 7th, 10th), succedent (2nd, 5th, 8th, 11th), and cadent (3rd, 6th, 9th, 12th). Three pillars land on angular houses — Identity & growth (1st/Ascendant), Relationships (7th), Career & purpose (10th/Midheaven) — so those three form the top row + center. Values & money (2nd, succedent) and Health & energy (6th, cadent) form the bottom row. Center position: **Identity & growth**, since the Ascendant is the chart's own starting point.

| Pillar | Front (what it's about) | Back — method + the archetypes it reveals |
|---|---|---|
| Relationships | Why you keep attracting — or avoiding — the same kind of connection | Venus, the Moon, and the 7th house · Mirror seeker, Guardian, Free spirit |
| Career & purpose | Why some work feels meaningful and other work just drains you | The Midheaven and 10th house, plus Saturn · Strategist, Craftsman, Wanderer |
| Identity & growth (center) | Why you feel most like yourself in some seasons, and a stranger in others | The Sun and Ascendant, against outer-planet transits · Chameleon, Outsider, Phoenix |
| Values & money | Why security, spending, and self-worth feel tangled together | The 2nd house, plus Venus and Saturn · Builder, Provider, Risk-taker |
| Health & energy | Why stress shows up in your body before you notice it anywhere else | The 6th house, the Moon and Mars · Warrior, Healer, Dreamer |

Updated 2026-07-07: the back now carries two things, not one — the astrological method (*how* it's read) and the three archetype names it tends to surface (*what* you'll recognize). These complement rather than compete.

**Mobile — confirmed direction (2026-07-07):** the pentagon doesn't scale down cleanly — shrinking cards enough to fit two per row on a phone makes the back-face technique text unreadable. Below a width threshold, switch to a swipeable one-card-at-a-time carousel with dot pagination. **Shipped 2026-07-26:** the mockup's own CSS-only single-column stack went live first (rows collapse to one column under 620px). **Upgraded to a real swipeable carousel 2026-07-26:** Archetypes, Methods, and Tarot all now use native CSS scroll-snap under 620px — one card in view, swipe or use the prev/next arrows/dots to move — instead of reusing the testimonial carousel's JS-transform-and-click mechanic as originally planned. That reuse was dropped deliberately: a real touch-scroll gesture never dispatches a synthetic `click` on the element being dragged, so scroll-snap sidesteps the flip-vs-slide click conflict structurally rather than needing custom touch-delta handling. Archetypes cards still flip on tap and reset to their front the instant a swipe begins; Methods and Tarot cards don't flip at all. The carousel opens on "Identity & growth" by default in Archetypes (reusing the existing `center` flag/`flip-card--center` class rather than a new marker) and on the first card elsewhere. Navigation loops past either end — "next" from the last card wraps to the first, and "prev" from the first wraps to the last — via a small JS-inserted clone of the first/last card at each end of the track (hidden on desktop), the standard technique for a seamless infinite-feeling loop without duplicating the visible card set.

Click/tap should be the primary flip interaction everywhere; hover-flip as a bonus only on devices that support real hover, not the primary trigger (touch devices don't have hover).

### Services page: two tabs + a separate Tarot section (resolves the replace-vs-supplement question above)

Proposed 2026-07-07, revised same day, **built 2026-07-26**: rather than three parallel tabs, split Services into **two tabs** (Archetypes, Methods) plus **Tarot as its own section below the tabbed area**, not a third tab. Nothing gets removed, and the earlier open question (does Archetypes replace the 6 service cards?) is still resolved the same way — Archetypes and Methods don't compete with Tarot for the same space, they just don't share a tab switcher with it either.

| Section | Content | Status |
|---|---|---|
| **Archetypes** tab (confirmed name 2026-07-07 — was "Patterns") | The five pillar flip cards above | Built |
| **Methods** tab (renamed from "Astrology") | The current 6 technique-based cards — Natal Chart Analysis, Progressions & Solar Returns, Relationship Analysis, Elective & Horary Charts, Astro Travel, Yearly Horoscope | Built, renamed only |
| **Tarot**, own section below the tabs (moved out of the tab group 2026-07-07) | The current 3 tarot cards, plus a newly-drafted short description (see Drafted copy) and its own separate "Book a Session" CTA | Built |

"Archetypes" replaced "Patterns" — it reuses a term the site already teaches (the About/Services FAQ already explains "archetypes" directly: *"Are the archetypes (like 'Guardian' or 'Builder') fixed personality types?"*) rather than introducing a second word for the same concept. Archetypes vs Methods is a deliberate pairing: Archetypes is organized by *what* you'll recognize (a named pattern), Methods is organized by *how* it's read (a technique) — the same duality as the front/back of each flip card, just at the tab level.

**Why Tarot moved out of the tab group:** two reasons. Practical — two tabs fit safely in one row at phone widths (confirmed by testing, see below), three don't. Conceptual — Archetypes and Methods are two views of the same underlying practice; Tarot is a categorically different modality, and folding it into the same three-way toggle implied it was a peer to the astrology views rather than a separate, deliberately-kept offering (decided 2026-07-07, see Site content plan). Giving it its own section keeps astrology as the clear core and Tarot honestly present but visually secondary.

**Discoverability risk, partially addressed:** if Tarot isn't in the tab bar, a visitor who came specifically for it might not scroll past the tabbed area to find it. The Tarot section's shell now carries `id="tarot"` (shipped 2026-07-26), so a jump-link is trivial to wire up later — but no visible "Looking for tarot readings? Jump down" line or anchor link exists near the tabs yet. Still open.

Also ties the Archetypes tab directly to the archetype-quiz content already planned for Journal/lead-gen — a visitor curious about "which archetype am I" on Services and in a future quiz post are the same hook.

**Desktop:** tabs + grid, reusing the existing `svc-tabs`/`svc-grid` mechanism already built. Confirmed 2026-07-07 — stays a static grid, unchanged. Not switching to carousel here: services cards are comparison content (someone deciding between two readings benefits from seeing them side by side), unlike the site's existing testimonial/journal carousels, which are sequential/narrative content where one-at-a-time costs nothing. Desktop already works well; no reason to downgrade it for consistency's sake.

**Mobile:** confirmed 2026-07-07, shipped 2026-07-26 — tabs stay as the category switcher, but the card grid underneath becomes a native scroll-snap swipeable carousel on mobile only (see "Mobile — confirmed direction" above for why this uses scroll-snap rather than the testimonial carousel's mechanics), since screen space is the actual constraint there, not a stylistic preference. Applies to whichever tab is active.

**Tab bar wrap bug — resolved by the design change, not by a CSS fix:** originally measured against a planned 3-tab set (Archetypes/Methods/Tarot), which at ~421px combined against a 335px content width would have wrapped to two uneven rows at 375px width. Once Tarot moved out of the tab bar entirely (2026-07-26), the shipped tab bar only ever holds 2 tabs ("Archetypes"/"Methods") — comparable in combined width to the previous working "Astrology"/"Tarotscopes" pair, so it holds one row without needing the equal-width-segments fix that was planned for the 3-tab version.

## Site content plan

About, Home, and Services currently run hardcoded placeholder copy from a generic demo theme — no CMS behind it. Journal is the one page already real: a database-backed post system (en/ro translations, CKEditor body, draft/published status) with a working admin editor at `/admin/posts`.

| Page | Role | Plan | Actual state (checked 2026-07-07) |
|---|---|---|---|
| **Homepage** | Fast orientation → booking or quiz | Hero, one-line differentiator, four-pillar teaser, 2 condensed quotes, journal preview, closing CTA — target ~150–220 words. | Partly there: the zodiac-trivia FAQ and Sun-sign sections are gone (matches the plan). But the differentiator strip, pillar teaser, condensed social proof, and closing CTA were never added — homepage is still just hero + one intro block + Journal preview. Hero **subhead is still the old placeholder line**, not the locked positioning statement. |
| **About** | Trust & philosophy | Hero → astrology intro → pillars overview → manifesto teaser (full essay on Journal) → practitioner bio → FAQ → testimonials. | Manifesto (full text) and the astrology-intro piece are both live, in that order, followed by the new FAQ. But: no pillars overview, no practitioner bio section, and testimonials were removed from this page entirely (now Services-only). |
| **Services** | Bookable offerings | Tabs restructured to the five pillars, Natal Chart Analysis featured as "start here." | Now **Archetypes / Methods** tabs (2026-07-26) plus a separate Tarot section below — the five-pillar flip cards live under Archetypes, Methods keeps the same 6 cards (renamed from "Astrology"), Tarot's 3 cards moved out of the tab bar into their own section with a new short description and its own CTA. Energy healing (4 services), Daily Horoscope, and Child's Horoscope were removed earlier. Hero subhead **does** use the locked positioning statement here (inconsistent with Homepage's stale one). Testimonials now live here, and the placeholder name is gone — quotes now credit **Andrei** by name. |
| **Journal** | Personal-voice blog | Reflections, current-sky commentary, session notes; hosts the pinned manifesto. | Unchanged — still the real CMS with no new posts published. The manifesto did **not** end up here; it was inlined into About instead (see Drafted copy). |

**Decided 2026-07-07: tarot readings stay** as a real, ongoing service — the 3 remaining tarot cards are intentional, not leftover placeholder content. Energy healing and "Daily Horoscope" were still cut as genuinely off-brand; tarot is different — kept deliberately. This is also reflected in the three-tab Services structure above, where Tarot is its own permanent tab alongside Archetypes and Methods, not something pending removal.

## Drafted copy

Source drafts live in `content/pages/`, checked against the actual templates on 2026-07-07.

| File | Title | Status | Where it actually landed |
|---|---|---|---|
| `content/pages/about-manifesto.md` | "Understanding the Why Behind Your Choices" | **draft** (updated 2026-07-07, not yet re-synced to the live page) | Inlined in full on the About page (`about.blade.php`), not teased-and-linked to a pinned Journal post as originally planned — a real deviation worth a conscious decision, not just letting it stand by default (see Open items). The 2026-07-07 update rewrote the pillar tour to cover all five current pillars (previously only relationships/money/health, plus an obsolete "decisions & timing" paragraph), with Identity & growth opening it as the central vortex the other four spin around, and each paragraph prefixed with its matching mockup icon (♡ ⟟ ◎ ◐ ☼). Live page still has the old four-pillar version. |
| `content/pages/about-astrology-intro.md` | "Why Knowing Your Patterns Can Change What Happens Next" | **live** | On the About page, right after the manifesto (word-for-word). A shortened variant also appears on the Homepage's intro section, blended with an older placeholder line ("far from cold prediction, a chart is a map"). |
| `content/pages/about-faq.md` | 8 new FAQs | **live** | All 8 items on **both** the About and Services pages, word-for-word. Item 8 (pricing) was added to the file 2026-07-07 and copied into About immediately; Services was still missing it until 2026-07-26, now synced. |
| Tarot section description (Services) | Short description between the "Tarot" heading and its 3 cards | **live** (drafted and shipped together, 2026-07-26) | `services.blade.php`'s `$tarotDescription` — no prior locked copy existed for this spot; drafted fresh in the site voice: "Tarot works a little differently than a chart reading, but it asks the same kind of question — not what's coming, but what's already moving underneath the thing you're sitting with. One card can sharpen a question you already have; three laid out side by side show where a situation has been and where it's actually heading. What you do with what surfaces is still yours to decide." |
| `content/pages/home-discover.md` | "What is AstroTHERAPIA?" | **draft** | Homepage "Discover" section. Added 2026-07-07 as a straight sync of the live text, then rewritten same day to actually answer its own headline — folds in the astro + therapeia etymology (with the Greek spelling, θεραπεία) and keeps the old placeholder's map metaphor rather than dropping it. No longer matches what's live in `home.blade.php`. |

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

**Content formats** (imported 2026-07-07, mapped onto the existing Journal content types rather than treated as a separate system):
- Educational carousels, one pillar per week — natural fit for "the sky right now"
- Myth-busting reels ("astrology isn't about predicting X — here's what it actually reveals") — also "the sky right now"
- Reflective-prompt endings (close a post with a question, not a statement) — apply across all post types
- Anonymized client-journey carousels/reels — natural fit for "from the consulting room"
- Seasonal transits reframed as reflection points rather than warnings — ties directly to the "timing with agency" messaging pillar

**Credibility angle, not yet adopted:** external research frames this approach as part of a recognized "therapeutic/psychological astrology" movement, citing sources like Fourth House Therapy and integrative-therapy blogs. Worth a future Journal post ("you're not the only one doing this") once those sources are independently verified — not adopted as a live claim yet.

## Legal disclaimer (wording ready, placement not decided)

Rewritten 2026-07-07 from a standard/generic version the user supplied, to match the site's voice — plain, first-person, and framed through the same agency principle that already runs through the manifesto ("the chart shows the pattern, you choose the response") rather than reading like a bolted-on legal clause. Keeps the same three functional pieces as the original (guide, not guarantee; good-faith effort; no responsibility for actions taken on it).

**Full version** (About, Services, Contact, or a dedicated page):
> A note on how to use this: what you hear in a session is meant as guidance, not certainty. I read your chart as carefully and honestly as I know how — but I can't guarantee it captures everything, and I can't take responsibility for decisions you make based on it. Those choices, and what comes of them, stay yours.

**Short version** (footer or fine-print use):
> Readings are offered as guidance, not certainty — the decisions that follow, and what comes of them, are always yours.

**Where it goes is not decided yet** — see Open items.

## Workflow & tooling

- **`content/pages/`** — staging convention for page copy. Each file front-mattered with `page`, `slot`, `locale`, `status`, `title`, `word_count`, `read_time`.
- **VS Code** — installed, opened on the project with drafts in tabs, for hand-editing outside the chat.
- **Session memory** (assistant-side, not in the repo) — mirrors the sections above for quick recall across sessions; this file is the source of truth if they ever diverge.
- **This file** — the one place to look for "what's the current brand/content plan," independent of chat history.

## Open items

**Resolved since last check (2026-07-07):**
- [x] About page copy — manifesto, astrology intro, and FAQ are all live.
- [x] Practitioner name — testimonials now credit "Andrei" by name (placeholder "Alice" is gone). A dedicated bio section still doesn't exist anywhere, though.
- [x] Services hero subhead — now uses the locked positioning statement verbatim.
- [x] Homepage's duplicate FAQ and Sun-sign sections — removed.
- [x] Energy-healing services (4), Daily Horoscope, and Child's Horoscope — removed from Services.
- [x] Homepage hero subhead — now uses the locked positioning statement verbatim, matching About and Services.
- [x] Tarot's keep-or-cut decision — resolved: tarot readings stay as a real, permanent service (see Site content plan).
- [x] Five-pillar flip-card idea for Services — built 2026-07-26 as the Archetypes tab (pentagon layout, flip interaction, restyled to the site's tokens).
- [x] Services restructure (Archetypes tab + Methods tab + separate Tarot section) — built 2026-07-26. The originally-planned 3-tab wrap-bug CSS fix turned out unnecessary once Tarot moved out of the tab bar (only 2 tabs ship).
- [x] Services FAQ missing item 8 (pricing) — copied in 2026-07-26, now matches About's 8 items exactly.

**Still open, plus a few newly surfaced:**
- [ ] No practitioner bio section anywhere on the site — the name is now known (Andrei), a proper bio still needs writing and a home (About page, per the plan).
- [ ] The manifesto was inlined in full on the About page rather than teased-and-linked to a pinned Journal post — worth a conscious decision (keep it inline, or still move the full text to Journal and shorten the About version) rather than leaving it as an unexamined default.
- [ ] Four pillar gaps — even with the Archetypes tab live, Values & money, Health & energy, Career & purpose, and Identity & growth still have no real *bookable* astrology service of their own (only Relationships does, via Relationship Analysis, under Methods); needs new service concepts, and the old timing-oriented services (Progressions & Solar Returns, Elective & Horary, Astro Travel, Yearly Horoscope) need remapping now that Decisions & timing isn't a pillar.
- [ ] Tarot discoverability — the section has `id="tarot"` (shipped 2026-07-26) but still no visible jump-link or nudge near the tabs pointing a tarot-seeking visitor down to it.
- [ ] No pillars overview section on About, and no four/five-pillar teaser, differentiator strip, condensed social proof, or closing CTA band on the Homepage — the "minimal homepage" plan is only partly built.
- [ ] Archetype placements are illustrative only — the chart mechanics behind each archetype are drafts, yours to finalize as the astrologer.
- [ ] Framework name not finalized — "The AstroTherapia Archetypes" vs. "The Inner Cast."
- [ ] Tighten the About/Services FAQ's "How is this different from therapy?" answer explicitly around "complementary tool" language (see Messaging pillars).
- [ ] Romanian translation not started — the site is bilingual (en/ro); all live/drafted copy so far is English only.
- [ ] Facebook page name, bio, and post voice are out of sync with the brand — see the Facebook page audit above; fixes drafted, not yet applied.
- [ ] Confirm whether "Online classes" (currently on the Facebook page) is a real, current offering — if so it needs a place in the services/pillars plan.
- [ ] Legal disclaimer — wording rewritten to match site voice (see section above), but where it should live is undecided: About, Services, Contact, footer, a dedicated legal/terms page, or more than one of these.
- [ ] Journal index has no subhead at all (just the "Cosmic Journal" title) — still open, not yet decided whether to use on the site. User's own draft (2026-07-07), currently the leading candidate:
  - **"Reflections on the sky, the self, and the seen and unseen patterns."**
  
  5 earlier candidates from Claude, still on the table too:
  1. "Not a horoscope column — reflections on the sky, the self, and the patterns worth noticing."
  2. "Notes on the sky and the why underneath it, not what's coming next."
  3. "Where I write about the current sky, real patterns from the practice, and the occasional longer thought."
  4. "A running notebook on astrology and self-understanding — no predictions, just what I'm noticing."
  5. "The sky, the self, and the patterns between them — written here as I notice them, not as a forecast."
- [ ] No `<meta name="description">` mechanism exists for Home, About, Services, or Contact (checked `layouts/app.blade.php`) — only Journal articles set one. Means sharing a Home/About/Services link on Facebook currently shows no description in the preview card. Once added, the positioning statement (or a trimmed version) is the natural content for it.
