# For app developers: building on osh-vps

If you're writing an app that will be hosted on this server, read this
before you start. Everything here is a constraint or convention *specific
to this environment* — not generic Docker advice.

---

## What the server is

- **1 DigitalOcean droplet**, SFO2, Ubuntu 24.04 LTS
- **2 vCPU, 4 GB RAM, 120 GB NVMe SSD, 2 GB swap**
- **Multi-tenant**: co-hosted with other apps. Your app is a neighbor, not
  the sole occupant. Don't assume the whole box is yours.

This is a deliberately small box. It's not a Kubernetes cluster, not a
PaaS, not autoscaling. Design accordingly.

## What the server already does for you

Don't re-implement these in your app:

- **HTTPS / TLS** — Caddy on the host terminates TLS and auto-issues
  Let's Encrypt certs. Your app speaks plain HTTP on a loopback port.
- **Reverse proxy / hostname routing** — Caddy maps
  `yourapp.example.com` → `127.0.0.1:<yourport>`. You don't configure
  nginx, don't install certs, don't deal with HSTS (Caddy adds the
  header).
- **Firewall** — only ports 2222/80/443 are open by default. Your
  app's HTTP traffic goes through Caddy; you do not need to open
  anything. If your app genuinely needs a non-HTTP port exposed
  (game server, WireGuard, SSH-based git, custom protocol), you can
  request it — see "Requesting a non-default port" below.
- **Security patches** — `unattended-upgrades` installs security-only
  apt updates automatically. Build your app so it survives occasional
  background patching (and rare reboots).
- **Docker log rotation** — the daemon rotates your container's
  stdout/stderr at 10 MB × 5 files per container. Log to stdout/stderr.
  Do NOT write logs to a file inside the container or a volume — you
  will eventually fill the disk.

## Hard rules (non-negotiable)

1. **Bind HTTP services to `127.0.0.1:<port>`, not `0.0.0.0`.**
   In your `docker-compose.yml`:
   ```yaml
   services:
     app:
       ports:
         - "127.0.0.1:3000:3000"   # GOOD — Caddy proxies the public request
         # - "3000:3000"           # WRONG for HTTP — bypasses Caddy, exposes to internet
   ```
   The only thing listening on `0.0.0.0:80` and `0.0.0.0:443` is Caddy.

   **Exception:** if ops has approved your app for a non-default
   exposed port (game server, VPN, etc.), the one service that
   needs the public port binds to `0.0.0.0:<approved-port>`
   deliberately. Every *other* service in your compose still binds
   to loopback. See "Requesting a non-default port" below.

2. **Pick a unique loopback port.**
   Current allocations (check and update [sysadmin.md](../sysadmin.md)
   when you add a new app):
   | Port  | App / tenant                      |
   | ---   | ---                               |
   | 3000  | mark-down-parts                   |
   | 8000  | project-gmac                      |
   | 9000  | websites tenant (PHP-FPM, shared) |

3. **Secrets live at `/srv/secrets/<yourapp>.env` on the droplet — not
   in git, not in the image.** Mode 600, owned by root. Reference via
   compose `env_file:`:
   ```yaml
   services:
     app:
       env_file: /srv/secrets/yourapp.env
   ```
   Ops (the sysadmin) will create this file with the real values; your
   repo ships a `.env.example` with the key names and empty values so
   ops knows what to fill in.

4. **Persistent state goes in named Docker volumes, NOT bind-mounts into
   `./data` or similar.** Named volumes survive `docker compose down`
   and are cleanly backupable via the backup framework.
   ```yaml
   services:
     db:
       volumes:
         - app_db_data:/var/lib/mysql   # GOOD
         # - ./data:/var/lib/mysql      # AVOID
   volumes:
     app_db_data:
   ```

