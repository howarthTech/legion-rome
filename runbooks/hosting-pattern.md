# Hosting Pattern — osh-vps

How to deploy a new containerized app on the droplet. The server is set up so
that each app is self-contained under `/srv/apps/<name>/`, binds to a unique
loopback port, and is reverse-proxied by host-level Caddy with auto-HTTPS.

---

## What's already on the droplet

- Docker 29.x + Docker Compose v5.x (non-root group: `darrell`, `claude`)
- Caddy 2.11 on host, systemd-managed, serving `80/443`
- ufw: only `2222/80/443` open
- fail2ban on sshd (port 2222)
- unattended-upgrades (security patches)
- 2 GB swap (`vm.swappiness=10`)
- Timezone `America/Denver`

## Conventions

### Directory layout

```
/srv/apps/<name>/        # repo + docker-compose.yml; owned by darrell:darrell
/srv/secrets/<name>.env  # secrets file, mode 600, out of the repo
/srv/backups/            # local staging for backups before rclone push
/srv/scripts/            # ops scripts (backup.sh, etc.)
```

### Port allocation

Apps bind to loopback only. **Public ports are Caddy's exclusive territory.**

| App              | Host port  | Status |
| ---              | ---        | ---    |
| mark-down-parts  | `127.0.0.1:3000` | reserved (not yet deployed) |
| appliancepartsindex-api | `127.0.0.1:3001` | reserved (not yet deployed; may be internal-only, see below) |
| project-gmac     | `127.0.0.1:8000` | reserved (not yet deployed) |
| websites (PHP-FPM, shared across 1–5 small sites) | `127.0.0.1:9000` | running |

Pick the next free port when adding a new app. Document it here.

**Note:** loopback ports are only needed when Caddy has to reverse-proxy
the service to a public hostname. Internal-only services that are
consumed by other containers (e.g. a private API that only another app
talks to) should skip loopback binding entirely and use a shared Docker
network instead — see "Tenant shapes" below.

### Tenant shapes

One-compose-per-concept is the default, but two patterns come up in practice:

**Single-tenant app.** One app, one `/srv/apps/<slug>/docker-compose.yml`, one budget row. This is the simplest and fits most apps. Example: project-gmac.

**Sibling tenants, tightly coupled.** Two apps that are separate concepts but consume each other — e.g. a frontend app and its backend API where the API may later serve other clients too. Each gets its own directory under `/srv/apps/`, its own compose file, its own secrets file, its own budget row, and its own GitHub repo. They can still talk to each other over a shared Docker external network (see below).

  Use this when:
  - The components deploy / restart on different schedules
  - One might eventually scale or be replaced independently
  - The API might serve other clients in the future (not just this frontend)

**Avoid:** a single compose file with a frontend service AND a backend service AND a DB "because they're related." That tightly couples deploy windows and failure modes and doesn't scale past the initial pair.

### Internal-only services (no Caddy, no public port)

When an app service is only consumed by another container on the same host, expose it **only** on a shared Docker network — no loopback binding, no Caddy block.

1. Create a stable external network once:
   ```bash
   docker network create --driver bridge osh-internal
   ```
2. Each compose file attaches the relevant services to it:
   ```yaml
   services:
     api:
       image: ghcr.io/.../appliancepartsindex-api:sha
       networks: [osh-internal, default]
       # NO `ports:` entry -- service is reachable only by other containers
       # that share the osh-internal network, via http://api:3001/
   networks:
     osh-internal:
       external: true
   ```
3. Consumer compose files also join `osh-internal` and address the
   service by its service name on that network (e.g. `http://api:3001/`).

**Advantages over public loopback + Caddy:**
- No ufw rule, no Caddy route, no TLS overhead for internal calls
- Not reachable from the host network or externally — even a compromised other tenant on the box can't hit it without being on the same Docker network
- Promotes to public later trivially: add a `ports: ["127.0.0.1:3001:3001"]` entry and a Caddy site block; no code change in the service

Use internal-only when the service has no real external consumer. Use
public (loopback + Caddy block) when external clients, admin UIs, or
human-visible documentation need to reach it.

### Caddy site blocks

One file per public hostname in `/etc/caddy/sites/*.caddy`. Source-of-truth
lives in this repo at [caddy/sites/](../caddy/sites/). To add a new site:

1. Copy [`_template.caddy.example`](../caddy/sites/_template.caddy.example) to
   `<hostname>.caddy` in this repo's `caddy/sites/` folder.
2. Edit: real hostname, real loopback port, tune headers/encoding as needed.
3. Copy it to the droplet:
   `scp caddy/sites/<hostname>.caddy osh-vps:/tmp/ && ssh osh-vps 'sudo mv /tmp/<hostname>.caddy /etc/caddy/sites/ && sudo chown caddy:caddy /etc/caddy/sites/<hostname>.caddy'`
4. **Point DNS at 165.227.26.223 *before* reloading**, or Let's Encrypt will
   fail cert issuance and rate-limit the domain for a week.
