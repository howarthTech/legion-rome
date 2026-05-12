# Deploy handoff template (for app devs)

Use this template when shipping a deploy to ops on osh-vps. Following
the template means ops can deploy from the message without rewriting
your commands, and we don't lose the safety properties of the deploy
script.

Audience: app developers (mark-down-parts, project-gmac, future tenants).

---

## Template

> Subject: `<app>` deploy — `<one-line summary>` (commit `<short-sha>`)
>
> CI is building. Image `ghcr.io/<owner>/<repo>:sha-<short>` (and
> `:latest`) will be in GHCR within ~5 min.
>
> **Changes:**
> *(1–3 bullet points: what this PR adds, what it changes, what it removes)*
>
> **Migrations:** *(yes/no, name, additive/destructive)*
>
> **New env vars:** *(list each by name, see "Secrets handoff" below)*
>
> **Deploy command:**
> ```bash
> ssh osh-vps-deploy 'sudo /srv/scripts/deploy.sh <app>'
> ```
>
> **Verification:**
> *(one or more `curl` / browser checks that exercise the NEW code path,
> not just an existing endpoint)*
>
> **Order if env vars are involved:** secrets first, then deploy.

---

## Why this shape

### 1. Always invoke `deploy.sh`, never raw `docker compose pull && up -d`

`deploy.sh` does five things raw compose doesn't:

1. `git pull` on `/srv/apps/<app>/` so any compose-file changes you
   shipped land on the droplet
2. Waits up to 5 min for `:sha-<short-sha>` to publish in GHCR before
   touching the live container — eliminates the "deployed-before-CI-
   finished" footgun
3. Pulls `:sha-<short>` explicitly (not `:latest`, which floats), so
   the deploy is pinned to the commit you tested
4. Polls the healthcheck for up to 2 min
5. Verifies the running container's
   `org.opencontainers.image.revision` label matches git HEAD before
   exiting clean

Telling ops "just `docker compose pull && up -d`" silently strips all
five and we end up debugging stale-image races that we already solved.
If you have a reason `deploy.sh` won't work for a specific deploy,
flag it explicitly and we'll figure out the right path together.

### 2. Don't paste production secrets in plaintext

When a deploy needs a new secret, use one of these instead of pasting
the value into chat / email:

- **Generate on the droplet:**
  ```bash
  openssl rand -hex 32 | sudo tee -a /srv/secrets/<app>.env
  # then prepend the var name with sudoedit:
  sudo sed -i 's|^[a-f0-9]\{64\}$|MY_NEW_KEY=&|' /srv/secrets/<app>.env
  ```
  Tell ops which var to add and how to derive it; ops generates the
  value, the secret never leaves the droplet.

- **Out-of-band share:** 1Password share link, encrypted note, or a
  physical handoff. The deploy message references "secret in 1Password
  item X" rather than embedding the value.

- **If chat-paste is genuinely the only option** (small team, trusted
  channel, expedience), label it explicitly:
  > **Secret:** `WIFI_SECRET_KEY=<value>` — this is now in chat
  > history; rotate after Pi onboarding (week of YYYY-MM-DD).

### 3. Verification commands must touch the NEW code path

A `curl` against an unrelated endpoint that returns 401 isn't a
verification — it's a regression check, and a weak one. The deploy
hasn't actually been tested if nothing exercised the lines you just
shipped.

Better:
- Hit the new route (with auth if needed) and assert 200
- For new env-var dependencies, hit a route that uses the var and
  assert it doesn't 500 with "missing config"
- For new DB columns, query the new column once and assert non-error
- If the feature is admin-only and hard to curl, say so — give ops a
  manual "click X, expect Y" smoke check instead

If the deploy is genuinely unobservable from the outside (background
worker, queue consumer), say that explicitly and provide a log line
to grep for.

### 4. Order matters for env-var deploys — make it explicit

If the new code 500s without the new env var (auth helpers that fail
closed, crypto helpers that need a key, etc.), ops *must* update
secrets before recreating the container. Make the order unambiguous:

> 1. Add `MY_NEW_KEY` to `/srv/secrets/<app>.env` (see "Secrets handoff")
> 2. Run `sudo /srv/scripts/deploy.sh <app>`
> 3. Verify *(commands)*

If the new code degrades gracefully when the var is missing, say so
explicitly so ops knows the order is flexible.

### 5. State the migration risk

Ops can read `prisma/migrations/<name>/migration.sql`, but a one-line
note saves an SSH round-trip:

> **Migrations:** `20260507000000_add_printer_and_wifi` — additive
> only (`CREATE TABLE Printer`, `CREATE TABLE WifiNetwork`). No
> alterations to existing tables. Zero risk to live Stripe orders.

Or:

> **Migrations:** `20260507000000_rename_orders_to_carts` — **renames
> the `orders` table**. Coordinated downtime required. Ops, please
> stop the app before deploying.

---

## What ops will do with your handoff

Given a template-conformant handoff, ops:

1. Reads the migration risk note → decides if a maintenance window
   is needed
2. Updates secrets (if listed) before deploying
3. Runs `ssh osh-vps-deploy 'sudo /srv/scripts/deploy.sh <app>'`
4. Runs your verification commands
5. Acks the deploy

Most of the message body becomes a confirmation that those five steps
went green, not back-and-forth on what the commands should have been.

---

## Anti-template — what we keep seeing and don't want

```
cd /srv/apps/<app>
docker compose pull
docker compose up -d
```

Three problems:
- No `git pull` (compose-file changes on disk get missed)
- No race guard (`:latest` may still resolve to the prior image)
- No revision verification (container can come up "healthy" on the
  wrong commit and we won't know)

```
WIFI_SECRET_KEY=c7504c95416bb571f93ff2276e6b4f2dae0026ee3a729b1b7ed2a452c57dc9ac
```

Production secret pasted directly. Now in chat history, not rotatable
without coordination, leaks via search.

```
curl -X POST https://app.../api/print-queue/claim → 401  ✓
```

This passes whether or not the new feature works. Verifies nothing
the deploy actually changed.
