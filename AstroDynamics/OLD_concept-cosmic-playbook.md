# Cosmic Playbook — Plan Final

*A two-step plan for a new AstroTherapia section: Step 1 launches lean, built
deliberately so Step 2 (a fuller growth engine) is an upgrade, not a
rebuild. A standalone spin-off (its own subdomain/brand) is described in §7
as a documented alternative, not pursued now. This document is
self-contained — written so it carries everything needed to implement it
later, without needing this conversation for context. Nothing here has
touched the codebase; it's still a document for review.*

## Decisions already locked

Quick-reference for anyone (including a future session) picking this up
without the conversation that produced it. Everything below was decided
deliberately, not defaulted to — treat changing any of these as a real
decision, not a drive-by edit:

- **Two-step structure**: build the lean version first (§2), start the
  growth-engine version (§4) only once Step 1's KPIs (§2.7) justify it —
  never build Step 2 first.
- **Content tone**: flirty/tasteful, PG-13, never explicit — on every page,
  free or paid, at both steps (§1). This is the test *all* content
  (product copy and promotional content alike, §2.6) has to pass.
- **Placement**: inside the existing AstroTherapia Laravel app, at
  `/cosmic-play`, not a new subdomain or separate app — see §7 for the
  conditions under which that would change.
- **Payments provider**: Stripe, via the already-specified but unbuilt
  `PaymentProvider` design at `docs/superpowers/plans/2026-06-25-reusable-site-template-php-payments.md`
  (§2.3) — build against that interface, don't design a new one.
- **Mailing provider**: Brevo, chosen over Mailchimp, built as shared
  app-wide infrastructure rather than something scoped only to this
  section (§3.3) — deliberately closes a gap already flagged on the main
  site, not just this page's own need.
- **Promotion**: do not lead with suggestive/appealing-women imagery as
  the primary content pillar for the Instagram/TikTok launch (§2.6) — real
  platform-policy, legal, and brand-mismatch risk outweighs the attention
  it buys; lead with creator-voice and meme/text-card formats instead.
- **Standalone spin-off** (own subdomain/brand, full creative and brand
  distance): documented and ready in §7, deliberately **not** the starting
  point — revisit only under the conditions listed there.

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

Cosmic Playbook helps a man understand a specific woman he's interested in
by reading her chart — Sun, Moon, Venus, Mars — and turning that reading
into concrete communication advice: what to say, how to read her signals,
how to plan a date that fits her. It's framed as "AstroTherapia's
Relationships pillar, made practical": same why-not-what philosophy, same
non-fatalistic stance — *"the chart shows the pattern, you choose the
response"* — but with a lighter, more direct, second-person voice ("here's
why she does that — and what to say back") instead of the main site's
therapeutic register.

**Content tone stays flirty and tasteful throughout, free and paid, at both
steps — never explicit.** This keeps the whole plan Stripe/Google-Ads
eligible and low-risk to sit next to AstroTherapia's core brand, and removes
the need for age-gating or an adult payment processor at either step. Keep
this line in mind through §2.6 — it's the test any promotional content also
has to pass, not just the product pages.

It lives inside the existing AstroTherapia Laravel app at `/cosmic-play`
(or `/{locale}/cosmic-play`), visually distinct from the active
`theme_solarsystem` theme via a section-scoped skin rather than a whole new
theme package — see §2.5 and §3.6.

---

## 2. Step 1 — Lean launch

**Goal:** ship the smallest real version of the product, reusing the
existing app and deploy pipeline, to answer one question cheaply — *will
men actually pay for chart-based advice about a specific woman?* — while
laying the groundwork in §3 so a "yes" doesn't mean starting over.

### 2.1 Target user & funnel

A man, generally 20s–30s, with a specific woman in mind — a match, a
coworker, someone he just met. Funnel: **free tool → email capture →
immediate free insight → paid full report → consultation upsell** a few
days later by email.

### 2.2 Free offerings — content, formats & sharing

Four tools, all buildable as **rule-based template content** (if sign X,
show paragraph Y) plus a small ephemeris helper to derive Sun/Moon/Venus/
Mars from a birthdate — no AI, no chart-calculation engine beyond that.

**Every one of these free tools produces an actual takeaway document or
image the visitor can keep and forward — not just an on-screen result that
disappears when the tab closes.** This is deliberate: the artifact itself
is the distribution mechanism (someone else sees it and comes to the site
to get their own). Each tool's result page ends in three concrete actions:

- **Download** — the file itself, saved to the visitor's device.
- **Email it to me** — sends the same file/image, via the mailing system
  in §3.3, to an address the visitor provides (this is also the lead-
  capture moment — the email address collected here is the lead).
- **Send via WhatsApp** — a `https://wa.me/?text=...` share link
  pre-filled with a short caption and the hosted URL of the file/image
  (WhatsApp's own share mechanism works from a link, not a raw file
  attachment from a website, so the file needs a real public URL to point
  to — already true for the PDF/image files described below). If a phone
  number is collected instead of (or alongside) an email, a direct
  WhatsApp Business API send is possible later, but that's extra
  integration cost — treat it as an optional Step 2+ enhancement, not
  something Step 1 needs.

The four tools, with their concrete output format:

