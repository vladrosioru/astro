# AstroDynamics — Plan Final

*A two-step plan for a new digital diagnostics service subcategory on
AstroTherapia: Step 1 launches lean, built deliberately so Step 2 (a fuller
growth engine) is an upgrade, not a rebuild. A standalone spin-off (its own
domain/brand) is described in §7 as a documented alternative, not pursued now.
This document is self-contained — written so it carries everything needed to
implement it later, without needing prior conversations for context. Nothing
here has touched the codebase; it is a complete specification document for
review.*

*Supersedes [`OLD_concept-cosmic-playbook.md`](OLD_concept-cosmic-playbook.md)
in this same folder. That document's strategy — a chart-based advice product
aimed at a man pursuing a specific woman — is **replaced**, for the reasons in
§1.1. Its
engineering detail, market research, and risk analysis are **kept**, merged in
here and re-aimed at the new audience. The old file is retained for history; if
the two ever disagree, this one wins.*

## Decisions already locked

Quick-reference for anyone (including a future session) picking this up without
prior context. Everything below was decided deliberately, not defaulted to —
treat changing any of these as an architectural decision, not a casual edit.
Each row carries its *why*, so the decision can be defended or deliberately
reversed later rather than silently re-litigated:

- **Two-step structure**: build the lean version first (§2), start the
  growth-engine version (§4) only once Step 1's KPIs (§2.7) justify it — never
  build Step 2 first. *Why: Step 2 costs 10–16 weeks and ongoing marketing-ops
  load; Step 1 answers "will anyone pay for this" in 4–7 weeks.*
- **Naming & identity**: **AstroDynamics** (derived naturally from AstroTherapia
  Dynamics) — an automated relational dynamics engine positioned under
  AstroTherapia's digital services umbrella. *Why: derives from the parent brand
  rather than competing with it, and reads as a diagnostic tool rather than as
  tactics run on another person. Known caveat in §1.3.*
- **Audience & relational architecture**: completely gender-neutral and
  inclusive of all relationship types and orientations, organized around a
  three-stage relational framework (**The Crush / New Spark**, **The
  Situationship / Mixed Signals**, **The Committed Partner / Long-term
  Friction**). Evaluates communication rhythms, emotional safety, vulnerability
  triggers, and friction points rather than pickup clichés or fatalistic
  fortune-telling. *Why: §1.1 and §1.2.*
- **Content tone**: conversational, insight-driven, non-fatalistic, and
  psychologically grounded — *"the chart reveals the pattern, you choose the
  response."* It avoids high-friction esoteric jargon, fatalistic matchmaking
  ratings ("you are 42% compatible"), and manipulative dating tactics. This is
  the test **all** content has to pass — product copy and promotional content
  alike (§2.6), free and paid, at both steps.
- **Placement**: integrated directly inside the existing AstroTherapia Laravel
  app as a core subcategory under Services at
  `/{locale}/services/astrodynamics` (aliased at `/{locale}/astrodynamics`),
  sharing domain authority, database, models, and layout templates with the main
  app. *Why: fastest and cheapest validation path — no second hosting bill, no
  second app to patch, and it inherits existing domain trust. See §7 for the
  conditions under which this would change.*
- **Payments provider**: Stripe, via the already-specified but unbuilt
  `PaymentProvider` design at
  `docs/superpowers/plans/2026-06-25-reusable-site-template-php-payments.md`
  (§2.3) — build against that interface, don't design a new one. *Why: a
  fully TDD-specified payments architecture already exists in this repo,
  unbuilt; designing a second one is pure waste.*
- **Mailing provider**: Brevo, chosen over Mailchimp, built as shared app-wide
  infrastructure rather than something scoped only to this section (§3.3) —
  deliberately closes a gap already flagged on the main site, not just this
  page's own need. *Why: full rationale in §3.3.*
- **Promotion**: international target (US, UK, Canada, Australia, Northern
  Europe) executed via short-form video on TikTok, Instagram Reels, and YouTube
  Shorts (§2.6). Lead with educational text teardowns, placement communication
  comparisons, and high-aesthetic shareable result cards — strictly avoiding
  suggestive thirst-trap imagery or localized promotions. *Why: §2.6.*
- **Standalone spin-off** (own domain/brand, full creative and brand distance):
  documented and ready in §7, deliberately **not** the starting point — revisit
  only under the conditions listed there.

## Open questions needing an owner decision

Not blockers for starting §2, but each needs an answer before the thing it
touches ships. **This table is the section's follow-up queue** — add a row
whenever a new open question, risk, or follow-up surfaces during the build, and
strike one when it's decided. It deliberately lives inline rather than in a
separate file: the predecessor plan kept its queue as its own document, which
drifted out of sync with the plan it was tracking within a single revision.

| # | Question | Needed before | Recommendation |
|---|---|---|---|
| 1 | Does the Step 2 "Dynamic Archetype" reuse **The AstroTherapia Archetypes** vocabulary (§1.2) or invent its own? | Step 2 quiz content; ideally decided in Step 1 so the card copy is written once | Reuse. It is the difference between a bolt-on and the Relationships pillar's commercial expression. |
| 2 | Does the section ship bilingual (en/ro) or English-only at Step 1? | §2.4 routes, §3.1 `locale` | English-only content at Step 1, routes bilingual from day one (§1.4) — cheap to keep the door open, expensive to retrofit. |
| 3 | Is Tier 3's exact-synastry engine in Step 1 scope, or deferred? | §2.2, and the whole §2.7 estimate | Defer the true aspect engine (§3.7). Ship Tier 3 as Moon-sign emotional-safety content only. |
| 4 | Social handles and (if §7 is ever taken) domain availability for "AstroDynamics" | Before the first social account is created | Check early; see the naming caveat in §1.3. |
| 5 | Double opt-in or single opt-in for the marketing list? | §3.3 build | Double opt-in for anything feeding an ongoing sequence; single for the one-off "here's your result" send. |

## Contents

