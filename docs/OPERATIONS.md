# Operations runbook (live site)

Things that are true about the **running** dev/prod sites and that no amount of
reading the code will tell you: how to get into the host's panel, host quirks
that break automation, and how to verify contact-form mail actually left the
box. Setup and pipeline configuration live in
[`DEPLOY-CPANEL.md`](DEPLOY-CPANEL.md); this file is what you consult when the
live site misbehaves.

> **Ask before touching live infrastructure.** Nothing here is an invitation to
> connect. Probing the live host, its FTP/SMTP, or its panel is a per-action
> decision the site owner makes — see the rule in [`../CLAUDE.md`](../CLAUDE.md).

---

## Host & panel access

| Thing | Value |
|---|---|
| cPanel server | `server20.romania-webhosting.com` |
| Panel login | `https://server20.romania-webhosting.com:2083` |
| Domains / subdomains page | append `/frontend/jupiter/domains/index.html` after login |
| Prod site | `https://astrotherapia.com` (also `astrotherapia.ro`) |
| Dev site | `https://dev.astrotherapia.com` |

Dev and prod are two subdomains **under one cPanel account**, with separate app
directories and separate MySQL databases — that shared account is what makes the
one-click *Copy prod → dev* possible (dev opens a direct PDO connection to
prod's database).

Panel login credentials live in the GitHub environment secrets and the
git-ignored `docs/DEPLOY-SECRETS.local.md`, never here. A cPanel URL containing
a `cpsess…` segment is a **session token** — it is a credential and it expires;
never paste one into a file or a commit.

---

## Host WAF: bare `curl` gets HTTP 415

The host runs LiteSpeed + Imunify360/mod_security. It returns **`415 Unsupported
Media Type`** to requests coming from GitHub Actions runners (and other
datacenter IPs) when the request carries **no explicit `Accept` header** — a
plain `curl` sends `Accept: */*` and is refused, while the identical request
from a residential IP returns 200.

The WAF anomaly-scores datacenter IPs higher, then a media-type rule rejects
`*/*`. Clean IPs stay under the threshold, so **this is not reproducible from a
local machine** — do not spend time trying.

`User-Agent` alone does not fix it (`-A "astro-deploy-bot/1.0"` still 415s). The
decisive header is `Accept`.

**Rule:** every script or CI step that hits the live site must send both a
`User-Agent` and an explicit `Accept`. See the next section for the values that
currently work — a bot-shaped UA is no longer enough.

---

## Host WAF, part two: the silent 200 challenge page (2026-08-01)

Worse than the 415, because it **succeeds**. The WAF answers requests it doesn't
like with **HTTP 200** and this body instead of proxying to PHP:

```html
<title>One moment, please...</title>
<script>(function(){ setTimeout(function(){ window.location.reload(); }, 5000); }())</script>
```

An Imunify360-style JS challenge. Consequences, all observed:

- `curl -fsS` only fails on 4xx/5xx, so **a challenged deploy hook reported
  success**. `deploy_dev` went green in 19 s with the challenge page in its log,
  `extract.php` never ran, and the site kept serving the previous build — with
  no failure anywhere to point at it. It cost a "why doesn't my deploy show up"
  round trip; assume it will again if the check below is ever removed.
- Smoke tests that only read `%{http_code}` pass against a challenged site for
  the same reason.

What passes and what gets challenged (measured, same host, same minute):

| Request | Result |
|---|---|
| `-A "astro-deploy-bot/1.0" -H "Accept: text/plain"` | challenge page (200) |
| `-A "Mozilla/5.0 …Chrome/126…" -H "Accept: text/html,application/xhtml+xml"` | the real page (200) |

**Rules now enforced in CI** — do not weaken these:

1. Deploy hooks go through
   [`.github/scripts/run-deploy-hook.sh`](../.github/scripts/run-deploy-hook.sh),
   which sends browser-shaped headers **and fails the step unless the body
   contains the hook's own success marker** (`Archive removed.` for
   `extract.php`, `Deploy hook finished.` for `deploy.php`).
2. Every site-facing smoke curl sends `-A "$UA" -H "Accept: text/html,…"` and
   greps the body for `$WAF_CHALLENGE_MARKER`; both are workflow-level `env` in
   `cicd.yml` and `rollback-prod.yml`.