5. `ssh osh-vps 'sudo caddy validate --config /etc/caddy/Caddyfile && sudo systemctl reload caddy'`
6. `curl -I https://<hostname>` — expect 200 + valid cert.

---

## Adding a new app (full checklist)

1. **GitHub deploy key** (if pulling source from a private repo)
   - On the droplet as `darrell`:
     `ssh-keygen -t ed25519 -f ~/.ssh/gh_<appname> -N "" -C "deploy:<appname>@osh-vps"`
   - Add `~/.ssh/gh_<appname>.pub` as a read-only deploy key on the GitHub repo.
   - Append an ssh config entry:
     ```
     Host github-<appname>
       HostName github.com
       User git
       IdentityFile ~/.ssh/gh_<appname>
       IdentitiesOnly yes
     ```
2. **Clone**: `cd /srv/apps && git clone git@github-<appname>:<owner>/<repo>.git <name>`
3. **GHCR pull access (if the app uses `image: ghcr.io/...` from a *private* package)** — the deploy key above only covers source code. Container registry access is separate. See "Private GHCR images" section below.
4. **Secrets**: create `/srv/secrets/<name>.env` (mode 600), reference from compose via `env_file:`.
5. **docker-compose.yml** (inside the app repo): bind app to `127.0.0.1:<port>:<app-port>`.
   DO NOT publish to `0.0.0.0` — that bypasses Caddy.
6. **Validate compose locally first**: `docker compose config --quiet` — catches the `pids_limit` conflict and other syntax issues before deploy.
7. **Bring it up**: `cd /srv/apps/<name> && docker compose pull && docker compose up -d` (for CI-built images) OR `docker compose up -d --build` (only for approved on-box builds; see [for-app-developers.md § 3](./for-app-developers.md)).
8. **Caddy site block**: follow the "Caddy site blocks" section above.
9. **DNS**: A record for `<hostname>` → `165.227.26.223`.
10. **Hook backup** (if app has state): commit the drop-in to `scripts/backup.d/<slug>.sh` in this Hosting repo + deploy to `/srv/scripts/backup.d/`. See [backup.md](./backup.md).
11. **Update [the port table](#port-allocation) in this doc** and the hosted-apps table in [sysadmin.md](../sysadmin.md).

## Private GHCR images

When the app's compose references `image: ghcr.io/<owner>/<repo>:<tag>` and the **package** (not the source repo) is private, the droplet needs a GitHub Personal Access Token to pull. SSH deploy keys don't work here — they authenticate git operations, not the Docker Registry HTTPS protocol.

### Setup (one-time per GHCR-private app)

1. **Generate a classic PAT** at https://github.com/settings/tokens
   - Note: `osh-vps ghcr read <appname>`
   - Expiration: 1 year (the max without "no expiration")
   - Scopes: **only** `read:packages` — no `repo`, no `admin:*`, nothing else
2. **Deliver the PAT to ops** via any E2E-encrypted channel — or paste directly into the ops session if ops is Claude
3. **Configure `docker login` on the droplet as the user that will run deploys** (usually `claude`):
   ```bash
   echo "<PAT>" | docker login ghcr.io -u <github-username> --password-stdin
   ```
   Credentials land in `~/.docker/config.json` (mode 600 by default). Docker will emit a warning about "unencrypted credentials" — this means base64-encoded, not encrypted. Acceptable for a server with single-user docker socket access.
4. **Test the pull**: `docker pull ghcr.io/<owner>/<repo>:latest` should succeed without prompting.

### Alternative — make the package public

If the image doesn't contain anything sensitive (built Next.js apps typically don't; secrets come from env at runtime), flip the package visibility to Public at `github.com/<owner>/packages/container/<appname>` → Package settings → Danger zone → Change visibility → Public. No droplet config needed. This is the default for most open-source or low-sensitivity apps.

### Rotation

PATs expire at most 1 year after creation. Calendar reminder ~30 days before expiry to:
1. Generate a new PAT with the same scopes
2. Run `docker login ghcr.io -u <username> --password-stdin` again (overwrites the config)
3. Revoke the old PAT at https://github.com/settings/tokens

Document the expiry date in [sysadmin.md](../sysadmin.md) under "External credentials."

### Docker auth for sudo-based compose operations

`docker login` writes credentials to `$HOME/.docker/config.json` — so
`claude` logging in writes to `/home/claude/.docker/config.json`. That
works for `docker pull` as `claude` directly, but **`sudo docker pull`
uses root's config at `/root/.docker/config.json` and will 401 if you
skipped a root-level login**.

Compose operations for any app that reads `/srv/secrets/<slug>.env`
(which is 600 root-owned by policy) need to run under sudo, so root
needs GHCR creds too. Easiest: mirror `claude`'s config to root rather
than re-login (avoids pasting the PAT twice):

```bash
ssh osh-vps '
  sudo install -d -m 700 -o root -g root /root/.docker
  sudo install -m 600 -o root -g root /home/claude/.docker/config.json /root/.docker/config.json
'
```