5. **If your app has state worth keeping (DB, user uploads, etc.), you
   OWN writing the backup drop-in.** See
   [backup.md](./backup.md#adding-a-drop-in-for-a-new-app) and
   [scripts/backup.d/_example.sh](../scripts/backup.d/_example.sh).
   No drop-in = no backups = your fault.

6. **Set resource limits on every service.** Containers are process-isolated
   but NOT resource-isolated by default. A container with no `limits` can
   consume every CPU cycle and every byte of RAM on the box, starving its
   neighbors. Every service in your compose file MUST include:

   ```yaml
   services:
     app:
       deploy:
         resources:
           limits:
             cpus: '<your allocation>'
             memory: <your allocation>g
             pids: 1000                # prevents fork bombs
   ```

   **Compose V2 gotcha — do NOT use top-level `pids_limit:` when you also
   have a `deploy:` block.** Docker Compose V2 treats
   `pids_limit:` (top-level, Compose V1 style) and
   `deploy.resources.limits.pids` (Compose V2 / Swarm style) as conflicting
   fields — even if only one is explicitly set. It's rejected at
   `docker compose config` with
   `services.X: can't set distinct values on 'pids_limit' and 'deploy.resources.limits.pids'`.

   Always put PID limits inside the `deploy.resources.limits` block as
   shown above, not at the top level. This has bitten every tenant we've
   onboarded so far; validate with `docker compose config --quiet`
   locally before pushing.

   The current box is 2 vCPU / 4 GB total. Budget for this box:

   | Tenant                  | CPU cap | Memory cap | Notes                             |
   | ---                     | ---     | ---        | ---                               |
   | mark-down-parts (all services combined) | `0.65`  | `1.25g`    | Next.js app + MariaDB + OCR       |
   | project-gmac (all services combined)    | `0.65`  | `1.2g`     | FastAPI + SQLite                  |
   | websites (PHP-FPM + static, shared across 1–5 small sites) | `0.5` | `500m` | Add MariaDB here if a WP site lands |
   | Host reserve (unallocated) | ~0.2  | ~1g        | Caddy (serving static for websites), sshd, docker daemon, etc. |

   Split your allocation across your own services as makes sense (e.g.
   app: 1 CPU / 1.5g, db: 0.5 CPU / 1g). If you need more than your
   allocation, talk to ops before deploying — it's a capacity question,
   not a compose file question.

   **Rebalancing:** the table above is a starting allocation, not a
   constitution. When real numbers from `docker stats` show one tenant
   chronically under-using its budget and another hitting ceilings, ops
   can shift allocation across tenants. The droplet's **total** 2 CPU /
   4 GB is the real constraint; the per-tenant rows are a policy the
   sysadmin adjusts as needs change. If your app legitimately needs
   more than it's budgeted, measure and come to ops with numbers, not
   intuition.

   **Adding a sibling tenant** (e.g. an API your app depends on, separate
   from your app itself) means rebalancing, not ignoring the total.
   Sibling tenants are a real pattern — see
   [hosting-pattern.md § Tenant shapes](./hosting-pattern.md#tenant-shapes).

   **Deploys that don't include limits on every service will be rejected.**
   This is enforced during deploy review, not automated — but it's not
   negotiable. The first outage caused by a noisy neighbor makes
   everyone unhappy, and this is the fix.

7. **Containerize everything. Host installs need written justification.**
   If your app needs package X, your Dockerfile installs X. The host is
   off-limits for app-specific dependencies — not even for "just one
   small thing." Every host install is a permanent dependency that
   must survive DR, stay patched, and coexist with everything else on
   the host; that friction is intentional.

   Examples of what goes in your container (99% of cases):
   - Language runtime + libraries (Node, Python, Ruby, Go, PHP, etc.)
   - System packages your code links against (`libpq-dev`, `libvips`,
     `imagemagick`, `poppler-utils`, `tesseract-ocr`, etc.)
   - Playwright / Puppeteer browser binaries
   - Database client libraries
   - Any CLI tool your app shells out to

   The only things that belong on the host:
   - Things that affect *all* apps (Caddy, Docker daemon, fail2ban,
     backup tooling, monitoring agents)
   - Things that genuinely cannot run in a container (kernel modules,
     GPU drivers) — essentially zero apps need this

   If you think you have a case for a host install, send ops a one-paragraph
   request answering:
   - What the package is and what version
   - Why it can't go in your container
   - What alternatives you've tried
   - What other apps on the server could reasonably also use it

   Ops evaluates, either installs (and documents in `sysadmin.md` +
   [disaster-recovery.md](./disaster-recovery.md)), or proposes a
   containerized path. Most requests turn into the latter.

8. **Outbound email goes through the shared OSH Resend account.** There's
   no MTA on the host. Don't register your own provider account. Ask ops
   for:
   - A **Resend API key scoped to your sending domain** (created fresh
     per app, "Sending access" permission only, restricted to your app's
     verified domain — a leaked key can't affect other apps' reputation)
   - Confirmation that your sending domain is **verified in Resend** (DNS
     records for DKIM/SPF/DMARC have to be in place and verified before
     the first send)

   Your app reads two env vars from its secrets file:
   ```
   RESEND_API_KEY=re_...
   EMAIL_FROM=noreply@yourdomain.com
   ```
   Then call Resend's HTTP API from code — their SDK (`resend` npm
   package, `resend` Python package, etc.) is simplest; a plain `curl`
   to `https://api.resend.com/emails` works too.

   Do NOT:
   - Call `sendmail` / `mail` / SMTP port 25 — there's no MTA, and DO
     blocks outbound 25 anyway
   - Register a separate Resend/Postmark/etc. account for just your app
     — we pool volume into one free tier and one place to manage
     deliverability
   - Hardcode the From address inside your code — pull it from
     `EMAIL_FROM` so ops can change it without a code deploy

## Strong recommendations

1. **Prefer pulling a pre-built image over building on the droplet.**
   4 GB of RAM is tight. `next build` and similar JS-tooling builds often
   consume >2 GB, pushing into swap and sometimes OOM-killing. Cleanest
   pattern:
   - Build the image in CI (GitHub Actions works great) and push to
     GitHub Container Registry (`ghcr.io`)
   - On the droplet, `docker compose pull && docker compose up -d`
   - Zero build work happens on the host

   If you do build on the droplet, at least pin `NODE_OPTIONS=--max-old-space-size=3072`
   in your Dockerfile's build stage.

2. **Multi-stage Dockerfile with a thin runtime image.** Don't ship
   `node_modules` from a Debian build stage — copy just what the
   standalone build needs to an `alpine` or `distroless` runtime. Image
   size eats disk and slows pulls.

3. **Set `restart: unless-stopped` on every service in compose.**
   Otherwise a reboot (security patch) or crash won't bring the app back.

4. **Set a `healthcheck:` in compose.** Doesn't affect Caddy routing, but
   makes `docker ps` honest and enables fail-fast on broken deploys.

5. **Pin your container timezone.** Host is `America/Denver` but
   containers inherit UTC unless told otherwise. Pick a canonical TZ for
   your app (UTC internally is the standard advice) and set it
   explicitly:
   ```yaml
   environment:
     TZ: America/Denver
   ```

6. **Make migrations idempotent.** On DR the whole droplet rebuilds from
   scratch: fresh database, `docker compose up`, restore dump into it.
   Your migration flow must not break when run against a restored DB.
   Use Prisma `migrate deploy` (not `migrate dev`), Alembic `upgrade head`,
   etc. — not interactive tools.

7. **First admin user / seed data: a script, not a click-around.** If
   someone has to log in and click buttons to make the app usable, they
   won't remember how after 18 months. Put it in a one-shot script your
   Dockerfile or entrypoint can run.

8. **Storage for user-generated content: default to local volume, move
   to object storage when scale demands it.** Two options for apps that
   accept uploads (photos, documents, etc.):

   | | Named Docker volume | Object storage (R2 / S3 / DO Spaces) |
   |---|---|---|
   | **Setup complexity** | Trivial — `volumes:` in compose | App needs SDK, credentials, retry logic |
   | **Backup integration** | Your drop-in tars the volume nightly, backup framework handles the rest | You maintain separate lifecycle/versioning at the provider |
   | **Durability** | As good as the droplet's disk + your off-site backup | Higher (provider-managed replication) |
   | **Scales to** | ~40 GB comfortably on 120 GB droplet (leaving room for DB, logs, OS) | Essentially unlimited |
   | **Latency (serve a file)** | ~1 ms | 20-100 ms + signed URL overhead |
   | **Cost** | $0 incremental (part of droplet) | Pennies/GB/mo + egress |
   | **Multi-region / CDN** | No | Yes (R2 has free egress via Cloudflare) |

   **Default to local volume for launch.** It's simpler, faster to
   serve, integrates cleanly with the backup framework, and "good
   enough" for most apps for their first year.

   **Migrate to object storage when** you hit any of:
   - Upload volume exceeds ~40 GB (droplet disk pressure)
   - You want a CDN in front of the assets (image-heavy sites)
   - Multi-region serving matters
   - You're adding horizontal redundancy and volumes can't be shared

   Planning tip: design your code so the storage backend is swappable —
   a thin `saveUpload(file)` / `getUpload(id)` interface over either
   filesystem or S3-compatible API. Migration later is then a config
   change plus a one-time file copy, not a refactor.

9. **Your runtime image must include everything needed for post-deploy
   ops, not just for serving requests.** This is the #1 trap when using
   Next.js `output: "standalone"`, Nuxt's `.output`, or any other
   framework that produces a minimized runtime bundle — **the minimized
   bundle deliberately omits build tools and dev dependencies, which
   often includes the exact things ops needs post-deploy:**

   | Operation | What it needs in the runtime image |
   |---|---|
   | `prisma migrate deploy` | `prisma/schema.prisma`, `prisma/migrations/`, the `prisma` CLI package, executable shim in `node_modules/.bin/` |
   | `npx tsx scripts/seed.ts` | `tsx` **and its full dependency tree** (esbuild, get-tsconfig, etc.) — not just the `tsx` package alone |
   | `alembic upgrade head` (Python) | `alembic/` dir, `alembic.ini`, alembic CLI on PATH |
   | `rails db:schema:load` | rails + all gems, not a `rails runner`-style slim image |

   **Test for this before you ship:** run the post-deploy commands in
   your locally-built runtime image, not the build-stage image:
   ```bash
   docker build --target runner -t my-app .
   docker run --rm my-app npx prisma --version          # works?
   docker run --rm my-app npx tsx --version             # works?
   docker run --rm my-app node scripts/some-seed.js     # works?
   ```
   If any of these fail with "not found" or `ERR_MODULE_NOT_FOUND`,
   you've under-copied. Ops will hit the same error at deploy time.

   **Concrete example from mark-down-parts** (the specific `COPY`
   lines needed in the runner stage of the Dockerfile):
   ```dockerfile
   # Prisma client (generated, already copied — needed at runtime for queries)
   COPY --from=builder /app/node_modules/.prisma ./node_modules/.prisma
   COPY --from=builder /app/node_modules/@prisma ./node_modules/@prisma

   # THESE are also needed for `npx prisma migrate deploy`:
   COPY --from=builder /app/prisma ./prisma
   COPY --from=builder /app/node_modules/prisma ./node_modules/prisma
   COPY --from=builder /app/node_modules/.bin ./node_modules/.bin
   ```

   **Alternative pattern: auto-migrate on startup.** Put
   `prisma migrate deploy || exit 1` (or equivalent) in an entrypoint
   script that runs before `node server.js`. Trades the post-deploy
   migration step for automatic behavior on every container start.
   Pros: simpler deploy. Cons: all containers try to migrate; race
   possible on scale-out (not a concern here with one replica).

   **`.ts` seed scripts need more than just the `tsx` package.** If
   you ship `scripts/seed.ts` and expect to run it via `npx tsx`, you
   need the full tsx dependency tree — copying just `node_modules/tsx`
   gives you `ERR_MODULE_NOT_FOUND` at runtime because tsx loads
   esbuild and other nested deps lazily. Two ways to avoid this:
   - **Convert to `.mjs`** — write seed scripts as plain ESM / CJS and
     invoke with `node` directly. No tsx needed; no extra COPYs. For a
     typical `PrismaClient + bcrypt + process.env` script this is ~60
     lines either way.
   - **Use a pre-compiled script** — tsc or esbuild bundle the `.ts`
     into a `.js` at build time, ship only the `.js`. Same outcome.

   Ops doesn't normally install runtime tooling to work around a thin
   image. If the Dockerfile can't run the intended commands, that's a
   dev fix, not an ops workaround.

## Requesting a non-default port

The default posture is "only 2222/80/443 are open; everything else
lives on loopback behind Caddy." But there are legitimate cases where
your app genuinely needs its own port exposed to the internet — for
example:

- A game server speaking its own UDP/TCP protocol (Minecraft, Valheim, etc.)
- WireGuard VPN endpoint (UDP 51820)
- SSH-based git hosting (Gitea, Forgejo — usually on 2223 or similar)
- MQTT broker (TCP 1883 / 8883)
- RTMP ingest for live streaming (TCP 1935)
- Any other non-HTTP protocol that Caddy can't proxy

### Not valid reasons (ops will push back)

- **"I want to test the container directly."** Use
  `ssh -L <port>:127.0.0.1:<port> osh-vps` to tunnel the loopback
  port to your laptop. No public port needed.
- **"My API runs on port 8080 / 3000 / etc."** That's a loopback
  port. Caddy proxies it. No public port needed.
- **"I want WebSockets / gRPC."** Caddy handles both over 443.
- **"I want my database reachable from my laptop."** Use an SSH
  tunnel or set up WireGuard. Never open a DB port to the internet.
- **"Just in case I need it later."** We don't pre-open ports.
  Request them when actually needed.

### How to request

Send ops a one-paragraph request with:

1. **Port number and protocol** (TCP / UDP / both)
2. **Why Caddy can't handle it** (i.e. what non-HTTP protocol is it)
3. **Source restriction if any** — "only my office IP" or "only a
   specific CDN's IPs" lets us ufw-restrict by source, hugely
   reducing risk vs. "open to the world"
4. **What the app does if the port is unreachable** (fails loudly
   vs. silently)

### What ops does if approved

1. Adds the ufw rule (with source restriction if applicable) and
   documents it in [sysadmin.md](../sysadmin.md) and
   [runbooks/disaster-recovery.md](./disaster-recovery.md) so
   rebuilds recreate it
2. Coordinates with you on whether the container binds `0.0.0.0:<port>`
   directly (most common) or whether Caddy's `layer4` module
   should front it for logging/metrics
3. Confirms the app is doing its own TLS / auth / rate-limit since
   Caddy won't be in the path — Caddy's security features only
   apply to traffic going through Caddy

### What this means for your compose

When an open-port app deploys, its compose has a PUBLIC port binding
instead of the usual loopback one. **Only the specific service that
needs the exposed port does this.** Everything else in the same
compose still binds `127.0.0.1:`:

```yaml
services:
  # This service is what needs the public port
  game-server:
    ports:
      - "25565:25565"          # PUBLIC (no 127.0.0.1: prefix)
      - "25565:25565/udp"      # If UDP also needed

  # Other services (admin panel, metrics, etc.) still stay on loopback
  admin:
    ports:
      - "127.0.0.1:8080:8080"  # private, Caddy proxies
```

The ufw rule is what makes the public port reachable — the compose
port binding alone doesn't. Both have to be in place.

## Things that are true but feel surprising

- **Your access model is one of three tiers, but Tier A is the default
  and what we expect to use unless there's a specific reason otherwise.**
  Decided by ops when you onboard:
  - **Tier A (the default for every new tenant — what every current
    tenant uses: mark-down-parts, project-gmac, lazrchess):** you do
    not get a shell account on the droplet. Your CI pipeline (GitHub
    Actions) SSHes in as a restricted `deploy-<slug>` user, scoped to
    `sudo /srv/scripts/deploy.sh <slug>` and nothing else, and that's
    your deploy path. You interact with the server only through the
    repo and CI logs. Setup is documented at
    [ci-auto-deploy-setup.md](./ci-auto-deploy-setup.md).
    **Plan your workflow assuming Tier A from day one** — don't build
    a deploy story around personal SSH and then ask to switch later.
  - **Tier B (rare, requires justification):** you get your own Unix
    account with access to `/srv/apps/<your-app>/` and docker-group
    deploy permissions. SSH in directly for deploys and debugging.
    The `docker` group is root-equivalent on Linux (a user with docker
    access can `docker run -v /:/host ...` and escape to the host
    filesystem) — Tier B trusts you not to do that. Use only when
    there's an ongoing operational need that genuinely can't be served
    by deploy-and-observe-logs cycles (e.g. you regularly need to
    `docker exec` for forensics on a class of issues that doesn't
    surface in container logs). "I want to poke around and see what's
    there" is not a Tier B justification.
  - **Tier C:** full sudo. Co-founder / ops-partner only. Not handed
    to app devs.

  **Default to Tier A.** If you think you need Tier B, send ops a
  one-paragraph justification and ops decides. Most "I need SSH"
  intuitions evaporate once the CI pipeline is wired and the dev
  realizes deploy logs + container logs cover what they actually
  needed.
- **Deploy is pull-only from GitHub** via a read-only deploy key
  specific to the repo. The droplet can pull from your repo. It cannot
  push. You push via your normal GitHub workflow; the droplet catches up
  on the next deploy.
- **No CI runs on the droplet.** CI runs in GitHub Actions (or your CI
  of choice). The droplet is runtime, not build.
- **DNS is not managed here.** The sysadmin points DNS after your app is
  running. Don't assume you control the A record.
- **There is no staging.** This is production. If you want
  staging/preview environments, run them elsewhere (your laptop,
  Vercel's free tier, fly.io, etc.) and only bring tested builds here.
- **The host does not run your framework's CLI.** No `pnpm` / `yarn` /
  `poetry` installed on the host. Everything happens inside your
  container.
- **Your app is behind a reverse proxy — `req.url` lies.** Inside your
  container, the server is bound to `0.0.0.0:3000` (or equivalent).
  Caddy in front of it forwards the real public Host + Proto via
  headers, but Next.js (and similar frameworks) often construct
  internal URLs using `HOSTNAME` / `PORT` env vars rather than the
  inbound headers. **Never build redirect or callback URLs from
  `req.url`, `req.nextUrl.toString()`, or any "current URL" API — they
  leak the internal `0.0.0.0:3000` into user-facing responses.**

  ```typescript
  // WRONG — produces https://markdownparts.com:3000/ in redirects
  const url = new URL(req.url);
  url.host = "markdownparts.com";
  return NextResponse.redirect(url);

  // WRONG — callbackUrl becomes https://0.0.0.0:3000/
  const callbackUrl = req.nextUrl.toString();

  // RIGHT — build URLs from the public-facing host Caddy forwarded
  const host = req.headers.get("host") ?? "";
  const proto = req.headers.get("x-forwarded-proto") ?? "https";
  const callbackUrl = `${proto}://${host}${req.nextUrl.pathname}${req.nextUrl.search}`;
  return NextResponse.redirect(`${proto}://${host}/login?callbackUrl=${encodeURIComponent(callbackUrl)}`, 307);
  ```

  This bit mark-down-parts on launch — the middleware worked
  functionally but browsers couldn't follow the `0.0.0.0:3000`
  redirect target. Symptom: login form loads, you type credentials,
  sign-in "succeeds" server-side, then the browser tries to follow
  the callbackUrl and fails to connect.

- **If your app serves multiple hostnames from one container, the app
  is what differentiates — not Caddy.** Ops can point as many
  hostnames as you want at the same loopback port. Your code decides
  what each hostname renders. Typical pattern in Next.js:

  ```typescript
  // src/middleware.ts
  export function middleware(req: NextRequest) {
    const host = (req.headers.get("host") ?? "").toLowerCase();
    const { pathname } = req.nextUrl;

    // www → apex redirect
    if (host === "www.example.com") {
      return NextResponse.redirect(
        `https://example.com${pathname}${req.nextUrl.search}`, 308
      );
    }

    // Staff subdomain: auth-gate at root
    if (host === "app.example.com" && pathname === "/") {
      const session = req.cookies.get("authjs.session-token");
      if (!session) {
        return NextResponse.redirect("https://app.example.com/login", 307);
      }
    }

    // Public apex: let / → /shop (or whatever your normal root handler does)
    return NextResponse.next();
  }
  ```

  Tell ops upfront which hostnames you want and what each should
  render. Ops writes the Caddy site block(s); you write the
  middleware.

## What "ready to deploy" looks like from the sysadmin's perspective

When your code is ready to go live, ops needs every item below from you.
If giving this document to you is the only conversation ops and you
have before go-live, the deploy should still succeed — so nothing is
left implicit.

### The hand-off checklist

1. **Repo access**
   - Repo URL (e.g. `github.com/owner/repo`)
   - Public, or private? If private, ops generates a deploy keypair on
     the droplet and sends you the public key to paste at GitHub
     *Settings → Deploy keys → Add* (read-only).
   - Which branch to deploy from (usually `main`).

2. **Dockerfile + docker-compose.yml**
   - Both in the repo root.
   - Docker Compose file must include `deploy.resources.limits` for
     CPU and memory on every service (see Hard rule 6). Deploys
     without limits are rejected.
   - App ports bound to `127.0.0.1:<loopback-port>` (see Hard rule 1).
     Pick an unused port from the allocation table in
     [sysadmin.md](../sysadmin.md).
   - Persistent state (DB files, uploads) in named volumes, not
     bind-mounts under the repo.

3. **Build strategy**
   - **Default: CI builds the image and pushes to a registry.**
     Typically GitHub Actions → `ghcr.io/<owner>/<repo>:<tag>`. Your
     compose references `image: ghcr.io/...`. Tell ops whether the
     image is public or private — if private, ops needs a pull token
     (GitHub PAT with `read:packages`). Ops runs `docker compose pull && docker compose up -d`
     to deploy.
   - **Why this is the default, not a choice for most stacks:**
     On-box `docker compose up --build` is off-limits for any app
     whose build peaks above ~500 MB resident. That rules out
     essentially every Node-based framework (Next.js, Nuxt, SvelteKit,
     Remix), most Python projects that compile wheels (SciPy, Pillow,
     Pandas), Rust, and Go with heavy linking. On this 4 GB box
     sharing RAM across 3+ tenants, a `next build` can OOM-kill the
     whole droplet — not just your container. We won't take that
     risk.
   - **On-box build is ONLY acceptable for:**
     - Static-site generators with pre-built output already in the
       repo (Hugo, Eleventy, Astro if you committed `dist/`)
     - Alpine-based tiny runtimes assembling a few files
     - Build processes you've measured at <400 MB peak and declared
       to ops in advance
   - **Tagging:** whatever strategy, tags must be immutable and
     traceable. `:<git-sha>` plus `:latest` as a floating alias is
     the common pattern. Do not deploy `:latest` alone to production
     — rollback requires the old sha to still be on the registry.

4. **Env vars**
   - Committed `.env.example` in the repo listing every required
     variable with a one-line description of what each does.
   - For any secret value (DB root password, API keys, session
     secret, etc.), flag whether ops generates it (`openssl rand`)
     or you provide it out-of-band.
   - Ops puts real values in `/srv/secrets/<slug>.env` on the
     droplet; your compose references it via `env_file:`.

   **How to hand a secret to ops:**
   - **Acceptable:** 1Password / Bitwarden / Dashlane shared vault
     entry, Signal, ProtonMail, age-encrypted file
     (`age -r <ops-pubkey> -o secret.age`). Anything end-to-end
     encrypted where only ops can decrypt.
   - **Not acceptable:** plain email, plain Slack / Discord /
     Teams / SMS, any chat without E2E, any file in the repo (even
     private repos — collaborators, CI artifacts, GitHub support can
     all reach them), screenshots (shoulder surfing + OCR is trivial).
   - **Values ops generates (you don't need to provide):** anything
     with no external meaning — DB passwords, session cookies,
     CSRF tokens, JWT signing keys. Tell ops the env var names;
     ops generates with `openssl rand -base64 24` (or similar) and
     inserts.
   - **Values you must provide:** third-party API keys and client-
     furnished credentials where ops can't generate them (Stripe,
     Resend, Azure Vision, SendGrid, OpenAI, etc.).

5. **Database bootstrap and schema**
   - *If your app uses a DB:*
   - The exact command that creates the initial schema on a fresh
     DB (e.g. `docker compose exec app npx prisma migrate deploy`,
     `docker compose exec app python manage.py migrate`,
     `docker compose exec app rails db:schema:load`). Ops runs this
     once at first deploy, and on every subsequent deploy that
     introduces migrations.
   - The migration command must be **idempotent** — safe to run
     against an already-migrated DB. Use `migrate deploy` / `upgrade head`
     style, never interactive `migrate dev`.
   - How your app behaves if migrations aren't up to date yet (fails
     loudly preferred — silent corruption is bad).

6. **Seed data + first-login access**
   - *If your app has auth or requires any seeded rows to function:*
   - A one-shot script (or docker-compose service with
     `profiles: [seed]`) that creates the first admin user, inserts
     essential reference data, etc. Must be runnable non-interactively.
   - Initial admin credentials: tell ops out-of-band (not in the
     repo). Ops will log in, change the password, hand over to the
     client.

7. **Public hostname(s)**
   - Primary hostname and any aliases (usually `app.example.com` and
     maybe `www.app.example.com` redirecting to primary).
   - Ops handles DNS + Caddy site file + TLS cert.

8. **Backups (if stateful)**
   - A drop-in at `scripts/backup.d/<slug>.sh` in the *Hosting* repo
     (or send it to ops to commit there) that dumps whatever state
     must survive a droplet loss. Typically:
     - `mariadb-dump` / `pg_dump` / `sqlite3 .backup` of your DB(s)
     - `tar` of any named volume holding user uploads
   - Restore notes at the top of the drop-in (for a future
     restore-from-zero operation).
   - See [backup.md § Adding a drop-in](./backup.md#adding-a-drop-in-for-a-new-app)
     for the convention.

   **DB-specific command-name gotchas:**
   - **MariaDB 11.0+** removed the `mysql` and `mysqldump` compat
     symlinks. Use `mariadb` and `mariadb-dump` instead. If your
     drop-in uses `mysqldump`, it will fail with exit 127 ("command
     not found") at backup time.
   - **MariaDB 10.x** still has both names — use whichever.
   - **MySQL 8** uses `mysqldump` / `mysql` — no rename issue.
   - **PostgreSQL** uses `pg_dump` / `psql` in every recent version.
   - Always match the command name to the image tag you actually
     shipped in `docker-compose.yml`.

9. **Rollback plan**
   - What's the command sequence to revert to a known-working version
     if a deploy breaks production?
   - For (A) CI-push: `docker compose pull <old-tag> && docker compose up -d`
     with a previous image tag. Requires that you tag images
     meaningfully (git sha, timestamp, or semver) and don't just
     use `:latest`.
   - For (B) on-box build: `git checkout <prev-commit> && docker compose up -d --build`.
   - Either is fine — ops just needs to know which.

10. **(Optional but strongly encouraged) Healthcheck endpoint**
    - A URL path like `/healthz` or `/health` that returns 200 when
      the app is healthy, non-2xx when not. Ops adds an UptimeRobot
      monitor against it (see [management-tools.md](./management-tools.md)).
    - If your framework has a built-in one (Next.js doesn't, Spring
      Actuator does), great; otherwise a trivial handler is fine.

### Shortest valid hand-off message to ops

This is the **initial onboarding** message — first time an app gets
deployed. For ongoing per-PR deploy messages, follow
[deploy-handoff-template.md](./deploy-handoff-template.md) instead.

> Repo: github.com/<owner>/<repo>, private, deploy from `main`.
> Build strategy: CI pushes to ghcr.io/<owner>/<repo>:<tag>, public images.
> Hostname: `<app>.<example.com>`.
> DB: MySQL 8. Bootstrap: `docker compose exec app npx prisma migrate deploy`.
> Seed admin: `docker compose exec app npm run seed:admin`. I'll send
> initial password via Signal.
> Env vars: see `.env.example` in repo.
> Backups: `scripts/backup.d/<slug>.sh` in the Hosting repo (I PR'd it).
> Rollback: revert in git, then `ssh osh-vps-deploy 'sudo /srv/scripts/deploy.sh <app>'`
> (the script handles SHA-pinned pull + revision verification — see
> [deploying-apps.md](./deploying-apps.md)).
> Health: `GET /healthz` returns 200.

If you can't fill in all of the above, flag what's missing so ops can
ask questions rather than discovering gaps at deploy time.

### Per-PR deploy messages

After onboarding, every PR you ship needs a small handoff message so
ops can deploy. Use the template at
[deploy-handoff-template.md](./deploy-handoff-template.md). The short
version: invoke `deploy.sh` (not raw `docker compose pull`), name the
new env vars without pasting their values in chat, and provide a
verification command that actually exercises your new code.

## Choosing a database engine

You pick the DB your app runs (MySQL, Postgres, SQLite, whatever). Ops
doesn't mandate a specific engine — but here's the honest resource cost
so you pick with eyes open on a 4 GB box.

| Engine | Resident memory at idle | Good for | Caveats |
| ---    | ---                     | ---      | ---     |
| SQLite (file on a named volume) | ~0 MB (in-process) | Low-concurrency apps, single-writer workloads, content/config DBs | Concurrent writes serialize; no network access |
| Postgres 16 | ~300 MB idle, typically 400–700 MB under load | JSONB, full-text, trigram fuzzy search, graph-ish queries, rich types | ~2–3× the memory footprint of a comparable MySQL |
| MySQL 8 | ~200 MB idle, typically 300–500 MB under load | Standard OLTP, well-known tooling, Prisma's default | Less expressive than Postgres for semi-structured data |
| MariaDB 10 | ~150 MB idle | Drop-in MySQL; slightly lighter | Minor feature divergence from MySQL, usually irrelevant |

**Running two different engines on this box (e.g. Postgres + MySQL) costs
500–1000 MB combined** before you've stored any real data. That's 15–25%
of the whole droplet. It's possible but it compresses everything else.
If two tenants each want a relational DB, prefer having them share an
engine (one Postgres with multiple DBs, one MySQL with multiple DBs) over
running two engines.

**For greenfield apps:** pick Postgres unless you have a specific reason
to use something else. The JSONB + full-text + trigram features pay for
themselves in app code you don't have to write.

**For existing Prisma + MySQL apps:** the migration to Postgres is a
schema rewrite but not usually painful. Consider it if you're going to
add another Postgres-preferring tenant to the box anyway.

**Don't run a separate DB container per app "for isolation"** unless you
have real security requirements (you probably don't; everything on the
box is on the same kernel). Two apps on one Postgres instance with
separate database names uses half the RAM of two separate Postgres
containers.

## Out of scope for this server

Don't build an app that needs any of these here:

- GPU / CUDA / ML training or large-scale inference
- Dedicated RAM above ~2 GB sustained per service (you're sharing)
- More than a few hundred concurrent users per app (not a hard limit,
  but vertical scaling means we go up one DO plan at a time)
- Real-time / soft-real-time guarantees (SLA-adjacent work)
- Compliance regimes with dedicated-host requirements (HIPAA, PCI,
  FedRAMP — this is shared infrastructure)
- WebSockets at high fan-out — works fine for moderate loads, but Caddy
  + single box has a ceiling
- Anything that needs a `cap_add: [ NET_ADMIN ]` or privileged container

If your app really needs one of those, it's the wrong box — talk to
Darrell early so you're not surprised at deploy time.

## Read next

- [hosting-pattern.md](./hosting-pattern.md) — step-by-step checklist
  for adding a new app (sysadmin-side)
- [backup.md](./backup.md) — how to write and test your backup drop-in
- [sysadmin.md](../sysadmin.md) — state of the server, port allocations,
  version table