3. `tests/Feature/DeployHookVerificationTest.php` fails the suite if any of that
   is undone.

If a deploy ever "succeeds" but the site doesn't change, read the hook step's
body first — that is this failure, and the fix is to re-run the workflow (the
FTPS upload is idempotent, so re-running is safe).

### Browser-shaped headers are necessary, not sufficient (2026-08-02)

`deploy_dev` failed in 18 s with the challenge page in the log — the guard above
working exactly as intended, but the challenge now fires against the
Chrome-shaped request too. The header table above still holds (a bot UA is
*always* challenged); it just no longer buys immunity.

So `run-deploy-hook.sh` now does what the challenge page itself does: it keeps a
**cookie jar** (`-c`/`-b`), and on a marker-less 200 it waits and **asks again**
— the challenge sets a cookie and its own JS reloads after 5 s, and that reload
is proxied through. Four attempts, 15 s apart by default (`HOOK_ATTEMPTS`,
`HOOK_RETRY_DELAY`). Only after all of them does the step go red.

Retrying cannot mask a real failure: a retry only happens when the marker is
absent, i.e. PHP was never reached. `extract.php` re-extracts the same uploaded
archive and `deploy.php`'s migrate/cache work is idempotent.

`tests/Feature/DeployHookRetryTest.php` drives the real script against a local
stub that challenges first and answers second, pinning both halves — that it
gets through, and that it still fails when every attempt is challenged.

### The cookie is the whole mechanism (2026-08-02, same day)

The next run proved what the retry was really buying. `deploy_dev` passed — the
cookie-carrying hook got through — and then `test_dev` failed 1 m 14 s later with
**12 challenge pages in 12 attempts** on `/up`. Same runner, same IP, same
minute, opposite outcomes.

The difference was not patience: the wait loop had *more* retries than the hook.
It was that each of its 12 curls was a fresh session with no cookie jar, so every
attempt restarted the challenge. **The challenge is cleared by a cookie, not by
waiting.** Retrying without a jar can never converge.

So every site-facing request now goes through
[`.github/scripts/fetch-site.sh`](../.github/scripts/fetch-site.sh) — browser
headers, the shared jar (`WAF_COOKIE_JAR`, workflow-level `env`), body check,
bounded retry, status code on stdout. Both smoke jobs in `cicd.yml` and the
rollback's smoke job call it, and both now `actions/checkout` first because they
need the script. The challenge is solved once per job and every later step rides
on that cookie.

`tests/Feature/SiteFetchRetryTest.php` pins it against a stub that challenges
anything without the cookie: that the first request clears it, that a *second*
request is not challenged again (the regression that caused this), that it fails
when the challenge never clears — and that neither workflow contains a raw
`curl` aimed at the site.

The standing rule derived from all of this lives in
[`../CLAUDE.md`](../CLAUDE.md): two scripts, no direct HTTP to the host.

---

## FTPS upload: `curl: (28) Failed to connect ... port 21` (2026-08-02)

A `deploy_dev` run failed in the **upload** step, before any hook or smoke test:

```
curl: (28) Failed to connect to *** port 21 after 134234 ms: Couldn't connect to server
```

A connect **timeout**, not a refusal — the SYN was dropped rather than rejected,
which means either FTP was down on the host or something in front of it was
blackholing the runner's IP. Unrelated to the WAF work above: FTPS is a different
protocol on a different port and does not pass through the WAF at all.

The upload is now capped and retried (`--connect-timeout 25 --retry 4
--retry-delay 15 --retry-connrefused`, pinned by
`tests/Feature/FtpsUploadTest.php`), so a dropped connection costs a retry
instead of the build. `--ssl-reqd` stays on it — without that curl falls back to
plaintext FTP with the deploy credentials on the wire; never trade it for
reliability.

**If it repeats after a re-run** (a re-run gets a different runner IP, which by
itself distinguishes an IP block from an outage), it is host-side. In cPanel:

- **Imunify360 → Incidents / Blocked IPs** — the likeliest cause of a silent
  drop; look for GitHub Actions ranges.
- **cPHulk Brute Force Protection → blocked IPs** — blocks reach the port before
  any login is attempted.