After this `sudo docker pull`, `sudo docker compose pull`, and the full
deploy sequence all work. Re-run if the PAT rotates.

---

## Recreating a single service without cascading

`docker compose up -d --force-recreate <service>` cascades to
`depends_on` services unless you pass `--no-deps`. That means
`--force-recreate app` will also recreate `db` — harmless for named-
volume state (the DB volume persists), but wastes a 30-second MariaDB
init cycle and bounces connections.

**For post-deploy app updates (new image from CI), prefer:**

```bash
sudo docker compose pull app
sudo docker compose up -d --force-recreate --no-deps app
```

The DB keeps running, only the app container is swapped.

**When you DO want to recreate everything** (e.g. after editing
`docker-compose.yml` itself, not just the image):

```bash
sudo docker compose up -d --force-recreate    # no --no-deps
```

## Running one-off scripts when the runtime image is thin

Modern framework runtimes (Next.js standalone, Nuxt minimal, etc.)
often ship without the build toolchain, which means `npx prisma
migrate deploy` and `npx tsx scripts/seed.ts` may fail with
`command not found` or `ERR_MODULE_NOT_FOUND`. Dev should fix this at
the Dockerfile level (see [for-app-developers.md § 9](./for-app-developers.md))
— but for a blocking first deploy, there's a fallback.

**If the runtime image has the deps but lacks the CLI:** run the
script logic inline via `node -e` using the packages that ARE baked in.
This bypasses the missing CLI. Example: `seed-admin.ts` that needs
`tsx` → rewrite as `node -e` using `@prisma/client` + `bcryptjs`
directly (both are typically in the runtime deps for a typical CRUD
app):

```bash
sudo docker compose exec -T \
  -e FIRST_ADMIN_EMAIL=user@example.com \
  -e FIRST_ADMIN_PASSWORD=<pw> \
  app node -e '
    const { PrismaClient } = require("@prisma/client");
    const bcrypt = require("bcryptjs");
    const prisma = new PrismaClient();
    (async () => {
      const email = process.env.FIRST_ADMIN_EMAIL;
      const password = process.env.FIRST_ADMIN_PASSWORD;
      const existing = await prisma.user.findUnique({ where: { email } });
      if (existing) { console.log("exists"); return; }
      const passwordHash = await bcrypt.hash(password, 12);
      const u = await prisma.user.create({ data: { email, passwordHash, role: "admin", name: "Admin" }});
      console.log("created:", u.email);
    })().finally(() => prisma.$disconnect());
  '
```

**When this is acceptable:** one-off bootstrap / seed / migration
steps on first deploy, where dev is aware and fixing the runtime image
for future deploys. **When it is NOT:** as an ongoing pattern. Every
one-off `node -e` is a hint that the runtime image is under-specified.
Tell dev to patch the Dockerfile so the next deploy doesn't need the
workaround.

## Apps serving multiple hostnames from one container

Caddy can point any number of hostnames at the same loopback port —
the app code differentiates via Host header. When a dev tells you
"`apex.example.com` should show X, `app.example.com` should show Y,
`www.*` should redirect to apex," that's:

1. **Ops side:** one Caddy site block per hostname group, all
   reverse-proxying to the same `127.0.0.1:<port>`. Can be combined
   into a single block if the headers/encode/etc. are identical:
   ```
   example.com, www.example.com, app.example.com {
       reverse_proxy 127.0.0.1:3000
       encode zstd gzip
   }
   ```
   Or split into multiple files under `caddy/sites/` if different
   sites want different headers / redirects / max upload sizes.

2. **Dev side:** Next.js middleware (or equivalent) reads
   `req.headers.get("host")` and routes accordingly. Dev owns the
   middleware; ops does NOT rewrite hosts at the Caddy layer — always
   the app's responsibility. See
   [for-app-developers.md § "If your app serves multiple hostnames"](./for-app-developers.md)
   for the pattern.

3. **First cert issuance** will iterate over every hostname in the
   site block. Expect to see multiple "certificate obtained
   successfully" lines in Caddy's log. If one hostname's DNS isn't
   live yet, that one fails (and Caddy will retry) while others
   succeed — not a block for the live ones.

---

## Things the app team owns (not this repo)

- Dockerfile, docker-compose.yml, .dockerignore — in the app repo
- Prisma/SQL migrations, seed scripts, first admin user — in the app repo
- Build strategy (build on droplet vs pull prebuilt image from a registry) — app team's call
- Memory tuning for Node builds — recommend `NODE_OPTIONS=--max-old-space-size=3072`
  on this 4 GB box, or build off-box and pull
- App-level logging format

## Things this repo owns

- Caddyfile + per-site blocks
- Backup script skeleton and its cron
- Disaster recovery runbook
- This pattern doc
- sysadmin.md (server state of record)

---

## Before first real cert issuance

Edit `/etc/caddy/Caddyfile` and set:

```
{
    email you@yourdomain.com
}
```

Without it, Let's Encrypt uses an anonymous account and there's no expiry
notification channel. This is the one config item you definitely want set
before going live.
