# romelegion.org

The new website for **Shanklin Attaway American Legion Post 5** in Rome, Georgia.
Replaces the existing Legionsites-hosted site at https://romelegion.org/.

## What this is

- **Static site** built with [Hugo](https://gohugo.io/) (extended)
- Hosted on our existing VPS via the shared `websites` tenant pattern
  (see [`runbooks/`](./runbooks/))
- Caddy on the host serves static files from `/srv/www/romelegion.org/` and
  routes the two form endpoints to PHP-FPM at `127.0.0.1:9000`
- [Resend](https://resend.com) sends the form-submission emails (no SMTP
  daemon on the box)
- GitHub Actions builds and deploys on every push to `main`, plus a daily
  cron rebuild so the past/upcoming event split stays current

```
┌─────────────────┐      ┌────────────┐      ┌──────────────────┐
│ Push to main    │─────▶│ GH Actions │─────▶│ rsync to VPS     │
│ (or daily cron) │      │ hugo build │      │ /srv/www/...     │
└─────────────────┘      └────────────┘      └──────────────────┘
                                                       │
                                                       ▼
                                        ┌─────────────────────────────┐
                                        │ Caddy serves static          │
                                        │ + routes .php to PHP-FPM     │
                                        │ + PHP-FPM emails via Resend  │
                                        └─────────────────────────────┘
```

---

## Quick start (local dev)

You'll need:
- **Hugo extended** ≥ 0.140 (`apt`, `brew`, or
  [GitHub releases](https://github.com/gohugoio/hugo/releases))
- **Docker + Docker Compose** (only if you want to test the PHP forms locally)

```bash
make dev          # Hugo dev server with live reload — http://localhost:1313
make build        # Production build (TODO banners hidden) → ./public
make help         # List all targets
```

Live reload is fast (~80ms per change). Edit a markdown file, save, and your
browser refreshes.

### Testing forms locally

The contact and membership forms POST to PHP endpoints that talk to Resend.
Hugo's dev server doesn't run PHP. To test them end-to-end:

```bash
# One-time setup
cp dev/secrets/romelegion.org.env.example dev/secrets/romelegion.org.env
# Edit dev/secrets/romelegion.org.env, paste in a dev Resend key

make dev-forms    # Builds the site, starts Caddy + PHP-FPM in docker-compose
                  # Site is served at http://localhost:8080 (no live reload here)
```

For day-to-day content editing, `make dev` is what you want. Reach for
`make dev-forms` only when actually exercising form code.

---

## Layout

```
.
├── content/                   Markdown content for every page
│   ├── _index.md              homepage
│   ├── about/
│   ├── events/                each event is one .md file
│   ├── family/                Auxiliary, SAL, Riders
│   ├── membership/
│   └── ...
├── data/
│   └── officers.yaml          Post officer roster (used by shortcodes)
├── layouts/
│   ├── _default/              fallback list / single templates
│   ├── events/                custom list, single, and .ics templates
│   ├── partials/              header, footer, head, event-card, etc.
│   └── shortcodes/            {{< officers >}}, {{< figure-image >}}, etc.
├── assets/
│   ├── css/                   main.css (Hugo Pipes minifies + fingerprints)
│   └── images/                originals; Hugo generates responsive WebP+JPG
├── static/                    files served verbatim (favicon, robots.txt, JS)
├── forms/                     PHP form handlers (deployed to /_form/ on VPS)
│   ├── _shared.php            validation, rate-limit, Resend wrapper
│   ├── contact.php
│   └── application.php
├── caddy/sites/
│   └── romelegion.org.caddy   Site block — OPS copies to /etc/caddy/sites/
├── docker/
│   └── Caddyfile.dev          Used only by docker-compose.dev.yml
├── docker-compose.dev.yml     Local PHP-FPM + Caddy for form testing
├── dev/secrets/               Gitignored. Dev-only Resend keys.
├── .github/workflows/
│   └── deploy.yml             Build → rsync to VPS on push to main
├── runbooks/                  VPS conventions (read these before deploying)
├── site-inventory.md          What the existing romelegion.org has
├── QUESTIONS-FOR-POST.md      Content gaps to fill before launch
└── hugo.toml                  Site config
```

---

## Authoring content

### Adding an event

Drop a new file in `content/events/`:

```yaml
---
title: "Veterans Day Ceremony"
date: 2026-11-11T11:00:00-05:00
endDate: 2026-11-11T12:00:00-05:00
location: "Myrtle Hill Cemetery — Rome, GA"
contact: "Albert Hollis · (951) 204-8635"
description: "Annual Veterans Day ceremony at Myrtle Hill."
---

Markdown body — full event description, what to expect, etc.
```

Push to `main` and GH Actions deploys it. The event automatically gets:
- A detail page at `/events/<slug>/`
- An `.ics` download at `/events/<slug>/event.ics`
- An "Add to Google Calendar" link
- A card on the homepage (if it's one of the next 4 upcoming)
- A card on `/events/` (Upcoming or Past, depending on whether `date` is past)

### Adding a page

```bash
hugo new content content/about/scholarships.md
# edit the front matter title/description, write your body
```

### Adding a photo album

Each album is a **page bundle** — a folder under `content/gallery/`. Drop the
photos directly into the folder and Hugo handles the rest (responsive WebP +
JPG variants at multiple widths via the same pipeline as the hero).

```bash
# 1. Make a folder named for the URL slug you want
mkdir content/gallery/memorial-day-2026/

# 2. Drop photos into it (any JPG/PNG; high-res originals are fine — Hugo
#    resizes them at build time)
cp ~/Downloads/IMG_*.jpg content/gallery/memorial-day-2026/

# 3. Create an index.md with the album metadata
```

`content/gallery/memorial-day-2026/index.md`:

```yaml
---
title: "Memorial Day 2026"
date: 2026-05-25
description: "Annual Memorial Day observance at Myrtle Hill Cemetery."
cover: "IMG_1023.jpg"      # optional — defaults to first image
resources:
  - src: "IMG_1023.jpg"
    title: "Posting of the colors"
  - src: "IMG_1024.jpg"
    title: "Three-volley salute"
  - src: "IMG_1025.jpg"
    title: "Wreath laying ceremony"
---

Optional markdown body describing the event. Renders above the photo grid.
```

The album will appear on `/gallery/` (sorted by `date`, newest first) and each
photo will be clickable to open a full-size view in the lightbox. Captions
come from the `title` field of each resource.

### Updating officer info

`data/officers.yaml` — single source of truth. The roster on the About page
and the contact form routing dropdown both read from here.

### TODO markers

Pages still missing real content from the post are wrapped in:

```
{{< todo >}}
Notes about what's needed.
{{< /todo >}}
```

These appear as yellow banners in dev/staging builds. In **production builds**
(`make build`) they're hidden — so launching the site doesn't expose them to
visitors, but reviewers and editors can still see them.

---

## Deploy

The workflow `.github/workflows/deploy.yml` has two jobs:

1. **build** — always runs on every push to `main`. Builds with Hugo, bundles
   the PHP endpoints, uploads the result as a workflow artifact. Catches any
   regression that would break the production build before it hits the VPS.
2. **deploy** — runs only when the repo variable `DEPLOY_ENABLED` is set to
   `"true"`. rsyncs the build artifact to the VPS.

### Why deploys are off right now

OPS hasn't provisioned the deploy SSH key yet, and the new site isn't ready to
go live anyway. Every push to `main` runs **build** and stops there — you get
a green check confirming the site still compiles.

### Enabling deploys

When OPS provisions credentials, set five repo secrets and flip the variable:

```bash
gh secret set DEPLOY_SSH_KEY     -R howarthTech/legion-rome < ~/.ssh/deploy_key
gh secret set DEPLOY_KNOWN_HOSTS -R howarthTech/legion-rome   # paste known_hosts
gh secret set DEPLOY_HOST        -R howarthTech/legion-rome --body "osh-vps-deploy"
gh secret set DEPLOY_USER        -R howarthTech/legion-rome --body "claude"
gh secret set DEPLOY_PATH        -R howarthTech/legion-rome --body "/srv/www/romelegion.org/"

# Flip the toggle
gh variable set DEPLOY_ENABLED   -R howarthTech/legion-rome --body "true"
```

The next push to `main` will build **and** deploy.

| Secret | What it is |
| --- | --- |
| `DEPLOY_SSH_KEY` | Private key for the deploy user on the VPS |
| `DEPLOY_KNOWN_HOSTS` | `ssh-keyscan -p 2222 <vps-host>` output |
| `DEPLOY_HOST` | SSH host alias (e.g. `osh-vps-deploy`) |
| `DEPLOY_USER` | Deploy user (e.g. `claude`) |
| `DEPLOY_PATH` | rsync destination (`/srv/www/romelegion.org/`) |

Manual run: **Actions** tab → "Build & Deploy" → "Run workflow."

### Grabbing the production build without deploying

Even with deploy disabled, every successful build uploads `public/` as a
workflow artifact you can download from the Actions UI. Useful for previewing
the production build (TODO banners hidden) without setting anything up.

---

## Production OPS contract

This site lives in the shared `websites` tenant. Constraints from
[`runbooks/for-app-developers.md`](./runbooks/for-app-developers.md):

- Static files in `/srv/www/romelegion.org/`, owned by the deploy user
- Form endpoints exec under the shared PHP-FPM container on `127.0.0.1:9000`
- Resend API key in `/srv/secrets/romelegion.org.env` (mode 600, root-owned)
- The site doesn't run a long-lived process of its own — no port reservation,
  no Docker container in `/srv/apps/`, no resource limits required
- Caddy block in `/etc/caddy/sites/romelegion.org.caddy` (source of truth:
  [`caddy/sites/romelegion.org.caddy`](./caddy/sites/romelegion.org.caddy))

---

## Open questions

See [QUESTIONS-FOR-POST.md](./QUESTIONS-FOR-POST.md) for content gaps
awaiting answers from Albert Hollis, Will Adams, Louise Burgess, and others.