- The FTP service's own status, and whether plain FTP from your own machine
  connects (your residential IP is scored differently — see the WAF sections).

## Contact-form mail

The public contact form (`POST /{locale}/contact` → `App\Mail\ContactMessage`)
delivers to the **`office@astrotherapia.com` mailbox**, readable in cPanel
webmail. Delivery stays inside the server (`dovecot_virtual_delivery`).

**If someone reports "the contact form is not working": check that mailbox in
cPanel webmail first** — Inbox, not just Junk. A 2026-08-01 report turned out to
be a working form and the wrong mailbox being watched.

### Sender address

`MAIL_FROM_ADDRESS` is `contact-form@astrotherapia.com`, both as a GitHub
variable and as the fallback baked into
[`.github/scripts/make-env.sh`](../.github/scripts/make-env.sh), so both
environments get an authorized sender even with no variable set. No mailbox
backs that address — bounces to it are discarded, by choice.

It **must stay on a domain the site controls.** The `astrotherapia.com` SPF
record (`v=spf1 +a +mx +ip4:86.107.43.20 ~all`) authorizes this server. The
earlier `no-reply@example.com` placeholder failed by design: `example.com`
publishes `v=spf1 -all` and `p=reject;sp=reject;adkim=s;aspf=s`, so any filter
evaluating the sender was told to reject it. It only ever arrived because local
delivery skips sender policy.

### Diagnosing a suspected non-delivery

Cheapest first:

1. **SMTP timing discriminator.** `PageController::contactSubmit` returns the
   same "sent" confirmation for a real send and for a tripped honeypot or
   timestamp trap, so the response body proves nothing. Submit once with the
   honeypot field `website=` filled — a provable **no-send** control, ~79 ms —
   then submit cleanly, which does real SMTP work at ~166–243 ms. The gap tells
   you whether mail was actually handed off.
2. **cPanel → Email → Track Delivery**, with **Show Successes** ticked (it
   defaults to failures only). Gives per-message From / Recipient / Router /
   Result. The `Sender Host` column separates prod (`astrotherapia.com`) from
   dev (`dev.astrotherapia.com`), because `config/mail.php` derives the EHLO
   `local_domain` from `APP_URL`.

Locally none of this is exercised: the local `.env` uses `MAIL_MAILER=log` and
every test uses `Mail::fake()`, so **no test touches the real SMTP transport** —
mail behaviour is only ever proven on the live host.

### Known and accepted (do not "fix" unasked)

Deliberate decisions by the site owner, recorded so they are not rediscovered as
bugs:

- Mail vars in `make-env.sh` use `${VAR:-default}` (soft fallback) while DB vars
  use `${VAR:?required}` (hard fail). Asymmetric on purpose.
- `MAIL_ENCRYPTION=tls` is dead config — Laravel 12+ reads `MAIL_SCHEME`. It is
  written to `.env` and ignored.
- `MAIL_USERNAME` / `MAIL_PASSWORD` are empty on both environments; sends rely
  on the unauthenticated local relay.
- `admin@astrotherapia.com` does not exist, so the server's own cron mail has
  bounced "No Such User Here" since at least 2026-07-04. Unrelated to the
  contact form.

## Rolling back site content

Content only — `authors`, `posts`, `post_translations`, `media`,
`site_settings`. Accounts, sessions and uploaded files are never touched, so a
rollback cannot log anyone out and cannot bring back a deleted image file.

1. Admin → **Database**. Pick the backup from before the mistake. Backups are
   listed newest first; `auto (pre-restore)` ones were written automatically
   just before an earlier restore or pull.
2. **Restore** opens a confirmation page. Check the row counts against what is
   live now — that is what catches a wrong file.
3. Type the site host and confirm. A snapshot of current content is written
   first; its filename is in the message afterwards, so the restore itself is
   reversible.

Only backups this site wrote are offered. A dev backup is not restorable on
production and vice versa — the host segment of the filename must match
`APP_URL`, and a mismatch is a 404. This works on production; it is not gated by
`DB_RESTORE_ENABLED`, which now covers only the prod → dev copy.

If the admin page is unreachable, a backup is plain gzipped SQL and can be
imported through cPanel's phpMyAdmin. It carries data only — no `CREATE TABLE` —
so the schema must already exist.