1. [Concept & positioning](#1-concept--positioning)
2. [Step 1 — Lean launch](#2-step-1--lean-launch)
3. [Technical foundation — built once, sized for both steps](#3-technical-foundation--built-once-sized-for-both-steps)
4. [Step 2 — Growth engine](#4-step-2--growth-engine)
5. [Combined pros & cons](#5-combined-pros--cons)
6. [Risks & conditions of application](#6-risks--conditions-of-application)
7. [Alternative: a standalone spin-off](#7-alternative-a-standalone-spin-off)
8. [OVERVIEW](#8-overview)

---

## 1. Concept & positioning

AstroDynamics helps anyone navigate a romantic connection — whether an early
crush, an ambiguous situationship, or an established partnership — by reading
the cross-chart dynamics (Sun, Moon, Mercury, Venus, Mars) between two people
and translating those astrological patterns into actionable communication
insight: how both partners process vulnerability, what triggers emotional
withdrawal or defensiveness, how affection is received, and how to bridge
natural element misalignments.

It is framed as **"AstroTherapia's Relational Pillar, Made Immediate"**: same
why-not-what philosophy, same non-fatalistic stance — *"the chart shows the
pattern, you choose the response"* — but delivered as an interactive digital
diagnostic tool rather than requiring an immediate high-consideration 1:1
consultation.

**Content tone stays psychologically grounded, empowering, and mature
throughout, free and paid, at both steps.** It never indulges in fatalistic
predictions ("you are doomed to break up"), binary compatibility scores, or
manipulative dating tactics. This keeps the whole service Stripe- and
Google-Ads eligible, protects AstroTherapia's therapeutic brand equity, and
removes the need for age-gating or high-risk merchant accounts.

It lives natively inside the existing AstroTherapia Laravel app at
`/{locale}/services/astrodynamics` (or `/{locale}/astrodynamics`), visually
distinct from the standard informational service pages via a section-scoped
interactive skin rather than a detached theme package — see §2.5 and §3.6.

### 1.1 Why the audience changed (and why the stage framework replaced signs)

This plan's predecessor aimed at *a man trying to understand a specific woman*.
That was dropped deliberately, for four reasons worth keeping on the record so
the decision isn't quietly reversed:

1. **It targeted the hardest available segment.** Men are a minority of
   astrology buyers and the most price- and credibility-resistant slice of them.
   The audience that already pays for synastry reports on Etsy and subscribes to
   Co-Star and The Pattern (§8.2) skews strongly the other way. The
   gender-neutral framing addresses several times the market for the same build.
2. **It forced a permanent brand argument.** A "how to talk to her" product
   sitting one domain away from grief, career, and identity content is a
   standing tension — the predecessor listed it as an unresolved con and as the
   main reason a standalone spin-off might be needed. A relational-dynamics
   diagnostic has no such tension; it is straightforwardly the Relationships
   pillar with a faster delivery mechanism.
3. **It made the promotion strategy a live risk.** The predecessor's launch idea
   — suggestive imagery paired with sign hooks — needed a whole risk analysis
   and left the only unresolved item in the follow-up queue. Removing the
   premise removes the risk rather than managing it (§2.6).
4. **Stages beat signs as a segmentation axis.** A sign never changes; a
   relationship stage does. Segmenting by *Crush → Situationship → Committed*
   gives a repeat-purchase mechanic below the subscription tier (a stage
   transition is a legitimate second Blueprint), an email-list split that
   actually predicts what someone wants to read next, and a content vocabulary
   people already use about themselves.

**Marketing note worth acting on:** "situationship" is a culturally live,
high-volume, low-competition term in astrology content. Nobody in this niche
owns it. It deserves a dedicated stage landing, its own `/guide/{slug}` cluster,
and to be the lead hook in the first month of short-form video (§2.6).

The user selects the current stage of their connection, unlocking tailored
insight:

- **The Crush / New Spark**: breaking the ice, genuine rapport, first-date
  chemistry, and avoiding over-pursuit.
- **The Situationship**: decoding mixed signals, attachment rhythms, emotional
  availability, and clear communication boundaries.
- **The Committed Partner**: recurring conflict cycles, element misalignments
  (e.g. Air rationality vs. Water emotional processing), and repair language.

### 1.2 Relationship to The AstroTherapia Archetypes

`docs/concept-synthesis.md` already defines the site's chosen content strategy:
five life-theme pillars, each carrying named archetypes, with the **Relationships**
pillar mapped to *Mirror seeker, Guardian, Free spirit* (read through Venus, the
Moon, and the 7th house). The 2026-07 project analysis
([`docs/project-analysis-2026-07.md`](../docs/project-analysis-2026-07.md))
names the **Archetype Quiz + email list** as the single highest-ROI build on the
whole project.

**AstroDynamics should be built as that pillar's commercial expression, not
beside it.** Concretely:

- Step 2's "Dynamic Archetype" quiz result (§4.2) uses the **Relationships
  pillar's archetype vocabulary**, extended with relational-dynamic pairings,
  rather than inventing a parallel naming system. One vocabulary, taught once,
  reinforced in two places.
- The quiz engine (§4.6) and the mailing infrastructure (§3.3) are the same
  build the main site needs for its own Archetype Quiz. Building them here means
  the main site's flagged gap closes as a side effect rather than as a second
  project.
- The standing guardrail from the concept file applies verbatim: an archetype is
  *a current pattern, never a fixed identity* — "you tend to run the Guardian
  pattern," not "you are a Guardian." The same rule applies to a relational
  dynamic: name the pattern, never the verdict.

This is the strongest single argument for building AstroDynamics inside
AstroTherapia rather than as a spin-off, and it should be stated that way when
the decision is revisited (§7).

### 1.3 A known caveat about the name

"Astrodynamics" is an established aerospace term (the study of orbital
mechanics — NASA, spacecraft trajectory work). Practical consequences:

- **Inside this plan it doesn't matter.** The section lives at
  `astrotherapia.com/services/astrodynamics` and ranks for long-tail relational
  queries ("how to communicate with a Water Moon", "situationship astrology"),
  not for the bare term.
- **It matters for social handles.** Check availability across TikTok,
  Instagram, and YouTube before creating the first account; expect the clean
  handle to be taken and plan a consistent qualified variant
  (`astrodynamics.love`, `theastrodynamics`) used identically everywhere.
- **It matters most for §7.** If the spin-off path is ever taken, the exact-match
  domain is likely unavailable or expensive, and the organic SERP for the bare
  term is contested by aerospace content. Factor that into the cost of that
  option, not this one.

### 1.4 Locale posture

The commercial target is English-speaking international (§2.6), but the routes
stay under the existing `{locale}` group (`en|ro`) and `astrodynamics_leads`
keeps a `locale` column from day one. Reason: the app is already bilingual, the
route group already exists, and retrofitting locale support into a live section
costs far more than carrying a column that defaults to `en`. Ship the *content*
in English only at Step 1; keep the *plumbing* bilingual.

Note that this weakens — but does not remove — one of the Brevo arguments in
§3.3 (EU hosting mattering for a Romanian audience). The other Brevo arguments
stand on their own.

---

## 2. Step 1 — Lean launch

**Goal:** ship the smallest real version of the product, reusing the existing
app and deploy pipeline, to answer one question cheaply — *will an international
audience pay for immediate, actionable dual-chart relationship blueprints and
communication guides?* — while laying the groundwork in §3 so a "yes" doesn't
mean starting over.

### 2.1 Target user & funnel

Adults (20s–30s, all genders, all orientations, all relationship configurations)
navigating a specific connection.

Funnel: **progressive dual-input tool → email capture → immediate free
relational signature → paid full AstroDynamics Blueprint ($19–29) → 1:1
relationship consultation upsell ($79–99)** a few days later by email.

### 2.2 Free offerings & progressive precision inputs

A major point of friction in relationship astrology is asymmetric information: a
user rarely knows their crush's or partner's exact birth time or birthplace on
date two. Demanding full birth data up front drives abandonment — and it is the
single biggest reason chart-based relationship tools lose visitors before they
see any value.

Step 1 introduces **Progressive Precision Inputs**. This is the plan's most
important conversion mechanic, so it is worth stating what it actually does:
it moves the entry cost to *two dropdown selections*, delivers real value there,
and then offers each additional data point as an unlock rather than a
requirement. The ladder is the product's own upsell rehearsal.

- **Tier 1 (Signs only)** — user selects their Sun sign, their partner's Sun
  sign, and the relationship stage. Delivers immediate high-level conversational
  dynamics, elemental balance (e.g. Fire + Earth), and initial connection
  patterns. *Entry cost: three dropdowns, no typing, no personal data.*
- **Tier 2 (Birthdates)** — adding both birthdates unlocks Sun, Mercury
  (communication style), Venus (love language / attraction), and Mars
  (assertiveness / conflict triggers). *Entry cost: two dates. No time or place
  needed — these four placements are date-derivable.*
- **Tier 3 (Exact time & city — optional)** — unlocks the Moon sign (emotional
  safety and deep attachment), with an explicit disclaimer when a time is
  unknown ("calculated using a noon solar chart — your Moon may fall in the
  adjacent sign"). *See §3.7 for exactly what Tier 3 does and does not include
  at Step 1 — this is the one place where scope can quietly explode.*

All four tools below are buildable as **rule-based template content** (if
aspect/element X in stage Y, show paragraph Z) plus an ephemeris helper to
derive Sun/Moon/Mercury/Venus/Mars from birth data — no AI runtime and no
general-purpose chart engine required (§3.7).

**Every one of these free tools produces an actual takeaway document or image
the visitor can keep and forward — not just an on-screen result that disappears
when the tab closes.** This is deliberate: the artifact itself is the
distribution mechanism (someone else sees it and comes to the site to get their
own). Each tool's result page ends in three concrete actions:

- **Download** — the file itself, saved to the visitor's device.
- **Email it to me** — sends the same file/image, via the Brevo mailing system
  in §3.3, to an address the visitor provides. This is also the lead-capture
  moment — the email address collected here is the lead.
- **Send via WhatsApp / Share to Stories** — a `https://wa.me/?text=...` share
  link pre-filled with a short caption and the hosted URL of the artifact, plus
  a direct download for a 1080×1920 card optimized for Instagram/TikTok stories.
  WhatsApp's own share mechanism works from a link, not a raw file attachment
  from a website, so the file needs a real public URL to point at — already true
  for the PDF/image files below. If a phone number is ever collected alongside
  the email, a direct WhatsApp Business API send becomes possible, but that is
  extra integration cost — an optional Step 2+ enhancement, not something Step 1
  needs. (The `phone` column in §3.1 exists only to keep that door open.)

The four tools, with their concrete output format:

1. **Personalized Relational Signature Sheet** — dual Sun/Moon dynamic: how both
   partners process emotion (Moon) and express identity (Sun). **Output: a
   one-page PDF**, branded with the AstroDynamics look (§2.5), generated
   server-side from a print-styled Blade view using a pure-PHP PDF library such
   as `dompdf` — no external binary, fits the shared-hosting / no-Node
   constraint.
2. **Love & Conflict Dynamic Guide** — a reference sheet on how Venus (relational
   affection) and Mars (assertiveness / frustration) interact. **Output: a
   downloadable reference PDF.** Build this as **element-pairing based and fully
   evergreen** (Fire Venus × Water Mars, and so on — a bounded set of
   combinations rendered once and cached), *not* per-user personalized. Its job
   is to be an SEO- and share-friendly evergreen asset that costs nothing per
   request; personalization is what the paid Blueprint sells.
3. **Chemistry & Blind Spots Teaser** — enter both individuals' placements, get
   1–2 free connection highlights ("your Mercuries are trine — banter is
   effortless; your Mars signs square — defensiveness escalates fast"); the rest
   is gated behind the paid Blueprint. **Output: an in-page result plus a
   shareable 1080×1920 story image** carrying the highlight as short, punchy text
   over the AstroDynamics motif — generated via Blade/SVG to PNG server-side.
   This is the tool most worth optimizing for the share flow, since the image is
   designed to be reposted, not just kept.
4. **Conversational Rhythm Cheat Sheet** — "what makes them open up vs. what
   triggers retreat, tailored by stage" — a short, shareable reference.
   **Output: the same shareable-image format as #3.**

### 2.3 Paid offerings, pricing, and the payments foundation

- **Full Personalized AstroDynamics Blueprint** (cross-chart synthesis):
  **$19–29 one-time.** Delivered as an interactive in-page report plus a
  downloadable 10–14 page PDF covering deep synastry friction points, emotional
  repair strategies, long-term trajectory, and concrete communication scripts.
- **1:1 Relational Consultation**: **$79–99 for 45 minutes**, booked after report
  delivery while intent and curiosity are highest, routing directly into
  AstroTherapia's primary consultation calendar.

**Why these numbers** (the anchors, so the prices can be defended or moved
deliberately — all sourced in §8.2):

| Offer | Price | Anchor |
|---|---|---|
| Blueprint | $19–29 | Etsy software-generated synastry reports run **$15–30**; fully custom written compatibility books run **$150–375**. Sitting at the top of the automated band is defensible because the output is stage-specific and behavioral, not a generic PDF dump. |
| Consultation | $79–99 / 45min | Mainstream 1:1 dating coaching runs **$75–170 per 45–60 min**. Priced like coaching, differentiated by the chart-based "why." The low end is deliberate: this is an upsell to someone who has already paid $19–29, not a cold-sale price. |
| Subscription (Step 2) | $9–14 / mo | The Pattern's Go Deeper+ runs **from $14.99/mo** for general personalized content. Priced below it because the scope is narrower (one relationship, not a whole life), but not far below — specificity justifies proximity. |

All three should be A/B tested continuously once traffic supports it; treat the
ranges above as launch brackets, not final numbers.

**On the payments foundation** — build against the dormant, fully-specified,
test-driven implementation plan already in this repository at
`docs/superpowers/plans/2026-06-25-reusable-site-template-php-payments.md`.
"Dormant" here means specifically: a step-by-step TDD implementation plan, with
exact file paths, exact class/interface signatures, and failing tests to write
first (per this repo's TDD rule), that was **written but never built**. It
specifies:

- `App\Payments\PaymentProvider` — an interface with four methods:
  `createCheckout(array $line, string $successUrl, string $cancelUrl): CheckoutResult`,
  `verifyWebhook(string $payload, string $signature): bool`,
  `handleWebhookEvent(string $payload): WebhookResult`, and
  `getStatus(string $reference): string`.
- `App\Payments\CheckoutResult` / `App\Payments\WebhookResult` — small readonly
  DTOs the interface returns.
- `App\Models\PaymentSettings` — a singleton DB row holding **non-secret** public
  config (provider name, currency, success/cancel URLs, enabled payment
  methods), editable from an admin page at `/admin/payments`. The actual Stripe
  secrets (`STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`) live only in `.env` — never
  in the database, never in the admin UI.
- `App\Payments\FakePaymentProvider` — a deterministic, no-network fake,
  auto-bound whenever the app runs in the `testing` environment, so the test
  suite never calls Stripe. This is what makes the design usable under this
  repo's TDD rule (`php artisan test` must not touch the network) without extra
  work.
- `App\Payments\StripePaymentProvider` — the real adapter, built on the official
  `stripe/stripe-php` SDK, implementing **one-time Stripe Checkout Sessions**
  (`mode: payment`) and signed-webhook verification via
  `Stripe\Webhook::constructEvent`.
- Routes: `POST /{locale}/checkout` (creates a Checkout Session, redirects to
  Stripe's hosted page) and a public, CSRF-exempt, signature-verified
  `POST /payments/webhook`.

**How Step 1 should use it**: implement that plan's Tasks 1–4 close to as
written — the interface, DTOs and settings model; the fake provider and
container binding; the Stripe adapter; the checkout and webhook routes — scoped
to AstroDynamics' two products (the Blueprint and the consultation, each just a
one-off `createCheckout()` call with its own `name`/`amount` line item). Task 5
(the `/admin/payments` settings page) is optional for a true v1 but cheap to
include since it's already fully specified.

**Three things to check, not assume, when actually building this:**

1. The plan document's own header says **Laravel 11.x** while the app is now on
   **Laravel 13** (`composer.json`). The pattern itself isn't
   Laravel-version-sensitive, but verify the current shape of
   `bootstrap/app.php` and `bootstrap/providers.php` before pasting the plan's
   snippets verbatim.
2. The plan is written as **"Plan 3 of 4,"** declaring two prerequisites — a
   `SiteSetting` model + locale route group, and an `admin` middleware + admin
   dashboard. **Both already exist** in this codebase today
   (`app/Models/SiteSetting.php`, the `{locale}` route group, and a working
   `/admin/*` area with post CRUD), so only the payments slice actually needs
   building. Don't rebuild the prerequisites.
3. The plan implements **one-time Checkout only** (`mode: payment`) — no
   subscription billing. That's intentional and correct for Step 1, which has no
   recurring product. Step 2's subscription tier (§4.3) needs a follow-on
   addition — a `createSubscriptionCheckout()`-style method on the same
   interface (Stripe's `mode: subscription`) plus subscription-lifecycle webhook
   handling (renewed / cancelled / payment failed). That is new work at Step 2,
   not a gap being left in Step 1.

**One table not in the dormant plan itself** — `astrodynamics_orders`
(id, `lead_id` FK, `product` enum: `blueprint`/`consultation`/`subscription`,
`amount`, `currency`, `provider_reference` — the Stripe session or subscription
id, `status`: pending/paid/refunded/cancelled, timestamps) — updated by the
webhook handler. This connects a completed payment back to a specific lead in
§3.1's data model, and already anticipates the `subscription` product value Step
2 will start using.

### 2.4 Page structure

All routes sit cleanly under the Services namespace at
`/{locale}/services/astrodynamics`, with a top-level vanity alias
`/{locale}/astrodynamics` in `routes/web.php`, following the existing
locale-prefix pattern:

| Route | Purpose |
|---|---|
| `/services/astrodynamics` | Hero landing: relationship stage selector + progressive dual-input form **is** the hero — no marketing clutter above it. |
| `/services/astrodynamics/result/{token}` | Free result page: Relational Signature + chemistry teaser, with a visually locked ("blurred/starred") card previewing the full Blueprint, plus the download/email/share actions from §2.2. |
| `/services/astrodynamics/report` | Checkout and post-purchase delivery page for the full personalized Blueprint (in-page + PDF download). |
| `/services/astrodynamics/consultation` | 1:1 Live Relational Consultation booking — surfaced right after report delivery, and reachable standalone from the pricing page. |
| `/services/astrodynamics/pricing` | Transparent side-by-side: Digital Blueprint ($19–29) vs. 1:1 Human Consultation ($79–99). |
| `/services/astrodynamics/guide/{slug}` | 8–12 evergreen SEO guides ("how to communicate with a Water Moon", "dating an Aries Venus", "what a situationship looks like in synastry"), each ending in the interactive tool CTA. |
| Global navigation & footer | Listed under "Services" in the main menu; footer keeps site-wide AstroTherapia links. |

**SEO note:** cluster the `/guide/{slug}` set around the three stages rather than
around signs alone. Sign-by-sign dating content is a crowded, ad-supported,
product-less category (§8.2); *stage* content is not. Three or four guides per
stage plus a handful of placement guides is a better opening set than twelve
sign posts.

### 2.5 Design direction

**Palette** — dark cosmic base consistent with AstroTherapia's night-sky motif,
warmer than the active `theme_solarsystem`'s icy accent:

| Token | Value | Use |
|---|---|---|
| `--ad-bg` | `#0b0714` (near-black, warm-leaning) | Page background |
| `--ad-bg-elevated` | `#150e22` | Cards, panels, input surfaces |
| `--ad-accent-1` | `#ff6b8a` (rose) | Primary CTA, active states, key headlines |
| `--ad-accent-2` | `#ffb37a` (ember) | Secondary accent, gradient end-stop, hover glow |
| `--ad-accent-gradient` | `linear-gradient(135deg, #ff6b8a, #ffb37a)` | Buttons, progress strokes, shareable card background |
| `--ad-text` | `#f3ecf7` | Body text on dark background |
| `--ad-text-muted` | `#b9aec4` | Captions, helper text, input labels |
| `--ad-locked` | `#3a2e46` with blurred overlay | Paywall card preview |

Rose and ember both read comfortably above AA contrast against `--ad-bg` for
large text and buttons; body copy stays on `--ad-text`, never directly on the
accent colors, to keep small-text contrast solid.

**Typography** — serif display face for headlines, maintaining continuity with
the main site's classical feel but warmer/rounder than `theme_solarsystem`'s
Cinzel: **Fraunces** or **Playfair Display**, self-hosted WOFF2 (the same
approach the active theme already uses), 2.25–3rem for H1, 1.5rem for H2,
semi-bold. Paired with a clean, round sans for body and UI copy — **Inter** or
**Nunito** — at 1rem / 1.6 line-height, slightly heavier for buttons and labels.
The pairing should read "practical guide," not "oracle."

**Iconography & imagery** — line-art celestial icons only (crescent moon, Venus
and Mars glyphs, orbital rings) in the accent gradient. **No photographic or
human-figure imagery on the product's own pages.** This is deliberate: it keeps
the product reading as a diagnostic tool rather than a gallery, and keeps its
visual language independent of whatever the social promotion in §2.6 does.

**Motif & motion** — overlapping dual-orbit celestial rings: two orbital rings
representing Chart A and Chart B that visually intersect as inputs are
completed, illuminating nodes of connection ("harmony points" and "friction
points"). SVG `stroke-dashoffset` animation handles the reveal, with a static
fallback for `prefers-reduced-motion`. This motif is also what Step 2's match
visual language (§4.5) grows out of, so it's worth getting right here rather
than treating it as a placeholder. It has a second, useful property: it maps
directly onto the Progressive Precision ladder — each tier lights up more of the
rings, making the upgrade path visible rather than explained.

**Layout** — mobile-first single column (expect most traffic to arrive from
social, on a phone): the stage selector and form are the first thing on the
page, no scrolling required to start. Result and paywall sections use one
consistent card component (16–20px corner radius, soft outer glow in the accent
gradient at low opacity) so the same card style carries into Step 2's quiz
result and ladder-pricing cards without a new design language.

**Paywall** — the free dynamic summary sits inline, followed by a blurred paywall
card showing a *real, personalized* preview rather than an abstract promise:

> *"Because their Moon is in Scorpio and your Moon is in Aquarius, emotional
> withdrawal occurs when… [•••••••• blurred 3 lines ••••••••]. Unlock the full
> Blueprint to see your tailored repair scripts."*

Showing the actual placements in the locked copy is measurably stronger than
generic upsell text (§8.4).

**Hero copy** — variants worth testing rather than committing to one at build
time: *"Understand the pattern before you send the text"* / *"What your charts
say about how you two actually communicate"* / *"Mixed signals have a mechanism.
Here's yours."*

### 2.6 Social & promotion strategy for Step 1

Targeting an **English-speaking international audience** (US, UK, Canada,
Australia, Northern Europe) across TikTok, Instagram Reels, and YouTube Shorts —
the native channel for this audience, and the one where zodiac-relationship
content is already a proven organic-reach category (§8.2).

**Suggestive / thirst-trap imagery is excluded outright**, and it's worth
recording *why*, because it was the predecessor plan's original launch instinct
and the reasoning shouldn't have to be rediscovered:

1. **Platform policy risk.** Instagram and TikTok both restrict "sexually
   suggestive" content well short of explicit; accounts leaning on it routinely
   see reduced organic reach, post removals, or strikes. This compounds into
   Step 2: Meta and TikTok ad review restrict sexualized imagery in *paid* ads,
   which can block the very accounts the tactic builds from ever being used for
   the paid acquisition Step 2 depends on.
2. **Legal / consent exposure.** Using real people's photos without a signed
   model release creates right-of-publicity exposure, especially when the copy
   implies a claim about that person's dating behavior. AI-generated imagery
   sidesteps consent but raises disclosure and credibility problems of its own.
3. **Brand mismatch.** The whole product is "why, not what" — insight-driven
   credibility, reinforced by a visible AstroTherapia crosslink. An
   appearance-first feed undercuts the advice product the Blueprint and
   consultation depend on, and reflects back onto the parent brand.
4. **Funnel-quality mismatch.** Imagery-led content optimizes for views and
   follows, not for tool starts and email captures. It's entirely possible to
   build a large audience that watches and never converts — the exact
   vanity-metric trap the KPIs in §2.7 exist to catch.
5. **Content-sourcing bottleneck.** Sustaining it needs a continuous supply of
   fresh, rights-clear imagery — a real ongoing production cost.

With that premise gone, the calendar leads with formats that are both proven in
this niche and structurally lower-risk:

1. **The "Text Teardown" breakdown** (TikTok / Reels)
   - *Hook*: "If you're texting an Air Moon and they suddenly go cold, don't
     double-text. Here's the actual mechanism."
   - *Content*: compartmentalization vs. genuine disinterest, closing with a
     concrete script.
   - *CTA*: "Run your connection at astrotherapia.com/services/astrodynamics."
2. **Placement interaction POVs** (Reels / TikTok) — relatable, humorous,
   insightful: *"When an Aries Venus (wants an answer now) dates a Taurus Venus
   (needs three business days to process a feeling)."* Drives comments, tags,
   and profile clicks. Cheapest format to produce at volume.
3. **The "group chat" shareable graphic card** (Stories / DMs) — aesthetic
   1080×1920 cards showing relatable relational dynamics, engineered for
   peer-to-peer forwarding: *"sent this to the group chat because their
   Venus/Mars explains everything."* Zero photo-rights risk, and it matches the
   product's own visual identity exactly (§2.5).
4. **Stage-native content** — lead the first month on "situationship" (§1.1).
   It's the term with the most cultural energy and the least astrology
   competition.
5. **UGC / result-reaction content** — short clips of consenting, credited users
   reacting to their free result. Ties promotion directly to the real product,
   doubles as social proof.
6. **Duet / stitch / commentary** on existing zodiac-relationship videos already
   getting reach — cheap, platform-native, low production cost.
7. **Direct collaboration with existing niche creators** (§8.2) — sponsored posts
   or an affiliate arrangement with accounts that already hold this exact
   audience, instead of cold-starting a new account's reach from zero. **This is
   the single fastest path out of the zero-follower problem and should not be
   left to Step 2.**
8. **Interactive native formats** — polls and quiz stickers ("guess which
   placement causes this") — no rights issues, and they rehearse the Step 2
   on-site quiz mechanic in front of an audience before it exists on the site.

**Measurement rule**: judge every format on downstream funnel action — tool
starts and email captures attributable to social — not on views or follows.
That's what §2.7's KPIs are for, and it only works if attribution is tracked per
post from day one (§3.2's `meta` column carries the UTM source).

### 2.7 Effort, timeline & Step 1 KPIs

**Effort**: moderate — route definitions, stage selector, progressive dual-input
form, ephemeris helper (§3.7), rule-based card engine, server-side `dompdf`
rendering, share-image generation, Brevo integration, scoped layout, Stripe
checkout slice, and 8–12 SEO guides.

**Realistic solo-developer timeline: 4–7 weeks to a sellable v1**, assuming Tier
3 ships as Moon-sign content only and the true synastry aspect engine is
deferred per §3.7. *This is a deliberate revision upward from the 3–5 weeks the
predecessor plan estimated: that estimate covered a single-chart, sign-keyed
product. This one reads two charts, five placements, three stages, and three
input tiers — meaningfully more content and more logic. If the aspect engine is
pulled into Step 1, add 2–4 weeks and re-read §3.7 first.* The §3 groundwork is
included in this figure; see §3.8 for how it splits.

**KPIs that decide whether to start Step 2**:

- Free-tool completion rate, **split by precision tier** — if almost nobody
  progresses past Tier 1, the ladder is working as a funnel but the paid product
  needs re-pitching against Tier 1 data alone.
- Email capture rate via Brevo.
- Free → paid Blueprint conversion % (benchmark: 2–4%).
- Blueprint → consultation upsell % (benchmark: 3–5%).
- Blended revenue per visitor — **the actual go/no-go number.**
- Completion and conversion **split by relationship stage** — this tells you
  which stage to build Step 2's content and ad creative around.
- Social traffic: tool starts and email captures attributable to social
  specifically, not views or follows.

A materially positive revenue-per-visitor figure, sustained over a few weeks, is
the trigger to move to §4.

---

## 3. Technical foundation — built once, sized for both steps

Written to be read on its own, later, when actually building Step 1 — the
engineering spec for the pieces that make Step 2 an upgrade rather than a
rebuild, with enough concrete detail (table names, columns, class names) to
implement directly. These decisions cost little extra now and remove real rework
later; one of them (the mailing system) is worth building as shared, app-wide
infrastructure rather than scoped to this section alone.

### 3.1 Lead & submission data model

One table, `astrodynamics_leads`, used by every Step 1 tool and by Step 2's quiz
without a schema migration:

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `name` | string, nullable | |
| `email` | string, indexed | Lead capture value; required to send the free result. |
| `phone` | string, nullable | Optional; keeps the future WhatsApp Business API / SMS door open (§2.2). Unused by anything in Step 1. |
| `stage` | string | `crush`, `situationship`, `partner`. The primary segmentation axis (§1.1). |
| `precision_tier` | string | `sign_only`, `birthdate`, `full_data`. Records how far up the ladder the visitor actually went — a KPI input (§2.7), not just bookkeeping. |
| `locale` | string | Defaults to `en`; supports `ro` (§1.4). |
| `source` | string | Which tool produced the lead: `signature_sheet`, `love_conflict`, `chemistry_teaser`, `rhythm_sheet` in Step 1; `quiz` added in Step 2 — a new value, not a new column. |
| `payload` | json | Whatever was actually submitted (both charts' inputs and stage data; from Step 2, quiz answers). Free-form and keyed by `source`, so Step 2's quiz answers are just a new shape under a new value. |
| `consent_marketing` | boolean, default false | Consent state at capture time — feeds Brevo (§3.3). |
| `created_at` / `updated_at` | timestamps | |

### 3.2 Event log

One table, `astrodynamics_events`, logged from day one even though Step 1 builds
no dashboard on top of it:

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `lead_id` | bigint FK, nullable | Nullable because some events (a page view before submission) precede lead capture. |
| `event_name` | string | Step 1: `tool_viewed`, `tool_completed`, `tier_upgraded`, `report_purchased`, `consultation_booked`. Step 2 adds `quiz_started`, `quiz_completed`, `subscription_started`, ad-attribution events — same table, new names only. |
| `meta` | json | Free-form context per event: stage, precision tier, referrer, UTM source, which post drove the visit. |
| `occurred_at` | timestamp | |

Step 2's funnel analytics (quiz start → complete → email → Blueprint →
consultation → subscription, cost per lead, cost per customer) are new *queries*
against this table, not a new tracking system bolted on retroactively — which is
where funnel analytics usually loses its first month.

### 3.3 Shared mailing system (Brevo)

Built as **app-wide infrastructure**, not scoped to AstroDynamics — the
project's own market analysis
([`docs/project-analysis-2026-07.md`](../docs/project-analysis-2026-07.md))
flags "zero audience capture — no newsletter, lead magnet, or list" as one of
the biggest gaps on the *main* AstroTherapia site. Building one real mailing
capability now, used by both this section and the main site's future
newsletter/Archetype Quiz, avoids ever building a second bespoke system for a
gap that's already known.

**Current state, for context**: outgoing mail today is a single transactional
mailable, `App\Mail\ContactMessage`, sent through the standard cPanel mailbox
(see [`docs/OPERATIONS.md`](../docs/OPERATIONS.md) § "Contact-form mail"). That
is fine for one-off transactional sends and should stay as-is for anything
unrelated to marketing. It is **not** a fit for drip sequences, list
segmentation, unsubscribe handling, or deliverability at volume — a hand-rolled
SMTP mailer handles none of those well (spam-reputation risk, no bounce or
unsubscribe management, no automation).

**Design** — mirrors the shape of the payments plan in §2.3, since the same
TDD / no-network-in-tests constraint applies:

- `App\Mailing\MailingProvider` — interface:
  `subscribe(string $email, array $tags = [], array $attributes = []): void`,
  `sendTransactional(string $email, string $templateId, array $data): void`,
  `unsubscribe(string $email, ?string $tag = null): void`.
- `App\Mailing\FakeMailingProvider` — deterministic, no-network fake, auto-bound
  in the `testing` environment, exactly like `FakePaymentProvider`.
- `App\Mailing\BrevoMailingProvider` — real adapter calling Brevo's REST API via
  Laravel's HTTP client.

**Why Brevo over Mailchimp** — recorded so the locked decision can be defended:

- **Generous free tier** (300 emails/day) covers early volume at zero cost;
  Mailchimp's free tier caps at 500 contacts and gates most automation behind
  paid tiers — a worse fit for a project starting from zero on a budget.
- **One product, one API** for transactional email *and* marketing-automation
  drip sequences *and* list/tag segmentation. Mailchimp splits these; this plan
  needs all three (§4.2).
- **HTTPS-only from Laravel's own HTTP client** — nothing to install on the
  shared cPanel host, no SMTP configuration, fits the no-Node / shared-hosting
  constraint this project runs under exactly.
- **EU-hosted**, which matters for GDPR and for any Romanian-language audience
  the site keeps (§1.4). This argument is weaker than it was for the predecessor
  plan given the international target, but the other three stand alone.

**Shared data model** — one `subscribers` table, **not** namespaced to
AstroDynamics, so the main site's future newsletter/quiz can reuse it directly
instead of getting its own:

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `email` | string, unique | |
| `name` | string, nullable | |
| `phone` | string, nullable | |
| `locale` | string | |
| `tags` | json | e.g. `['astrodynamics_lead', 'stage_situationship']` today; `['journal_newsletter']` for a future main-site feature on the same table. |
| `consent_marketing` | boolean | |
| `consent_at` | timestamp, nullable | |
| `source` | string | Which feature captured this subscriber. |
| `esp_contact_id` | string, nullable | Brevo contact id. |
| `created_at` / `updated_at` | timestamps | |

`astrodynamics_leads.email` (§3.1) writes into this same shared table, tagged
`astrodynamics_lead` plus its stage, rather than maintaining a second list.
**Tagging by stage from day one is what makes Step 2's cohort nurture possible
without re-segmenting a flat list later.**

**Consent**: double opt-in (a confirmation email) for anything feeding an
ongoing marketing sequence — Step 2's nurture emails, any future main-site
newsletter. Single opt-in is fine for the one-off "here's the free result you
just asked for" transactional send tied to something the visitor actively
requested. With an EU-hosted provider and an international audience including
UK/EU visitors, this is not optional polish.

### 3.4 Payments foundation

Covered in full in §2.3 — the `PaymentProvider` interface, the dormant
implementation plan it comes from, its three build-time caveats, and the
`astrodynamics_orders` table. Nothing to add here beyond the pointer: build it
once against that interface, and Step 2's subscription tier extends the same
interface rather than replacing it.

### 3.5 Content authored as modular cards

Template copy is authored in `config/astrodynamics/cards.php` rather than
hardcoded in Blade views:

- Keyed by `element_pair`, `planet_aspect`, and `relationship_stage`.
- Resolved via
  `App\AstroDynamics\Cards::render(string $cardId, string $pairKey, string $stage): string`.
- Reused by Step 2's quiz result mapping and email nurture sequences without
  rewriting a line of copy.

**Content volume is the real cost here, not the code.** The card matrix is where
this plan's effort actually lives: element pairings × stages × placements. Write
Tier 1 and Tier 2 copy first and completely; Tier 3's Moon content can ship
thinner and deepen after launch, since fewer visitors reach it.

### 3.6 Section-scoped theming & routing

`ThemeManager` currently resolves **one active theme for the whole site**
(`SiteSetting.theme`) — there is no mechanism today for a different theme per
route. Teaching it per-route theming is a bigger, riskier change than this
section needs. So instead, AstroDynamics gets its own dedicated Blade layout,
`resources/views/astrodynamics/layout.blade.php`, which inherits the master site
navigation and footer while loading its own hand-authored static CSS at
`public/css/astrodynamics.css` (no build step — consistent with this project's
no-Node approach), defining §2.5's palette as CSS custom properties scoped to
`#astrodynamics-container`.

Where it makes sense, still pull shared *structural* values (spacing scale,
border-radius scale) from `config/tokens.php` rather than duplicating them —
colors and fonts stay local to this section and are not routed through the
site-wide theme system at all. **This is the one genuinely new piece of plumbing
Step 1 requires beyond "add a route,"** and it should be counted as such in the
estimate.

Routes in `routes/web.php`:

```php
Route::group(['prefix' => '{locale}', 'where' => ['locale' => 'en|ro']], function () {
    Route::prefix('services/astrodynamics')->group(function () {
        Route::get('/', [AstroDynamicsController::class, 'index'])->name('astrodynamics.index');
        Route::get('/result/{token}', [AstroDynamicsController::class, 'result'])->name('astrodynamics.result');
        Route::get('/report', [AstroDynamicsController::class, 'report'])->name('astrodynamics.report');
        Route::post('/checkout', [AstroDynamicsController::class, 'checkout'])->name('astrodynamics.checkout');
        Route::get('/consultation', [AstroDynamicsController::class, 'consultation'])->name('astrodynamics.consultation');
        Route::get('/pricing', [AstroDynamicsController::class, 'pricing'])->name('astrodynamics.pricing');
        Route::get('/guide/{slug}', [AstroDynamicsController::class, 'guide'])->name('astrodynamics.guide');
    });

    // Top-level vanity alias
    Route::redirect('astrodynamics', 'services/astrodynamics');
});
```

### 3.7 Ephemeris & synastry — the scope boundary

**This is the section most likely to blow the Step 1 estimate, so the boundary is
drawn explicitly.**

What Step 1 needs is a **placement lookup**, not a chart engine:

- **In scope**: derive Sun, Mercury, Venus, and Mars sign from a birth *date*
  (all four are date-derivable within a day's tolerance; Mercury and Venus need
  a real ephemeris table but no time or place), and Moon sign from date + time,
  falling back to a noon solar chart with an explicit "your Moon may fall in the
  adjacent sign" disclaimer. A pure-PHP ephemeris library or a precomputed
  lookup table both satisfy this; neither needs an external binary, so both fit
  the shared-hosting constraint.
- **In scope**: element and modality pairing logic between the two charts
  (Fire/Earth/Air/Water × cardinal/fixed/mutable), which is what most of §2.2's
  free content and §3.5's card matrix is actually keyed on.
- **In scope**: simple same-sign / element-relationship framing of "your
  Mercuries are trine" style highlights — derivable from sign relationships
  alone (signs 120° apart are trine by sign), without computing exact degrees.
- **Out of scope for Step 1**: a true synastry **aspect** engine — exact
  longitudes, orbs, applying vs. separating aspects, and a 5×5 cross-chart
  aspect matrix with an interpretation paragraph behind each cell. That is a
  different order of work in both code and content, and the predecessor plan
  deliberately excluded it.

**Consequence for the copy**: Tier 3 must be described to users as unlocking
*the Moon and deeper emotional-safety reading*, not "precise mathematical
synastry aspects." Promising exact aspects and shipping sign-based ones is the
kind of gap that produces refund requests on a $19–29 product. If exact aspects
are wanted, they belong in the **paid Blueprint** at Step 2, priced accordingly,
and added to §4.6 — not smuggled into a free tier at Step 1.

### 3.8 What this adds to the Step 1 timeline

The items above (§3.1–3.3, 3.5–3.7; §3.4 is already counted in §2.3) add real
but bounded time beyond a throwaway MVP — mainly the Brevo integration (§3.3),
the section-scoped layout (§3.6), the ephemeris helper (§3.7), and the card
content matrix (§3.5, which is writing time more than engineering time).

**Hold scope to exactly what's specified here.** The entire value of this section
is *not* having to redo the data model, the email platform, or the payment
integration once Step 2 starts. Over-building any one piece beyond what's
written above defeats that purpose just as thoroughly as skipping it would — and
this plan is more susceptible to that than its predecessor was, because the
three-tier input ladder and three-stage content matrix both invite "while I'm
here" expansion.

---

## 4. Step 2 — Growth engine

**Trigger to start:** Step 1's KPIs (§2.7) show sustained positive revenue per
visitor over several weeks. Step 2 is a scaling decision, not a starting point —
building it before Step 1 validates demand risks a lot of infrastructure with
nothing proven to justify it.

### 4.1 What's additive vs. genuinely new

Because of §3, several Step 2 pieces are additive rather than rebuilds:

- **Additive**: the lead model, event log, Brevo adapter, and Stripe provider all
  already exist — Step 2 extends each of them (new `source` values, new event
  names, new tags, one new interface method).
- **Genuinely new**: the branching diagnostic quiz engine, the multi-step Brevo
  nurture sequence content, the recurring subscription product and its billing
  lifecycle (`mode: subscription`), and ad-platform pixel integration.

### 4.2 Free offerings, restructured as a ladder

1. **Stage 1 — The Relational Diagnostic Quiz** (new primary landing): 5–7
   questions about the connection's dynamics, communication frequency, and
   friction patterns, plus signs if known → an instant **Dynamic Archetype**
   result, with email capture at peak curiosity, *before* any birth-data
   friction. This matches the ~40%-vs-10–20% quiz-conversion gap documented in
   §8.2, and it is the same mechanic the main site needs for its own Archetype
   Quiz (§1.2) — build the engine once, use it twice.
2. **Stage 2 — Chart tools via nurture**: the four Step 1 tools (§2.2), now paced
   across a 4-part Brevo sequence instead of shown all at once — each email a
   reason to return to the site.
3. **Stage 3 — Recurring Relational Weather**: a monthly transit email
   ("upcoming communication windows and friction periods for your dynamic")
   keeping non-converters warm for a later re-pitch instead of the funnel being
   one-shot.

### 4.3 Paid offerings, restructured as a ladder

- **AstroDynamics Blueprint ($19–29)** and **1:1 Consultation ($79–99)** —
  unchanged from Step 1, now the first two rungs of a visible ladder rather than
  two flat, separate offers.
- **New — AstroDynamics Pass ($9–14/month)**: ongoing transit-based relationship
  nudges — Mercury retrograde alerts scoped to *this* connection, Venus transits
  touching either chart, conflict-warning windows, and date/communication timing
  cues. Anchored against The Pattern's $14.99/mo (§8.2), priced below it because
  the scope is narrower. **This is the piece that turns one-time buyers into
  recurring revenue and is the main reason Step 2's ceiling is higher than Step
  1's.**
- Deep synastry aspect work (§3.7) belongs here if it's built at all — as paid
  Blueprint depth, not free-tier scope.
- All three price points A/B tested continuously once traffic supports it.

### 4.4 Page structure additions

Builds on §2.4's routes rather than replacing them:

| Route | Purpose |
|---|---|
| `/services/astrodynamics/quiz` | New primary landing for paid and social traffic: one question at a time, visible progress bar. |
| `/services/astrodynamics/quiz/result/{token}` | Dynamic Archetype result + one free insight; email capture point; routes into the Stage-2 nurture sequence. |
| `/services/astrodynamics/pricing` | Evolves from Step 1's two-offer page into a real three-tier ladder: Blueprint → consultation → Pass. |
| `/services/astrodynamics/membership` | Subscription start / manage / cancel. |
| `/services/astrodynamics/guide/{slug}` | Existing SEO guides, CTA updated to route into `/quiz` rather than the direct-entry form. |
| Templated ad-landing variants | The same `/quiz` flow with swappable hero copy per campaign — a templating pattern for paid-traffic tests, not literal new pages. |
| `/services/astrodynamics` (original hero form) | Kept as a secondary, lower-friction entry for organic and direct visitors who'd rather skip the quiz. |

### 4.5 Design evolution

Same token system as §2.5 — an evolution, not a redesign:

- **Quiz UI**: single-question-per-screen flow with a progress bar, ending in an
  animated result reveal built to be screenshotted.
- **Ladder pricing page**: Blueprint / consultation / Pass as a three-card
  progression, reusing §2.5's card component.
- **Match visual language**: the dual-orbit rings from §2.5 fully realized — both
  orbits complete, intersection nodes lit and labeled as harmony and friction
  points, used on the quiz result and inside the Blueprint itself.
- **Shareable result card**: a story-shaped image of the Dynamic Archetype
  result, reusing §2.2's image-generation approach.

### 4.6 Technical implementation

- **Quiz engine**: a small, genuinely reusable question/branching/scoring system
  (config-driven questions → scoring → result mapping), built reusable from the
  start because multiple quiz variants will be tested and because the main
  site's Archetype Quiz will use the same engine (§1.2).
- **Event instrumentation**: extends §3.2's table with new event names — same
  table, no new system.
- **Email automation**: real drip-sequence logic on Brevo, cohorted by stage and
  archetype tag. **The single biggest genuinely new piece of infrastructure at
  this step.**
- **Subscription billing**: adds `createSubscriptionCheckout()` and
  subscription-lifecycle webhooks (renewal, cancellation, dunning) to the
  `PaymentProvider` interface from §2.3/§3.4.
- **Ad-platform readiness**: Meta/TikTok/Google conversion pixels wired into the
  event stream, plus a consent-banner and privacy-policy review before any paid
  campaign goes live — and a check that none of the organic content built in
  Step 1 would block ad approval when reused as paid creative.

### 4.7 Content, SEO & distribution strategy

- Paid social (TikTok/Meta) becomes a primary channel with ongoing budget,
  creative tested against `/quiz` specifically rather than the section homepage.
- The Step 1 SEO guide set now routes into the quiz instead of the direct-entry
  form.
- Creator seeding: once the result card is share-ready, put it in front of the
  astrology/relationship creators identified in §8.2 — the same collaboration
  tactic recommended in §2.6, now with budget behind it.
- **Relationship-advice is a scrutinized ad category.** Expect review friction on
  Meta and TikTok even with compliant creative; keep the non-fatalistic,
  no-compatibility-score framing in ad copy too, not just on-site.

### 4.8 Conversion optimization & testing culture

- Cohort nurture emails by **stage and Dynamic Archetype**, so follow-up copy
  speaks to the visitor's actual situation rather than broadcasting.
- Track **Blueprint → consultation → Pass** as its own funnel stage, separate
  from top-of-funnel quiz metrics — opt-in rate is the cheapest step in the
  funnel and the one least correlated with revenue (§8.2).
- Treat price points, quiz questions, email timing, and paywall copy as ongoing
  experiments once volume supports it.

### 4.9 Effort, timeline & Step 2 KPIs

**Effort**: large on top of Step 1 — quiz engine, expanded instrumentation,
email automation, subscription billing. Realistic timeline to a fully
instrumented v2: **10–16 weeks** from the decision to start, more if paid
ad-creative testing is in scope. Meaningfully less than building it from zero,
because of §3.

**KPIs**: full-funnel conversion at every stage (quiz start → complete → email →
Blueprint → consultation → Pass), cost per acquired lead and per paying customer
once ads run, subscription churn and retention, and — critically —
**contribution margin per customer after ad spend.**

---

## 5. Combined pros & cons

**Pros**

- Fully aligns with AstroTherapia's therapeutic brand without risking clinical
  credibility — it *is* the Relationships pillar, delivered faster (§1.2).
- Universal market: reaches all genders, orientations, and relationship
  configurations instead of the narrow, skeptical demographic the predecessor
  plan targeted (§1.1).
- Cheapest available way to find out whether this is a real business before
  committing to the expensive parts — §2.7's KPIs are a genuine go/no-go gate.
- No throwaway work: §3's decisions mean a "go" doesn't require rebuilding the
  data model, email system, or payment integration while the product is live and
  earning.
- Consolidated domain authority: all social traffic, backlinks, and press boost
  `astrotherapia.com` directly rather than a second domain from zero.
- Natural funnel into high-ticket 1:1 consultations — the existing business.
- Shared infrastructure pays off twice: Stripe and Brevo permanently upgrade the
  core platform, and the mailing system closes a gap the main site's own analysis
  already flagged as one of its biggest.
- The quiz engine built at Step 2 is the same one the main site needs for its
  flagship Archetype Quiz — one build, two products.
- Stage-based segmentation creates a repeat-purchase mechanic below the
  subscription tier, which the predecessor plan lacked entirely.

**Cons**

- Requires visual discipline to keep an interactive digital tool feeling cohesive
  next to reflective consultation pages — the scoped skin (§3.6) manages this
  but doesn't eliminate it.
- **Real scope-discipline risk**: §3's "prepare to upgrade" work, the three-tier
  input ladder, and the three-stage content matrix all invite quiet expansion.
  Build what §3 specifies and stop (§3.8).
- The card content matrix (§3.5) is a genuine writing workload, not just code —
  and it's the part most likely to be underestimated because it doesn't look
  like engineering.
- Section-scoped theming (§3.6) is new plumbing for this app — small but real
  work beyond "just add a route."
- Step 2 introduces ongoing marketing-ops overhead: short-form video production
  at cadence, ad-policy compliance in a scrutinized category, email-sequence
  upkeep.
- Cold-start problem on social is real: this plan launches with zero followers
  into a niche that already has established creators. §2.6's collaboration
  tactic is the mitigation, and it costs money.
- Some AstroTherapia visitors may still find an automated relational tool
  tonally different from grief and identity content — much less so than the
  predecessor plan, but not zero (see §6 and §7).

---

## 6. Risks & conditions of application

This plan is the right call when:

- The priority is answering "will anyone pay for this" within a few weeks, with
  a pre-agreed path to scale it if the answer is yes — not committing to a full
  build up front.
- The team wants to validate automated digital products alongside 1:1 services
  on `astrotherapia.com`, and accepts that the two share a domain provided the
  section stays visually and structurally distinct.
- Brand safety and clinical dignity must be preserved — this plan is
  structurally safer than any imagery-led or compatibility-score alternative.
- English-speaking international markets are the primary growth target.
- The team building it is the same one maintaining the Laravel app today — no
  appetite for a second deploy target at this stage.
- There is genuine intent to gate Step 2's investment behind Step 1's KPIs
  rather than treating the two-step structure as a formality.

It is **not** the right call, without modification, if:

- Brand-safety distance from AstroTherapia turns out to be a hard requirement
  rather than a manageable risk — see §7.
- There is no capacity to produce the card content matrix (§3.5) and 8–12 SEO
  guides. Without content, this is a well-engineered empty shell; the code is
  the smaller half of the build.
- Short-form video cadence can't be sustained. The funnel's top depends on it;
  SEO alone will not fill it at launch speed.

---

## 7. Alternative: a standalone spin-off

Not pursued now, mainly for cost and speed reasons — described here in full so
it can be picked up later without needing anything beyond this document.

**The idea**: instead of living inside the existing app, AstroDynamics becomes
its own product at its own address — a subdomain, or an entirely separate domain
if more distance is wanted. This is proven technically feasible in this project
already: dev and prod run as two independent subdomains under one cPanel
account, each with its own app directory and database — the same pattern applies
here as a third instance. The connection to AstroTherapia becomes a quiet footer
credit rather than a visible section of the main site.

**What it would change**: a second Laravel app (or a lighter static marketing
layer with a thin API for placement lookup and checkout) deployed to its own
subdomain and database; its own visual identity built from scratch with full
creative freedom; its own SEO and social footprint built from zero rather than
inheriting AstroTherapia's domain authority; and its own Stripe and Brevo
integrations, since it's a separate app rather than an extension of this one.
Add the naming friction from §1.3 — the exact-match domain is likely unavailable
or expensive, and the bare-term SERP is contested by aerospace content.

**What it would keep**: the same core product (free tools → email → paid
Blueprint → consultation, and eventually the same quiz/subscription ladder), and
— if built with the discipline of §3 — a data model, event log, and payments
interface general enough that most of that groundwork transfers rather than
restarting.

**What it would lose, and this is the strongest argument against it**: the
archetype tie-in in §1.2. A spun-off AstroDynamics can't be the Relationships
pillar's commercial expression; it becomes a generic relationship-astrology tool
competing on its own merits against Co-Star and The Pattern, without the
therapeutic positioning that differentiates it.

**When to reconsider**:

- Real feedback after Step 1 launches — from consultation clients, from
  search/social comments, from the owner's own read of the room — shows the
  shared-domain positioning is an actual problem rather than a theoretical one.
- Step 2's paid acquisition would clearly benefit from a fully separate brand
  voice and social presence, unconstrained by AstroTherapia's tone.
- Traffic and revenue outgrow what makes sense as a section of the main app, and
  standing it up independently becomes attractive in its own right.

**Trade-off to weigh if this path is taken**: maximum brand safety and creative
freedom, in exchange for the slowest path to first revenue (no inherited traffic
or trust), the loss of the pillar tie-in, and the highest ongoing overhead of any
option here — a second app to build, deploy, secure, and maintain indefinitely.

---

## 8. OVERVIEW

*Background research this plan is built on. Kept here as an appendix so the plan
above stays readable without losing the evidence behind it.*

### 8.1 The idea, restated

AstroDynamics helps anyone navigating a romantic connection understand *why* it
moves the way it does, by reading the cross-chart dynamics between two people
and translating them into communication insight — in the same why-not-what
register as AstroTherapia. Free tools with keepable, shareable artifacts pull
people in; a paid personalized Blueprint and a 1:1 relational consultation
monetize. Tone: psychologically grounded, non-fatalistic, never a compatibility
score, at both steps.

### 8.2 Competitive landscape

**Direct comparables — synastry / chart-comparison sellers**

Etsy synastry-report sellers are a real, proven micro-market: 1,000+ listings
under "synastry chart" / "synastry report." Formats range from an 8-page
auto-generated PDF up to 20+ page hand-interpreted reports and printed hardcover
books. Pricing spans roughly **$15–30** for a software-generated report up to
**$150–375** for a fully custom written compatibility book (one example: a $29
licensed report plus $150 of personalized interpretation, sold as a $375 gift).

**Gap this exposes**: Etsy sellers are gift- and curiosity-framed, descriptive,
and passive — generic about *what to do with the information*. Nobody packages
synastry as stage-specific, actionable communication guidance. That is
AstroDynamics' wedge and the core of both steps above.

**App / subscription comparables**

- **Co-Star** — freemium: free daily hyper-personalized notifications and
  compatibility snapshots drive downloads and daily habit; **Co-Star+** unlocks
  extended compatibility reports, deeper transit breakdowns, and an AI Q&A
  feature. The free tier is genuinely useful, which is what earns the upgrade —
  the same principle behind §2.2's "the free artifact is real" rule.
- **The Pattern** — free tier gives daily personalized read-outs and "Bonds"
  (compatibility with anyone added); paid **Go Deeper+** runs from **$14.99/mo**
  (or $29.99 per 3 months) and unlocks deeper relationship content and its
  proprietary match-insight algorithm. This is the direct pricing anchor for
  §4.3's Pass tier.

**Gap**: both apps present compatibility as a *static metric you read*, not
advice you act on before a conversation. Neither coaches behavior, and neither
segments by relationship stage. That is this plan's differentiator at both
steps.

**Content / SEO comparables**

AstroChartus runs a full "Dating Guide by Zodiac Sign" content hub — sign-by-sign
"how to date, communicate with, and attract" guides. SunSigns.org, YourTango, and
Astrologykart run similar evergreen "how to attract a [sign]" articles.

**Gap**: generic-by-sign, ad-supported, with no product behind it — proof of real
search demand with almost nothing monetized on top of it. This is the query set
§2.4's `/guide/{slug}` articles target. **Note the strategic refinement**: that
sign-keyed space is crowded, while *stage*-keyed content ("situationship
astrology", "why they went cold", "recurring fight patterns") is not. Lead there
(§1.1).

**Adjacent monetization comparable — relationship coaching**

1:1 dating and relationship coaching runs **$75–170 per 45–60 minute session** at
the mainstream tier (roughly $85 sliding-scale up to $170 for doctoral-level
coaches), with matchmaking-style sessions around $200/hr and elite coaching far
higher. This is the anchor behind the **$79–99 / 45min** consultation price:
priced like coaching, differentiated by the chart-based "why."

**Distribution comparable — short-form astrology creators**

Zodiac-relationship content is a proven organic-reach category: creators such as
"Your Zodiac Boyfriend" and "Your Mom's Horoscope" (366K followers) post
relationship and attraction content with strong engagement, mixing humor with
actionable advice — built on personality and insight, not imagery. This is the
evidence base behind §2.6's format recommendations *and* behind the
creator-collaboration tactic: these accounts already hold the exact audience this
plan needs, and reaching it through them is faster than cold-starting.

This audience lives on TikTok / Reels / Shorts — not search, and not Facebook
(where AstroTherapia's existing Romanian audience skews, per
[`docs/project-analysis-2026-07.md`](../docs/project-analysis-2026-07.md)). The
two audiences barely overlap, which is an argument for treating AstroDynamics'
social presence as its own channel even while the product lives on the main
domain.

**What quizzes / lead magnets actually do**

Quiz-format lead magnets convert **~40%** of starters into leads on average, vs.
**10–20%** for a static ebook or checklist, because the quiz self-segments the
visitor while collecting the email. This single data point is why Step 2 makes
the quiz the primary landing (§4.2) rather than Step 1's direct-entry form.

Caveat kept in view throughout: opt-in rate is "the cheapest step in the funnel
and the one least correlated with revenue." Every KPI section here (§2.7, §4.9)
tracks the *whole* funnel, not top-of-funnel capture.

### 8.3 Free-tool idea bank

The four Step 1 tools plus the backlog they came from, each tagged with the step
it belongs to. This is the content roadmap for Step 2's nurture sequence — it
exists so Step 2 doesn't start from a blank page:

| # | Idea | Step |
|---|---|---|
| 1 | Personalized Relational Signature Sheet (dual Sun/Moon) | 1 |
| 2 | Love & Conflict Dynamic Guide (Venus/Mars by element pairing) | 1 |
| 3 | Chemistry & Blind Spots teaser | 1 |
| 4 | Conversational Rhythm Cheat Sheet (by stage) | 1 |
| 5 | "What to say instead" script generator, by their Mercury sign | 2 (nurture) |
| 6 | Date/plan-together suggestions by element pairing | 2 (nurture) |
| 7 | Green-flag / friction-flag primer by placement | 2 (nurture) |
| 8 | Moon-phase timing tool ("a good window for this conversation: Thursday") | 2 (nurture / novelty hook) |
| 9 | Relational Diagnostic Quiz → Dynamic Archetype | 2 (primary landing) |
| 10 | Shareable "your Venus language" result card | 2 (share loop) |
| 11 | Stage-transition guide ("your situationship just became something — now what") | 2 (repeat-purchase hook) |
| 12 | Repair-language mini-guide by placement | 2 (retention) |
| 13 | "Are they pulling away or processing?" by element primer | 2 (nurture) |
| 14 | Monthly Relational Weather email | 2 (recurring free touch, §4.2 Stage 3) |

Every tool, at either step, ends in the same two CTAs: get the full personalized
Blueprint, and book a relational consultation.

### 8.4 Reach & conversion tactics (both steps, to different degrees)

- **SEO**: target the validated "how to communicate with / date / understand a
  [placement]" query set, plus the uncontested stage-keyed set (§1.1), landing
  visitors on a page with an actual product rather than an article.
- **Short-form video** is the native channel for this audience — §2.6 carries the
  format breakdown.
- **Quiz-first landing** for anything running paid traffic, matching the 40%
  opt-in benchmark — exactly why Step 2 restructures around the quiz.
- **Paywall placement**: one genuinely useful free insight, then gate the
  specific, actionable layer — mirrors the Etsy and Co-Star pattern of "free is
  real, paid is deeper."
- **Personalization at the paywall**: show the actual placements in the locked
  preview copy (§2.5) — measurably stronger than generic upsell text.
- **Built-in share loop**: every free result is a keepable, forwardable artifact
  by design (§2.2) — each share is a free impression, and the shareable card is
  the cheapest acquisition channel in the plan.
- **Upsell timing**: offer the consultation *after* the Blueprint is delivered,
  not before — "you've read the pattern, now let's plan the actual conversation"
  is a natural second sale.
- **Stage transitions as re-engagement**: someone who bought a Crush Blueprint
  three months ago is a warm lead for a Situationship or Committed one. Tag it
  at capture (§3.3) and mail it later.

### 8.5 Sources

[Etsy: custom compatibility book](https://www.etsy.com/listing/1775938186/custom-romantic-compatibility-book-with) ·
[Etsy: synastry charts market](https://www.etsy.com/market/synastry_charts) ·
[Etsy: 20+ page synastry report](https://www.etsy.com/listing/1500091791/romantic-compatibility-synastry) ·
[Etsy: relationships synastry report](https://www.etsy.com/listing/647077536/relationships-synastry-report) ·
[Gumroad: synastry report](https://realmofdivinewisdom.gumroad.com/l/astrologysynastry) ·
[Apptunix: astrology app like Co-Star](https://www.apptunix.com/blog/how-to-develop-an-astrology-app-like-co-star/) ·
[Comfygen: astrology app monetization models](https://comfygenprivatelimited7.wordpress.com/2025/05/21/how-astrology-apps-make-money-monetization-models/) ·
[Bustle: The Pattern app review](https://www.bustle.com/life/pattern-app-review-features-price) ·
[Aurae: The Pattern app review 2026](https://www.auraeastrology.com/blog/the-pattern-app-review-2026-an-astrologers-honest-opinion) ·
[Co-Star Astrology](https://www.costarastrology.com/) ·
[The Pattern App](https://www.thepattern.com/) ·
[AstroChartus: dating guide by zodiac sign](https://www.astrochartus.com/dating) ·
[SunSigns.org: dating by zodiac sign](https://www.sunsigns.org/dating-women-by-zodiac-sign/) ·
[YourTango: how to attract by zodiac sign](https://www.yourtango.com/2018316527/how-to-attract-a-man-by-zodiac-sign-astrology) ·
[Astrologykart: attract your crush by zodiac sign](https://astrologykart.com/astrology-blog/how-to-attract-your-crush-using-their-zodiac-sign) ·
[GrowingSelf: dating coach cost](https://www.growingself.com/dating-coach-cost/) ·
[Evan Marc Katz: price of a dating coach](https://www.evanmarckatz.com/blog/dating-tips-advice/the-price-of-true-love-how-much-is-a-dating-coach-and-is-it-worth-it) ·
[Craft of Charisma: 1-on-1 dating coaching](https://www.craftofcharisma.com/product/1-on-1-dating-coaching/) ·
[stormy.ai: astrology TikTok creators](https://stormy.ai/discover/tiktok/has-content-about-astrology-zodiac-signs-or-horoscopes-has) ·
[IZEA: top astrology influencers on TikTok](https://izea.com/resources/astrology-influencers-tiktok/) ·
[Interact: quiz conversion rate report](https://www.tryinteract.com/blog/quiz-conversion-rate-report/) ·
[Digital Applied: lead magnet conversion benchmarks](https://www.digitalapplied.com/blog/lead-magnet-conversion-benchmarks-2026-b2b-data-reference)

**Repository references** (not web sources — the other documents this plan
depends on):

- `docs/superpowers/plans/2026-06-25-reusable-site-template-php-payments.md` —
  the dormant payments plan (§2.3, §3.4).
- [`docs/concept-synthesis.md`](../docs/concept-synthesis.md) — the canonical
  brand/content record; the archetype system in §1.2 comes from here.
- [`docs/project-analysis-2026-07.md`](../docs/project-analysis-2026-07.md) —
  the 2026-07 project and market analysis; the lead-capture gap and Archetype
  Quiz priority in §3.3 and §1.2 come from here.
- [`docs/OPERATIONS.md`](../docs/OPERATIONS.md) — current mail setup (§3.3).
- [`OLD_concept-cosmic-playbook.md`](OLD_concept-cosmic-playbook.md) — the
  superseded predecessor plan, retained for history. Read it only for the
  reasoning behind decisions this plan inherited; its audience, routes
  (`/cosmic-play`), table names (`cosmic_play_*`), and promotion strategy are
  all replaced by this document.

*This section previously also carried its own separate follow-up queue
(`SPARK-POINTS-QUEUE.md`), removed when this plan superseded its predecessor:
every row in it was either a decision now restated with fuller reasoning in
"Decisions already locked" above, or — in the case of its one genuinely open
row, the suggestive-imagery promotion question — an issue dissolved rather than
answered by the audience change in §1.1. The open-questions table near the top
of this document replaces it.*