1. **Personalized Sun/Moon behavior sheet** — the founding idea: enter her
   birth date (and time, if known, with a "we'll use noon and flag it as
   approximate" fallback), get a plain-language read of how she processes
   emotion (Moon) and expresses identity (Sun). **Output: a one-page PDF**,
   branded with the Cosmic Playbook look (§2.5), generated server-side from
   a print-styled Blade view (a PDF library such as `dompdf` — pure-PHP, no
   external binary, fits the no-Node/shared-hosting constraint — renders it
   on request).
2. **Planets-in-signs behavior guide** — a static, well-written reference:
   what Venus in each sign wants romantically, what Mars in each sign wants
   physically/assertively — written once, reused everywhere. **Output: a
   downloadable reference PDF** (evergreen, not personalized — same
   generation approach as #1, cached instead of regenerated per request).
3. **"Spark points" synastry teaser** — enter both his and her birth data,
   get 1–2 free compatibility highlights ("your Moons are trine — she'll
   feel safe with you fast"); the rest is gated behind the paid report.
   **Output: an in-page result plus a shareable square/story image**
   (roughly 1080×1080 or 1080×1920, matching Instagram/TikTok story
   dimensions) carrying the one free highlight as short, punchy text over
   the Cosmic Playbook visual motif — generated by rendering a small
   Blade/SVG template to PNG server-side. This is the tool most worth
   optimizing for the share flow above, since the image is designed to be
   reposted, not just kept.
4. **Venus/Mars attraction-language cheat sheet** — "what she finds
   romantic vs. what turns her on, by sign," a short, shareable reference.
   **Output: the same shareable-image format as #3** (short enough to work
   as a card, unlike the longer PDFs in #1/#2).

### 2.3 Paid offerings, pricing, and the payments foundation

- **Full personalized cross-chart report** (his chart × her chart):
  **$19–35 one-time.** Delivered as an in-page report plus a downloadable
  PDF (same generation approach as the free sheet, longer and deeper).
- **Relationship/compatibility consultation**: **$69–99 for 45 minutes**,
  booked after report delivery while intent is highest.

**On the "dormant" Stripe payments design** — this term refers to a
specific, already-written implementation plan that exists in this
repository but was **never built**: `docs/superpowers/plans/2026-06-25-reusable-site-template-php-payments.md`.
It's a step-by-step, test-driven implementation plan (not a vague proposal
— it includes exact file paths, exact class/interface signatures, and
failing tests to write first, per this repo's TDD rule) for a
provider-agnostic payments capability. Concretely, it specifies:

- `App\Payments\PaymentProvider` — an interface with four methods:
  `createCheckout(array $line, string $successUrl, string $cancelUrl): CheckoutResult`,
  `verifyWebhook(string $payload, string $signature): bool`,
  `handleWebhookEvent(string $payload): WebhookResult`, and
  `getStatus(string $reference): string`.
- `App\Payments\CheckoutResult` / `App\Payments\WebhookResult` — small
  readonly DTOs the interface returns.
- `App\Models\PaymentSettings` — a singleton DB row holding **non-secret**
  public config (provider name, currency, success/cancel URLs, enabled
  payment methods), editable from an admin page at `/admin/payments`. The
  actual Stripe API secrets (`STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`)
  live only in `.env` — never in the database or the admin UI.
- `App\Payments\FakePaymentProvider` — a deterministic, no-network fake,
  auto-bound whenever the app runs in the `testing` environment, so the
  test suite never calls Stripe. This is what makes the design usable
  under this repo's TDD rule (`php artisan test` must not touch the
  network) without extra work.
- `App\Payments\StripePaymentProvider` — the real adapter, built on the
  official `stripe/stripe-php` SDK, implementing **one-time Stripe
  Checkout Sessions** (`mode: payment`) and signed-webhook verification via
  `Stripe\Webhook::constructEvent`.
- Routes: `POST /{locale}/checkout` (creates a Checkout Session, redirects
  to Stripe's hosted page) and a public, CSRF-exempt, signature-verified
  `POST /payments/webhook`.

**How Step 1 should use it**: implement this plan's Tasks 1–4 close to as
written — the interface, DTOs and settings model; the fake provider and
container binding; the Stripe adapter; the checkout and webhook routes —
scoped to Cosmic Playbook's two products (the report and the consultation,
each just a one-off `createCheckout()` call with its own `name`/`amount`
line item). Task 5 (the `/admin/payments` settings page) is optional for a
true v1 but cheap to include since it's already fully specified.

**Two things to check, not assume, when actually building this**: the plan
document's own header says "Laravel 11.x" while the app is now on
**Laravel 13** (`composer.json`) — the pattern itself isn't
Laravel-version-sensitive, but verify the exact shape of `bootstrap/app.php`
and `bootstrap/providers.php` in the current codebase before pasting the
plan's snippets verbatim. And the plan is written as **"Plan 3 of 4,"**
declaring two prerequisites — a `SiteSetting` model + locale route group,
and an `admin` middleware + admin dashboard — both of which **already exist**
in this codebase today (`app/Models/SiteSetting.php`, the `{locale}` route
group, and a working `/admin/*` area with CRUD for posts), so only this
payments slice actually needs building.

**What it does *not* cover, and why that's fine for Step 1**: the plan
only implements one-time Checkout (`mode: payment`) — no subscription
billing. That's intentional here: Step 1 has no recurring product. Step
2's subscription tier (§4.3) will need a follow-on addition — a
`createSubscriptionCheckout()`-style method on the same `PaymentProvider`
interface (Stripe's `mode: subscription`) plus subscription-lifecycle
webhook handling (renewed / cancelled / payment failed) — which is new
work at Step 2, not a gap being left in Step 1.

Recommend also creating one small table not in the dormant plan itself —
`cosmic_play_orders` (id, lead_id FK, product enum: `report`/
`consultation`/`subscription`, amount, currency, provider_reference —
the Stripe session or subscription id, status: pending/paid/refunded/
cancelled, timestamps) — updated by the webhook handler. This is what
connects a completed payment back to a specific lead in §3.1's data model,
and it already anticipates the `subscription` product value Step 2 will
start using.

### 2.4 Page structure

All routes below sit under `/cosmic-play` (or `/{locale}/cosmic-play`),
following the existing locale-prefix pattern in `routes/web.php`:

| Route | Purpose |
|---|---|
| `/cosmic-play` | Hero landing: headline + the two-field free-tool form (his input / her sign or birth data) *is* the hero — no marketing copy above it. |
| `/cosmic-play/result/{token}` | Free result page: Sun/Moon sheet + spark-points teaser, with a visually locked ("blurred/starred") card previewing what the paid report unlocks, plus the download/email/WhatsApp share actions from §2.2. |
| `/cosmic-play/report` | Checkout for the full cross-chart report; post-purchase delivery page (in-page + PDF download). |
| `/cosmic-play/consultation` | Consultation upsell/booking — surfaced right after report delivery, and reachable standalone from the pricing page. |
| `/cosmic-play/pricing` | Simple two-offer pricing page: report vs. consultation, side by side. |
| `/cosmic-play/guide/{slug}` | 8–12 evergreen SEO articles ("how to talk to a [sign] woman"), each ending in the free-tool CTA. |
| Footer (site-wide within the section) | One small "part of AstroTherapia" wordmark linking back — visible, not dominant. |

### 2.5 Design direction

**Palette** — a night-sky base consistent with AstroTherapia's cosmos
motif, but warmer than `theme_solarsystem`'s icy accent:

| Token | Value | Use |
|---|---|---|
| `--cp-bg` | `#0b0714` (near-black, warm-leaning) | Page background |
| `--cp-bg-elevated` | `#150e22` | Cards, panels |
| `--cp-accent-1` | `#ff6b8a` (rose) | Primary CTA, active states, key headlines |
| `--cp-accent-2` | `#ffb37a` (ember) | Secondary accent, gradient end-stop, hover glow |
| `--cp-accent-gradient` | `linear-gradient(135deg, #ff6b8a, #ffb37a)` | Buttons, progress/loading strokes, the shareable-card background |
| `--cp-text` | `#f3ecf7` | Body text on dark background |
| `--cp-text-muted` | `#b9aec4` | Captions, helper text |
| `--cp-locked` | `#3a2e46` with a blurred/starred overlay on the gated text | Paywall card background |

Rose and ember both read comfortably above AA contrast against `--cp-bg`
for large text/buttons; body copy should stay on `--cp-text`, not directly
on the accent colors, to keep small-text contrast solid.

**Typography** — keep a serif display face for headlines, for continuity
with the main site's classical feel, but pick something warmer/rounder
than `theme_solarsystem`'s Cinzel — **Fraunces** or **Playfair Display**
(both self-hostable as WOFF2, same approach the active theme already uses)
at 2.25–3rem for H1, 1.5rem for H2, semi-bold weight. Pair with a rounder,
friendlier sans for body and UI copy — **Nunito** or **Inter** — at
1rem/1.6 line-height for body text, and a slightly heavier weight for
buttons/labels. The pairing should read "practical guide," not "oracle" —
softer than the main site's tone without abandoning it.

**Iconography & imagery** — line-art celestial icons only on the product
pages themselves (crescent moon, Venus glyph, a small two-star
constellation mark) in the accent gradient, no photographic or
human-figure imagery on the site's own pages. This is a deliberate choice:
it keeps the *product* reading as a tool, not a gallery, and keeps its
visual language separate from whatever imagery choices the social
promotion in §2.6 ends up using — the product doesn't need to justify or
match promotional imagery decisions later.

**Motif & motion** — a two-point constellation line: two stars/charts
connected by a line that redraws itself (an SVG `stroke-dashoffset`
animation) as the free-tool form fills in, and reused as the loading state
between form submission and result. Keep motion subtle and respect
`prefers-reduced-motion` (fall back to a static reveal). This same
"two points connecting" idea is what Step 2's match-report visual language
(§4.5) grows out of, so it's worth getting right here rather than treating
it as a placeholder.

**Layout** — mobile-first single column (expect most traffic to arrive
from social, on a phone): the hero form is the first thing on the page,
no scrolling required to start; result and paywall sections use a
consistent card component (16–20px corner radius, soft outer glow in the
accent gradient at low opacity) so the same card style can be reused for
Step 2's quiz result and ladder-pricing cards without a new design
language.

**Paywall** — the free "spark points" teaser sits inline, followed by the
visually locked card described in the palette table above ("what to say
next") — the value of unlocking is shown concretely (blurred real text),
not described abstractly ("unlock more!").

**Hero copy** — two or three headline variants worth testing rather than
committing to one at build time: *"Understand her, before you say the
wrong thing"* / *"What her chart says about how to reach her"* /
*"Stop guessing what she wants — read her chart first."*

### 2.6 Social & promotion strategy for Step 1

The current working idea for launch promotion is to run an Instagram and a
TikTok account posting periodically, pairing an attractive,
suggestively-dressed woman with a sign-based hook — e.g. *"Dating an Aries
woman? Did you know that…"* — on the premise that appealing imagery of
women is the strongest way to stop the scroll of the target audience (men
interested in a specific woman).

**Worth flagging immediately**: this sits right at the edge of, and can
easily cross, the flirty-not-explicit line locked in §1 for the product
itself — and platform policy, not this document's taste, is what actually
enforces where that edge is. That tension is the core of the evaluation
below.

**Why the instinct isn't wrong** — attractive imagery genuinely is a
proven attention-grabbing pattern in dating/relationship content, it's
cheap and fast to produce at volume (stock or AI-generated imagery plus a
text overlay), and it's visually literal to the product's premise
("compatibility with a woman"). It shouldn't be dismissed outright.

**Why it's risky as the primary or only tactic**:

1. **Platform policy risk.** Instagram and TikTok both restrict
   "sexually suggestive" content well short of explicit — accounts that
   lean on it routinely see reduced organic reach ("shadow-limiting"),
   post removals, or account strikes; TikTok in particular polices
   age-appropriateness aggressively. This risk compounds directly with
   Step 2 (§4.7): Meta and TikTok ad review both restrict sexualized
   imagery in *paid* ads, which could block exactly the accounts this
   tactic builds from ever being used for the paid-acquisition strategy
   Step 2 depends on.
2. **Legal/consent exposure.** Using real women's photos — scraped,
   pulled from social media, or stock without a license that actually
   covers this use — without a signed model release creates real
   right-of-publicity and defamation exposure, especially since the copy
   implies a claim about that specific-looking person's dating behavior.
   AI-generated imagery sidesteps the consent problem but raises a
   different one: audiences and platforms are increasingly wary of
   undisclosed AI-generated "people," and using it without disclosure
   risks a credibility backlash if noticed. Any imagery used must be
   either (a) properly licensed stock with a commercial license that
   covers this exact use, or (b) clearly disclosed as illustrative/
   AI-generated, not presented as a real identifiable individual.
3. **Brand mismatch with the plan's own positioning.** The whole product
   is built on "why, not what" — insight-driven credibility, reinforced
   by the visible AstroTherapia crosslink in the footer (§2.4). An account
   that reads as an appearance-first feed undercuts the "this is a real
   advice product" positioning the paid report and consultation depend
   on, and risks reflecting back on the AstroTherapia brand the footer
   links to — a real escalation of the brand-risk already named in §6.
4. **Funnel-quality mismatch.** Imagery-led content optimizes for views
   and follows, not for the action this funnel actually needs (starting
   the free tool, submitting an email). It's entirely possible to build a
   large following that watches for the photos and never touches the
   product — the exact vanity-metric trap the KPIs in §2.7 and §4.9 are
   built to catch, but only if downstream conversion is actually tracked
   per post, not just views.
5. **Content-sourcing bottleneck.** Sustaining "periodic" posting needs a
   continuous supply of fresh, appealing, rights-clear imagery — a real,
   ongoing production cost whichever sourcing route is used, licensed
   stock or AI generation.

**Alternative and complementary formats** — recommend a mixed content
calendar rather than one tactic, leading with the two below since they're
already proven in this exact niche (see §8.2's creator research):

1. **Creator-voice video** (faceless or on-camera) — the strongest proven
   pattern here: accounts like "Your Zodiac Boyfriend" and "Your Mom's
   Horoscope" built real followings on personality, humor, and actionable
   tips, not imagery. Lower legal risk (no third-party photo rights to
   manage), and builds audience loyalty tied to a voice/personality rather
   than to interchangeable photos.
2. **Astrology-native meme/text-card format** — aesthetic graphic design
   posts (chart wheels, night-sky visuals, bold text overlay: *"POV:
   you're texting a Scorpio moon"*) — zero photo-rights risk, matches the
   product's own visual identity (§2.5's motif exactly), and is a
   currently-thriving format in the astrology-TikTok niche.
3. **UGC/result-reaction content** — short clips of consenting, credited
   users reacting to their free tool result — ties promotion directly to
   the real product, doubles as social proof, and carries far less legal
   risk than imagery of uninvolved people.
4. **Duet/stitch/commentary** on existing zodiac-dating videos already
   getting reach in the niche — cheap, platform-native, low production
   cost.
5. **Direct collaboration with existing niche creators** (the accounts
   surfaced in §8.2) — sponsored posts or an affiliate arrangement with
   accounts that already have this exact audience, instead of cold-
   starting a new account's reach from zero.
6. **Interactive native formats** — polls/quizzes as Instagram/TikTok
   stickers ("which sign is she — guess before the reveal") — no imagery-
   rights issue, and it rehearses the Step 2 on-site quiz mechanic (§4.2)
   in front of an audience before it exists on the site.

**Recommendation**: don't make suggestive imagery the primary content
pillar. If it's used at all, keep it to a minority of the calendar,
sourced only from properly licensed stock or clearly-disclosed
AI-generated imagery, held to the same PG-13 line as the product itself
(never more suggestive than the page it's promoting), and judged on
downstream funnel action — tool starts and email captures — not on views
or follows alone. Lead the calendar instead with the creator-voice and
meme/text-card formats above: both are proven in this exact niche, both
carry materially lower platform, legal, and brand risk, and both are just
as fast and cheap to produce.

### 2.7 Effort, timeline & Step 1 KPIs

**Effort**: small — new routes, four template-driven free tools with PDF/
image generation, a scoped visual skin, a minimal one-time-Checkout slice,
8–12 SEO articles, and the initial social content calendar. Realistic
solo-developer timeline: **3–5 weeks** to a sellable v1 (excludes the §3
groundwork, which adds a modest amount of time — see §3.7).

**KPIs that decide whether to start Step 2**: free-tool completion rate,
email capture rate, free→paid report conversion %, report→consultation
conversion %, revenue per visitor, and — for the social channel — quiz/
tool starts and email captures attributable to social traffic specifically
(not just views/follows). A materially-positive revenue-per-visitor
number, sustained over a few weeks, is the trigger to move to §4.

---

## 3. Technical foundation — built once, sized for both steps

This section is written to be read on its own, later, when actually
building Step 1 — it's the engineering spec for the pieces that make Step
2 an upgrade rather than a rebuild, with enough concrete detail (table
names, columns, class names) to implement directly. Four decisions cost
little extra in Step 1 and remove real rework later, plus one piece (the
mailing system) is worth building as shared, app-wide infrastructure
rather than something scoped only to this section.

### 3.1 Lead & submission data model

One table, `cosmic_play_leads`, used by every free tool in Step 1 and by
the quiz in Step 2 — deliberately generic so Step 2 needs no schema
migration to add quiz support:

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `name` | string, nullable | |
| `email` | string, indexed | The lead-capture value; required to send the free-tool result. |
| `phone` | string, nullable | Optional; enables a future WhatsApp Business API send (§2.2) or SMS follow-up — not used by anything in Step 1. |
| `locale` | string | `en` / `ro`. |
| `source` | string | Which tool produced this lead: `sun_moon_sheet`, `planets_guide`, `spark_points`, `venus_mars` in Step 1; `quiz` added in Step 2 — no new column needed, just a new value. |
| `payload` | json | Whatever was actually submitted (his/her birth data, or — from Step 2 — quiz answers). Because this is a free-form JSON blob keyed by `source`, Step 2's quiz answers are just a new shape under a new `source` value. |
| `consent_marketing` | boolean, default false | Consent state at capture time — feeds the mailing system in §3.3. |
| `created_at` / `updated_at` | timestamps | |

### 3.2 Event log

One table, `cosmic_play_events`, logged from day one even though Step 1
builds no dashboard on top of it:

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `lead_id` | bigint FK, nullable | Nullable because some events (e.g. a page view before the form is submitted) precede lead capture. |
| `event_name` | string | Step 1: `tool_viewed`, `tool_completed`, `report_purchased`, `consultation_booked`. Step 2 adds `quiz_started`, `quiz_completed`, `subscription_started`, ad-click attribution events — same table, new names only. |
| `meta` | json | Free-form context per event (which tool, which product, referrer/UTM source). |
| `occurred_at` | timestamp | |

Step 2's funnel analytics (quiz start → complete → email → report →
consultation → subscription, cost per lead, cost per customer) are new
*queries* against this table, not a new tracking system bolted on
retroactively — which is where funnel-analytics work usually loses its
first month.

### 3.3 Shared mailing/email system

This is worth building as **app-wide infrastructure**, not something
scoped only to Cosmic Playbook — the project's own market-analysis
research (§8.2 / [[reference_project_analysis_report]]) already flags
"zero lead capture — no quiz/newsletter/list" as the single biggest gap on
the *main* AstroTherapia site. Building one real mailing capability now,
used by both the new section and (eventually) the main site's own future
newsletter/quiz feature, avoids ever building a second bespoke system for
that already-known gap.

**Current state, for context**: outgoing mail today is a single
transactional mailable, `App\Mail\ContactMessage`, sent via the standard
cPanel mailbox (see `docs/OPERATIONS.md` §"Contact-form mail"). That's
fine for one-off transactional sends and should stay as-is for anything
unrelated to marketing sequences — it is **not** a fit for drip sequences,
list segmentation, unsubscribe handling, or deliverability at any real
volume, all of which a hand-rolled SMTP mailer handles poorly (spam-
reputation risk, no bounce/unsubscribe management, no automation UI).

**Recommended design** — mirror the shape of the payments plan in §2.3,
since the same TDD/no-network-in-tests constraint applies:

- `App\Mailing\MailingProvider` — an interface with something like:
  `subscribe(string $email, array $tags = [], array $attributes = []): void`,
  `sendTransactional(string $email, string $templateId, array $data): void`,
  `unsubscribe(string $email, ?string $tag = null): void`.
- `App\Mailing\FakeMailingProvider` — a no-network fake, bound under the
  `testing` environment, exactly like `FakePaymentProvider`.
- A real adapter behind the interface, calling a chosen provider's HTTP
  API (no SMTP, no server-side package installs beyond a small HTTP
  client call — fits the no-Node/shared-cPanel-hosting constraint exactly).

**Provider recommendation: Brevo** (formerly Sendinblue) over Mailchimp,
because: a generous free tier (300 emails/day) covers early volume without
cost; it's EU-hosted, which matters for a Romanian/EU audience and GDPR;
it combines transactional email *and* marketing-automation drip sequences
*and* list/tag segmentation in one product and one API, rather than
needing separate tools; and it's reachable entirely over HTTPS from
Laravel's own HTTP client, needing nothing installed on the shared-hosting
server. Mailchimp is the better-known alternative but its free tier caps
at 500 contacts and gates most automation behind paid tiers — a worse fit
for a project starting from zero on a budget.

**Shared data model** — one `subscribers` table, **not** namespaced to
Cosmic Playbook, so the main site's future newsletter/quiz feature (the
flagged gap) can reuse it directly instead of getting its own:

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `email` | string, unique | |
| `name` | string, nullable | |
| `phone` | string, nullable | |
| `locale` | string | |
| `tags` | json | e.g. `['cosmic_play_lead']` today; `['journal_newsletter']` for a future main-site feature reusing the same table. |
| `consent_marketing` | boolean | |
| `consent_at` | timestamp, nullable | |
| `source` | string | Which feature captured this subscriber. |
| `esp_contact_id` | string, nullable | The provider-side id, if sync is one-way from the app. |
| `created_at` / `updated_at` | timestamps | |

`cosmic_play_leads.email` (§3.1) writes into this same shared table (tagged
`cosmic_play_lead`) rather than maintaining a second list.

**Consent**: recommend double opt-in (a confirmation email) for anything
feeding an ongoing marketing sequence — Step 2's nurture emails, any future
main-site newsletter — but single opt-in is fine for a one-off "here's the
free result you just asked for" transactional send tied to something the
visitor just requested.

### 3.4 Payments foundation

Covered in full in §2.3 — the `PaymentProvider` interface, the dormant
implementation plan it comes from, and the `cosmic_play_orders` table.
Nothing to add here beyond a pointer: build it once against that
interface, and Step 2's subscription tier extends the same interface
rather than replacing it.

### 3.5 Content authored as modular cards

Write the four free tools' template content (§2.2) as config-driven
"cards" rather than copy hardcoded into Blade views — e.g.
`config/cosmic_play/cards.php` returning an array keyed by card id, each
with a prompt and a sign-keyed template map, resolved through a small
`App\CosmicPlay\Cards` service (`Cards::render(string $cardId, string
$sign): string`). Step 2's quiz result-mapping and nurture-email content
(§4.2) reuse these exact cards as their content source instead of the copy
being rewritten from scratch.

### 3.6 Section-scoped theming

The current `ThemeManager` resolves one active theme for the whole site
(`SiteSetting.theme`) — there's no mechanism today for a different theme
per route. Rather than extending `ThemeManager` itself (a bigger, riskier
change), give Cosmic Playbook its own dedicated Blade layout,
`resources/views/cosmic-play/layout.blade.php`, loading its own static CSS
file under `public/` (hand-authored, no build step needed — consistent
with this project's no-Node-tooling approach) that defines the token set in
§2.5's palette table as CSS custom properties, scoped to that layout only.
Where it makes sense, still pull shared, purely structural values (spacing
scale, border-radius scale) from `config/tokens.php` rather than
duplicating them — but colors/fonts are local to this section, not routed
through the site-wide theme system at all. This is the one genuinely new
piece of plumbing Step 1 requires beyond "add a route."

### 3.7 What this adds to the Step 1 timeline

The four items above (§3.1–3.3, 3.5–3.6; §3.4 is already counted in §2.3)
add real but modest time to Step 1's build beyond a true throwaway MVP —
mainly the mailing-provider integration (§3.3) and the section-scoped
layout (§3.6). Hold scope to exactly what's specified here: the value of
this section is entirely in *not* having to redo the data model, the email
platform, or the payment integration once Step 2 starts — over-building
any one piece beyond what's written above defeats that purpose just as
much as skipping it would.

---

## 4. Step 2 — Growth engine

**Trigger to start:** Step 1's KPIs (§2.7) show sustained positive revenue
per visitor. Step 2 is a scaling decision, not a starting point — building
it before Step 1 validates demand risks a lot of infrastructure (§3) with
nothing proven to justify it.

### 4.1 What's additive vs. genuinely new

Because of §3, several Step 2 pieces are additive, not rebuilds:
- Lead capture, event tracking, the mailing platform, and the payments
  interface **already exist** — Step 2 extends each of them.
- **Genuinely new**: the quiz engine (branching question/scoring logic),
  the multi-step nurture sequence content, the subscription product and
  its billing flow, and ad-platform pixel integration.

### 4.2 Free offerings, restructured as a ladder

1. **Stage 1 — the quiz** (new primary landing): 5–7 questions about him
   and the situation ("how long have you known her," "what's the vibe
   been") plus her Sun sign if known → a "situation type" result with one
   specific, useful tip. Email capture happens here, at peak curiosity,
   before any birth-data-entry friction — matching the ~40%-vs-10–20%
   quiz-conversion gap documented in §8.2.
2. **Stage 2 — the chart tools**: the same four Step 1 tools (§2.2), now
   paced across a 3–5 email nurture sequence instead of shown all at once —
   each email is a reason to return to the site.
3. **Stage 3 — recurring free touch**: a monthly "who's in your orbit"
   email (a transit note + a tip) keeps non-converters warm for a later
   re-pitch instead of the funnel being one-shot.

### 4.3 Paid offerings, restructured as a ladder

- **Report** ($19–35) and **consultation** ($69–99): unchanged from Step
  1, now the first two rungs of a visible ladder rather than two flat,
  separate offers.
- **New — a recurring subscription** (**$7–12/month**): ongoing
  transit-based nudges about a specific relationship in progress —
  "monthly reads on your situationship." Modeled on The Pattern's Go
  Deeper+ pricing (from $14.99/mo, see §8.2) but priced lower since the
  scope is narrower. This is the piece that turns one-time buyers into
  recurring revenue and is the main reason Step 2's ceiling is higher than
  Step 1's.
- All three price points should be A/B tested continuously once traffic
  supports it.

### 4.4 Page structure additions

Builds on §2.4's routes rather than replacing them:

| Route | Purpose |
|---|---|
| `/cosmic-play/quiz` | New primary landing for paid/social traffic: single-question-at-a-time flow with a visible progress bar. |
| `/cosmic-play/quiz/result/{token}` | Segmented "situation type" result + one free tip; email capture point; routes into the Stage-2 nurture sequence. |
| `/cosmic-play/pricing` | Evolves from Step 1's two-offer page into a real three-tier ladder: report → consultation → subscription. |
| `/cosmic-play/membership` (or `/subscribe`) | Subscription start/manage/cancel. |
| `/cosmic-play/guide/{slug}` | Existing SEO articles, CTA updated to route into `/quiz` instead of the direct-entry form. |
| Templated ad-landing variants | Same `/quiz` flow, swappable hero copy per campaign — not literal new pages, a templating pattern for paid-traffic tests. |
| `/cosmic-play` (original hero form) | Kept as a secondary, lower-friction entry point for organic/direct visitors who'd rather skip the quiz. |

### 4.5 Design evolution

Same token/palette system as §2.5 — this is an evolution, not a redesign:
- **Quiz UI**: clean, single-question-per-screen flow with a progress bar,
  ending in an animated "your result" reveal built to be screenshotted.
- **Ladder pricing page**: report / consultation / subscription shown as a
  clear three-card progression, reusing the same card component from
  §2.5.
- **Match-report visual language**: two overlapping chart wheels (his and
  hers), the overlap zone lighting up as "spark points" — a natural
  extension of Step 1's constellation-line motif, used on both the quiz
  result and the report itself.
- **Shareable result card**: a story/post-shaped image of the quiz result,
  reusing the same image-generation approach as §2.2's spark-points card.

### 4.6 Technical implementation

- **Quiz engine**: a small, reusable question/branching-logic system
  (config-driven questions + scoring → result mapping), built genuinely
  reusable since multiple quiz variants will likely be tested over time.
- **Event instrumentation**: extends §3.2's table with new event names —
  same table, no new system.
- **Email automation**: real drip-sequence logic on the mailing provider
  chosen in §3.3 — the single biggest genuinely new piece of
  infrastructure at this step.
- **Subscription billing**: adds the subscription method (renewal,
  cancellation, dunning webhooks) to the `PaymentProvider` interface from
  §2.3/§3.4.
- **Ad-platform readiness**: conversion-tracking pixels (Meta/TikTok/
  Google) wired into the event stream, plus a consent-banner/privacy-policy
  review before any paid campaign goes live — and a check, per §2.6, that
  none of the organic social content built in Step 1 would block ad
  approval once it's reused for paid placement.

### 4.7 Content, SEO & distribution strategy

- Paid social (TikTok/Meta) becomes a primary channel, budgeted for
  ongoing spend, with creative tested against the `/quiz` landing
  specifically rather than the homepage.
- The same SEO article set from Step 1 now routes into the quiz instead of
  the direct-entry form.
- Influencer/creator seeding: once the quiz result card is share-ready,
  send it to a handful of astrology/dating TikTok creators (§8.2) to drive
  quiz starts directly — the same creator-collaboration idea already
  recommended as a lower-risk tactic in §2.6, now with budget behind it.

### 4.8 Conversion optimization & testing culture

- Cohort nurture emails by quiz "situation type" so follow-up copy speaks
  to his specific scenario rather than a generic broadcast.
- Track **report → consultation → subscription** as its own funnel stage,
  separate from top-of-funnel quiz metrics — opt-in rate is the metric
  least correlated with revenue (§8.2).
- Treat price points, quiz questions, email timing, and paywall copy as
  ongoing experiments once volume supports it.

### 4.9 Effort, timeline & Step 2 KPIs

**Effort**: large on top of Step 1 — quiz engine, expanded instrumentation,
email automation, and subscription billing. Realistic timeline to a fully
instrumented v2: **10–16 weeks** from the decision to start, more if paid
ad-creative testing is in scope. Meaningfully less than building Step 2
from zero, because of §3.

**KPIs**: full-funnel conversion at every stage (quiz start → complete →
email → report → consultation → subscription), cost per acquired lead and
per paying customer once ads run, subscription churn/retention, and —
critically — **contribution margin per customer after ad spend**.

---

## 5. Combined pros & cons

**Pros**
- Cheapest possible way to find out if this is a real business before
  committing to the expensive parts (§2.7's KPIs are a genuine go/no-go
  gate, not a formality).
- No throwaway work: §3's decisions mean a "go" doesn't require rebuilding
  the data model, the email system, or the payment integration while the
  product is already live and earning.
- The mailing-system investment (§3.3) pays off twice — once for this
  section's own nurture needs, once for the main AstroTherapia site's own
  long-flagged missing newsletter/lead-capture capability.
- Stays inside the existing app and deploy pipeline at both steps — no
  second hosting bill, no second app to patch and monitor (the overhead
  the standalone spin-off in §7 would carry).
- Step 2's subscription tier gives this plan a real recurring-revenue
  ceiling that a report-and-consultation-only product doesn't have.

**Cons**
- Shares a domain with a therapeutic self-development brand at both
  steps — some AstroTherapia visitors may find "how to talk to her"
  content jarring next to grief/career/identity content, even in a
  visually distinct section. Mitigated by the scoped skin and minimal
  crosslinking, not eliminated (see §6 and §7).
- Real discipline risk: §3's "prepare to upgrade" work can quietly turn a
  supposedly lean Step 1 into a bigger build than intended if scope isn't
  held to exactly what's specified — build those items and stop there.
- Step 2 introduces real, ongoing marketing-ops load (ad spend, creative
  testing, email-sequence upkeep, ad-policy compliance for dating/
  relationship-advice categories) that Step 1 doesn't require.
- Section-scoped theming (§3.6) is genuinely new plumbing for this app —
  small but real engineering work beyond "just add a route."
- The organic-social promotion instinct floated for Step 1 (§2.6) carries
  its own platform-policy and legal risk if executed as originally
  proposed — worth resolving deliberately before content goes live, not
  discovered after a strike or takedown.

## 6. Risks & conditions of application

This combined plan is the right call when:
- The priority is answering "will anyone pay for this" within a few
  weeks, with a clear, pre-agreed path to scale it if the answer is yes —
  not committing to a full build up front.
- There's no strong objection to Cosmic Playbook living on the same
  domain as AstroTherapia, provided it stays visually and structurally
  distinct (scoped skin, separate route namespace, minimal crosslink).
- The team building it is the same one maintaining the Laravel app today —
  no appetite for a second deploy target at this stage.
- There's a genuine intention to revisit Step 2's investment (ads, email
  ops, subscription billing) only once Step 1's KPIs justify it.

It is **not** the right call, without modification, if brand-safety
distance from AstroTherapia turns out to be a hard requirement rather than
a manageable risk — see §7.

## 7. Alternative: a standalone spin-off

Not pursued now, mainly for cost and speed reasons — described here in
full so it can be picked up later without needing anything beyond this
document.

**The idea**: instead of living inside the existing app, Cosmic Playbook
becomes its own product at its own address —
`cosmicplaybook.astrotherapia.com`, or an entirely separate domain if even
more distance is wanted. This is proven technically feasible in this
project already: dev and prod already run as two independent subdomains
under one cPanel account, each with its own app directory and its own
database — the same pattern would apply here, just as a third instance.
The connection to AstroTherapia becomes a quiet footer credit ("from the
makers of AstroTherapia") rather than a visible section of the main site.

**What it would change**: a second, small Laravel app (or a much lighter
static/flat-file marketing layer with a thin API for chart calculation and
checkout) deployed to its own subdomain and database; its own visual
identity built from scratch rather than a skin on the existing theme
system (full creative freedom — no obligation to stay anywhere near
`theme_solarsystem`'s palette or motifs); its own SEO and social footprint
built from zero rather than inheriting AstroTherapia's existing traffic
and domain authority; and its own Stripe/mailing integrations, since it's
a separate app rather than an extension of this one.

**What it would keep**: the same core product (free tools → email → paid
report → consultation, and eventually the same Step 2 quiz/subscription
ladder), and — if built with the same discipline as §3 — a data model,
event log, and payments interface general enough that most of that
groundwork would transfer to a spun-off app rather than being a full
restart.

**When to reconsider this path**:
- Real feedback after this plan's Step 1 launches — from AstroTherapia
  clients, from search/social comments, from the owner's own read of the
  room — shows the shared-domain positioning is an actual problem, not
  just a theoretical one.
- Step 2's paid-acquisition strategy would clearly benefit from a fully
  separate brand voice and social presence, unconstrained by
  AstroTherapia's tone.
- Traffic and revenue outgrow what makes sense as a section of the main
  app, and standing it up as its own product — potentially spinnable off
  independently — becomes attractive in its own right.

**Trade-off to weigh if this path is taken**: maximum brand safety and
creative freedom, in exchange for the slowest path to first revenue (no
inherited traffic or trust to build on) and the highest ongoing overhead
of any option here (a second app to build, deploy, secure, and maintain
indefinitely).

---

## 8. OVERVIEW

*Background research this plan is built on. Kept here as an appendix so
the plan above stays readable without losing the evidence behind it.*

### 8.1 The idea, restated

Cosmic Playbook helps a man understand and approach a specific woman more
skillfully by reading her chart — "why she responds the way she does," in
the same why-not-what register as AstroTherapia. Free tools pull people in;
paid, personalized cross-chart advice and relationship/compatibility
consultations monetize. Tone: flirty and tasteful throughout, free and
paid — never explicit.

### 8.2 Competitive landscape (live web research, Sep 2026)

**Direct comparables — chart-comparison / synastry sellers**

Etsy synastry-report sellers are a real, proven micro-market: 1,000+
listings under "synastry chart" / "synastry report." Formats range from an
8-page auto-generated PDF up to 20+ page hand-interpreted reports and
printed hardcover books. Observed pricing spans roughly **$15–30** for a
software-generated report up to **$150–375** for a fully custom, written
compatibility book (one example: a $29 licensed report plus $150 of
personalized interpretation, sold as a $375 romantic gift). ([Etsy listing](https://www.etsy.com/listing/1775938186/custom-romantic-compatibility-book-with), [Etsy market: synastry charts](https://www.etsy.com/market/synastry_charts), [20+ page synastry report](https://www.etsy.com/listing/1500091791/romantic-compatibility-synastry), [Relationships synastry report](https://www.etsy.com/listing/647077536/relationships-synastry-report), [Gumroad synastry report](https://realmofdivinewisdom.gumroad.com/l/astrologysynastry))

**Gap this exposes**: Etsy sellers are gift/curiosity-framed and generic
about *what to do with the information*. Nobody packages synastry as
actionable communication coaching for a specific man pursuing a specific
woman — that's Cosmic Playbook's wedge, and the core of both steps above.

**App/subscription comparables**

- **Co-Star** — freemium: free daily hyper-personalized notifications and
  compatibility snapshots drive downloads and daily habit; **Co-Star+**
  unlocks extended compatibility reports, deeper transit breakdowns, and
  an AI Q&A feature ("The Void"). The free tier is genuinely useful, which
  is what earns the eventual upgrade. ([apptunix overview](https://www.apptunix.com/blog/how-to-develop-an-astrology-app-like-co-star/), [monetization models](https://comfygenprivatelimited7.wordpress.com/2025/05/21/how-astrology-apps-make-money-monetization-models/))
- **The Pattern** — free tier gives daily personalized read-outs and
  "Bonds" (compatibility with anyone added); the paid **Go Deeper+**
  subscription (from **$14.99/mo**, or **$29.99 per 3 months**) unlocks
  deeper relationship content and its proprietary match-insight algorithm
  ("Connect") — the direct pricing anchor for Step 2's subscription tier
  (§4.3). ([Bustle review](https://www.bustle.com/life/pattern-app-review-features-price), [Aurae review](https://www.auraeastrology.com/blog/the-pattern-app-review-2026-an-astrologers-honest-opinion))

**Gap**: both apps are self-focused — compatibility as a passive stat you
read, not advice you act on before a date. Neither coaches *behavior*,
which is this plan's differentiator at both steps.

**Content/SEO comparables**

AstroChartus runs a full "Dating Guide by Zodiac Sign" content hub —
sign-by-sign "how to date, communicate with, and attract" guides. SunSigns.
org, YourTango, and Astrologykart run similar evergreen "how to attract a
[sign]" articles. ([AstroChartus dating guide](https://www.astrochartus.com/dating), [SunSigns.org](https://www.sunsigns.org/dating-women-by-zodiac-sign/), [YourTango](https://www.yourtango.com/2018316527/how-to-attract-a-man-by-zodiac-sign-astrology), [Astrologykart](https://astrologykart.com/astrology-blog/how-to-attract-your-crush-using-their-zodiac-sign))

**Gap**: generic-by-sign content, ad-supported, no product behind it — the
exact SEO query set both steps' `/guide/{slug}` articles target (§2.4,
§4.7), proof of real search demand with almost no monetization built on
top of it.

**Adjacent monetization comparable — dating coaching**

1:1 dating coaching runs **$75–170 per 45–60 minute session** at the
mainstream tier (roughly $85 sliding-scale up to $170 for doctoral-level
coaches), with matchmaking-style sessions around $200/hr and elite coaching
far higher. ([GrowingSelf](https://www.growingself.com/dating-coach-cost/), [Evan Marc Katz](https://www.evanmarckatz.com/blog/dating-tips-advice/the-price-of-true-love-how-much-is-a-dating-coach-and-is-it-worth-it), [Craft of Charisma](https://www.craftofcharisma.com/product/1-on-1-dating-coaching/))
This is the anchor behind the $69–99/45min consultation price in both
steps — priced like coaching, differentiated by the chart-based "why."

**Distribution comparable — TikTok astrology creators**

Zodiac-dating content is a proven organic-reach category: creators like
"Your Zodiac Boyfriend" and "Your Mom's Horoscope" (366K followers) post
relationship/attraction-by-sign content with strong engagement, mixing
humor with actionable transit advice — the evidence base behind §2.6's
recommendation to lead with creator-voice and meme/text-card formats
rather than imagery. ([stormy.ai roundup](https://stormy.ai/discover/tiktok/has-content-about-astrology-zodiac-signs-or-horoscopes-has), [IZEA](https://izea.com/resources/astrology-influencers-tiktok/))
This audience lives on TikTok/Reels/Shorts, not search or Facebook (where
AstroTherapia's core audience skews per [[reference_project_analysis_report]]).

**What quizzes/lead magnets actually do**

Quiz-format lead magnets convert **~40%** of starters into leads on
average, vs. **10–20%** for a static ebook/checklist, because the quiz
self-segments the visitor while collecting the email. ([Interact benchmark report](https://www.tryinteract.com/blog/quiz-conversion-rate-report/))
This is the single data point behind Step 2's decision to make the quiz the
primary landing (§4.2), rather than Step 1's direct-entry form. Caveat kept
in view throughout: opt-in rate is "the cheapest step in the funnel and the
one least correlated with revenue" — every KPI section in this plan (§2.7,
§4.9) tracks the *whole* funnel, not just top-of-funnel capture. ([Digital Applied benchmark note](https://www.digitalapplied.com/blog/lead-magnet-conversion-benchmarks-2026-b2b-data-reference))

### 8.3 Free-tool idea bank

Two ideas came from the original brief; the rest were generated for this
plan. Each is tagged with the step it belongs to:

| # | Idea | Step |
|---|---|---|
| 1 | Personalized Sun/Moon behavior sheet | 1 |
| 2 | Planets-in-signs behavior guide | 1 |
| 3 | "Spark points" synastry teaser | 1 |
| 4 | Venus/Mars attraction-language cheat sheet | 1 |
| 5 | "Text her this" opener generator, by her Sun sign | 2 (nurture) |
| 6 | First-date planner by element (fire/earth/air/water) | 2 (nurture) |
| 7 | Red-flag / green-flag-by-sign primer | 2 (nurture) |
| 8 | Moon-phase approach-timing tool ("next good window: Thursday") | 2 (nurture / novelty hook) |
| 9 | Mini compatibility quiz ("what's your situation type") | 2 (primary landing) |
| 10 | Shareable "your Venus sign" result card | 2 (share loop) |
| 11 | Sign-by-sign conversation-topics guide | 2 (nurture) |
| 12 | Apology/repair-language-by-sign mini-guide | 2 (retention) |
| 13 | "Is she interested?" body-language-by-element primer | 2 (nurture) |
| 14 | Monthly "who's in your orbit" email | 2 (recurring free touch, §4.2 Stage 3) |

Every tool, at either step, ends in the same two CTAs: get the full
personalized report, and book a compatibility/relationship consultation.

### 8.4 Reach & conversion tactics (apply at both steps, to different degrees)

- **SEO**: target the proven "how to attract/date/talk to a [sign] woman"
  query set validated above, landing visitors on a page with an actual
  product, not just an article.
- **Short-form video** (TikTok/Reels/Shorts) is the native channel for
  this audience — see §2.6 for the full evaluation of formats and the
  specific risk/alternative analysis for the imagery-led idea originally
  proposed.
- **Quiz-first landing** for anything running paid traffic, matching the
  40% opt-in benchmark — this is exactly why Step 2 restructures around
  the quiz rather than Step 1's form.
- **Paywall placement**: one genuinely useful free insight, then gate the
  specific, actionable layer — mirrors the Etsy/Co-Star pattern of "free
  is real, paid is deeper."
- **Personalization at the paywall**: show her actual sign/Moon in the
  locked-preview copy — proven stronger than generic upsell copy.
- **Built-in share loop**: the free result card is shareable by design
  (§2.2) — each share is a free impression.
- **Consultation upsell timing**: offer it after the report is delivered,
  not before — "you've read the chart, now let's plan what you actually
  say to her" is a natural second sale.

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
[AstroChartus: dating guide by zodiac sign](https://www.astrochartus.com/dating) ·
[SunSigns.org: dating women by zodiac sign](https://www.sunsigns.org/dating-women-by-zodiac-sign/) ·
[YourTango: how to attract by zodiac sign](https://www.yourtango.com/2018316527/how-to-attract-a-man-by-zodiac-sign-astrology) ·
[Astrologykart: attract your crush by zodiac sign](https://astrologykart.com/astrology-blog/how-to-attract-your-crush-using-their-zodiac-sign) ·
[GrowingSelf: dating coach cost](https://www.growingself.com/dating-coach-cost/) ·
[Evan Marc Katz: price of a dating coach](https://www.evanmarckatz.com/blog/dating-tips-advice/the-price-of-true-love-how-much-is-a-dating-coach-and-is-it-worth-it) ·
[Craft of Charisma: 1-on-1 dating coaching](https://www.craftofcharisma.com/product/1-on-1-dating-coaching/) ·
[stormy.ai: astrology TikTok influencers](https://stormy.ai/discover/tiktok/has-content-about-astrology-zodiac-signs-or-horoscopes-has) ·
[IZEA: top astrology influencers on TikTok](https://izea.com/resources/astrology-influencers-tiktok/) ·
[Interact: quiz conversion rate report 2026](https://www.tryinteract.com/blog/quiz-conversion-rate-report/) ·
[Digital Applied: lead magnet conversion benchmarks 2026](https://www.digitalapplied.com/blog/lead-magnet-conversion-benchmarks-2026-b2b-data-reference)

**Repository reference** (not a web source, but the other document this
plan depends on throughout §2.3/§3.4): the dormant payments plan lives at
`docs/superpowers/plans/2026-06-25-reusable-site-template-php-payments.md`
in the AstroTherapia repository (`C:\Work\Astrotherapia_Claude`).
