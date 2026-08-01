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

**Rule:** every script or CI step that hits the live site must send both:

```bash
curl -A "astro-deploy-bot/1.0" -H "Accept: text/html" "$URL"     # page checks
curl -A "astro-deploy-bot/1.0" -H "Accept: text/plain" "$URL"    # deploy hooks
```

The smoke tests and deploy-hook calls in
[`.github/workflows/cicd.yml`](../.github/workflows/cicd.yml) already do. If a
415 appears on a **new** script, this is the cause — check it before
re-diagnosing anything else.

---

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
