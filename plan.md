# Project — Legion Post Platform (SaaS)

The source of truth for **what this project is** and **what it's supposed to be**.

If you're looking for…

- *what's left to ship* → [`TODO.md`](./TODO.md)
- *content the first client owes us* → [`QUESTIONS-FOR-POST.md`](./QUESTIONS-FOR-POST.md)
- *what the old Rome Post 5 site had* → [`site-inventory.md`](./site-inventory.md)
- *how to develop locally* → [`README.md`](./README.md)
- *VPS operating conventions* → [`runbooks/`](./runbooks/)

This document covers the **why and what**, not the *how* or the *what's-next*.

---

## 1. What this project is

A **SaaS platform for American Legion posts.** The operator signs up a post as
a client and provisions a complete online environment for them: a public
**website** plus an admin **CRM** for SMS event reminders. Each client runs in
its own isolated environment on shared infrastructure.

**Rome Post 5 (romelegion.org) is the first client and the reference
implementation.** Everything we built for Post 5 becomes the product:
- The Post 5 *site* generalizes into a **shared Hugo theme** + a **per-client
  content/config instance**.
- The Post 5 *CRM* is already org-agnostic (driven by env vars); it becomes a
  **shared app image** + a **per-client container instance**.

```
                     ┌──────────────────────────────────┐
                     │      Legion Post Platform         │
                     │   (operator provisions clients)   │
                     └──────────────────────────────────┘
                                    │
              ┌─────────────────────┼─────────────────────┐
              ▼                     ▼                     ▼
        ┌───────────┐         ┌───────────┐         ┌───────────┐
        │ Post 5    │         │ Post 47   │         │ Post 112  │
        │ (Rome GA) │         │  (…)      │         │  (…)      │
        ├───────────┤         ├───────────┤         ├───────────┤
        │ website   │         │ website   │         │ website   │
        │ + CRM     │         │ + CRM     │         │ + CRM     │
        │ own domain│         │ own domain│         │ own domain│
        │ own DB    │         │ own DB    │         │ own DB    │
        └───────────┘         └───────────┘         └───────────┘
         shared theme          shared theme          shared theme
         shared CRM image      shared CRM image      shared CRM image
```

### Locked product decisions

| Decision | Choice | Implication |
|---|---|---|
| **Who the clients are** | American Legion posts only | Template stays Legion-specific (officers, Auxiliary/SAL, membership eligibility, four pillars). Narrow, well-fitting template. |
| **Tenant isolation** | Isolated environment per client | Each post gets its own Hugo site instance, its own CRM container, its own SQLite DB, its own secrets. Strong isolation; provisioning automates the per-client setup. |
| **Domains** | Each client's own custom domain | We point their DNS at the platform and issue per-domain TLS (Caddy auto-cert). `romelegion.org`, `vfwpost-style-domain.org`, etc. |
| **Accessibility** | WCAG **2.1 + 2.2** Level **AA** | Built into the shared theme + CRM so every client inherits compliance. AA is the legal/industry standard (ADA, Section 508, EN 301 549, AODA). |

### What this is NOT

- **Not self-service signup.** The operator provisions clients. A post can't
  spin up its own environment from a marketing page; the operator runs the
  provisioning process for them.
- **Not multi-tenant-shared-database.** We chose isolated environments. There
  is no `tenant_id` column shared across posts; each post's data lives in its
  own DB file and its own site directory.
- **Not a generic website builder.** Clients are Legion posts. The template
  assumes Legion structure. A church or club is out of scope.
- **Not a member-facing portal or dues processor.** Same as the single-tenant
  scope — myLegion handles member accounts; posts take dues by check.

---

## 2. Why we're doing it

The single-post build for Rome Post 5 proved the model: a fast, accessible,
near-zero-cost replacement for a ~$199/yr Legionsites template site, plus an
SMS reminder tool Legionsites can't offer. **There are ~12,000 American Legion
posts in the U.S.**, most running the same dated Legionsites/ALPost templates
or nothing at all. The platform productizes the Post 5 work so the operator
can offer the same thing to other posts at low marginal cost per client.

Per-client economics (rough):
- **Hosting:** near-zero — static site is bytes on disk; CRM is a ~20 MB-RAM
  Go process. Many clients fit on one modest VPS.
- **Per-client variable cost:** a Twilio number (~$1/mo) + SMS usage
  (~$0.0075 each) for clients who use the CRM; a domain the client owns.
- **Operator revenue model:** TBD (see Open Seams) — likely a flat monthly
  per-post fee.

---

## 3. The platform in three layers

### 3a. The shared website theme

**What it is.** Everything currently under `layouts/`, `assets/`, `static/`,
and the shortcodes becomes a **reusable Hugo theme** (`legion-post-theme`).
The theme knows how to render a Legion post site; it has *no* Post-5-specific
content baked in. All post-specific values come from the instance's config and
data files.

**Per-client instance.** Each client is a thin Hugo site that pulls in the
shared theme and supplies:
- `hugo.toml` — org name, custom domain, contact, branding (colors, logo
  override), `mapShortlinks`, social URLs
- `content/` — the post's pages (most clients start from a scaffolded copy of
  the standard page set, then fill in history, rental info, etc.)
- `data/officers.yaml` — that post's roster
- `content/events/` — that post's events
- `assets/images/` — that post's photos (hero, grounds, gallery, logo)

**Provisioning a website** = scaffold a new instance from a template, fill in
the client's parameters, build with the shared theme, deploy to
`/srv/www/<client-domain>/`, add a Caddy block, coordinate DNS.

**Branding per client.** The theme exposes a small set of CSS custom
properties (the existing `--navy`, `--red`, `--gold`, etc.) as
config-overridable tokens so a post can have its own accent within the Legion
visual language. Default is the standard Legion blue/red/gold.

### 3b. The shared CRM image

**What it is.** The Go CRM is already tenant-agnostic — org name, admin
credentials, Twilio creds, and DB path all come from environment variables.
It becomes a **single Docker image** deployed once per client as an isolated
container.

**Per-client instance.** Each client gets:
- Its own container from the shared image
- Its own SQLite DB on a named volume (complete data isolation)
- Its own secrets file (`/srv/secrets/crm-<client>.env`): that post's Twilio
  number + auth token, that post's admin username + password hash, that post's
  session secret
- Its own Caddy block at `admin.<client-domain>` → that container's loopback
  port

**No code change per client.** Same image, different env + volume + route.

### 3c. The provisioning layer (new — to be built)

The capability that makes this a SaaS rather than a pile of manual steps. A
**provisioning tool** that, given a client spec, produces a running
environment:

```
provision-client --config posts/post-47.yaml
   │
   ├─ Website
   │    ├─ scaffold content instance from template
   │    ├─ write hugo.toml from the client spec
   │    ├─ seed data/officers.yaml, contact, branding
   │    ├─ build with the shared theme
   │    ├─ rsync to /srv/www/<domain>/
   │    └─ generate caddy/sites/<domain>.caddy
   │
   ├─ CRM
   │    ├─ generate /srv/secrets/crm-<client>.env
   │    │   (admin creds, session secret, Twilio placeholders)
   │    ├─ allocate a loopback port
   │    ├─ write the per-client compose entry / container config
   │    ├─ create the named volume + start the container
   │    └─ generate caddy/sites/admin.<domain>.caddy
   │
   └─ Output a checklist of the manual steps that remain
        (point DNS, buy Twilio number, hand the post their admin password)
```

**v1 of the provisioning layer is a CLI** that reads a per-client YAML spec
and emits the artifacts + a residual-manual-steps checklist. A web control
panel can come later; the CLI is the engine either way.

---

## 4. Multi-tenancy model (the important details)

We chose **isolated environment per client.** Concretely:

| Resource | Isolation |
|---|---|
| Website files | Separate directory per client: `/srv/www/<domain>/` |
| Website config/content | Separate Hugo instance per client (own repo or own directory) |
| CRM process | Separate container per client |
| CRM database | Separate SQLite file on a separate named volume |
| Secrets | Separate `/srv/secrets/*.env` per client |
| TLS | Per-domain cert (Caddy auto-issues) |
| Twilio | Separate number + subaccount per client (sender-reputation isolation) |

**What's shared:**
- The **Hugo theme** (one codebase, versioned; clients pin or track a version)
- The **CRM Docker image** (one codebase, one image, many containers)
- The **VPS** (one box hosts many clients until capacity forces a second)
- The **provisioning tooling**

**Why isolated over shared-multi-tenant:** with a small number of high-trust
clients (Legion posts), data isolation and a simple mental model beat the
resource efficiency of row-level multi-tenancy. A bug in tenant-scoping code
can't leak Post A's member phone numbers to Post B if Post B's data is a
different file in a different container. Provisioning automation removes the
per-client toil that would otherwise make this model painful.

**Scaling ceiling:** isolated-per-client is comfortable into the low hundreds
of clients on commodity hardware (each CRM idles at ~20 MB RAM; static sites
cost ~0). If the platform ever reaches a scale where per-client containers
become the bottleneck, *that* is the moment to consider shared multi-tenancy —
not before.

---

## 5. Accessibility — WCAG 2.1 + 2.2 Level AA

Accessibility is a **platform feature**, not a per-client afterthought.
Compliance is built into the shared theme and CRM so **every client site
inherits it**. The first client (Post 5) already meets WCAG 2.1 AA; the
platform raises the bar to **2.1 + 2.2 AA** across the board.

### Already in place from the 2.1 AA work (inherited by every client)

Semantic HTML landmarks, skip link, one-h1-per-page heading hierarchy, alt
text discipline, ≥4.5:1 contrast, keyboard operability, visible focus
(3px gold outline), `prefers-reduced-motion`, form labels + `aria-describedby`,
`role="alert"` error regions, `aria-current` nav state, external-link
announcements, reflow at 320px, a public `/accessibility/` statement.

### New for WCAG 2.2 AA (must be in the theme + CRM)

WCAG 2.2 adds these success criteria over 2.1. Each needs to hold for every
client:

| 2.2 criterion | Level | What it requires | Where it bites |
|---|---|---|---|
| **2.4.11 Focus Not Obscured (Min)** | AA | A focused element isn't fully hidden by sticky/overlay content | The sticky header — a focused link scrolled under it must still be partly visible. Audit `scroll-padding-top`. |
| **2.5.7 Dragging Movements** | AA | Any drag action has a single-pointer (no-drag) alternative | We have no drag UI (lightbox is click/keys) — verify and keep it that way. |
| **2.5.8 Target Size (Min)** | AA | Pointer targets ≥ 24×24 CSS px (with exceptions) | Audit every link/button — nav, event actions, phone-reveal, footer links, gallery thumbnails. Nav toggle already 44×44. |
| **3.2.6 Consistent Help** | AA | If a help/contact mechanism repeats, it's in the same relative place across pages | Footer contact is consistent. Formalize a consistent "Contact" affordance site-wide. |
| **3.3.7 Redundant Entry** | AA | Don't force re-entering info already given in the same process | The long application form — use autofill, and don't ask twice for the same datum. |
| **3.3.8 Accessible Authentication (Min)** | AA | No cognitive-function test (puzzle/CAPTCHA) required to log in; allow paste/password managers | **CRM login** — keep username+password with autofill/paste allowed, no CAPTCHA. Honeypots on public forms are fine (not a user-facing test). |

(WCAG 2.2 also **removed** 4.1.1 Parsing — no action needed. The AAA-level
2.2 criteria — 2.4.12, 2.4.13, 3.3.9 — are explicitly out of scope.)

### Accessibility as a selling point

For a SaaS targeting public-serving veterans organizations, documented WCAG
2.2 AA conformance is a genuine differentiator and reduces the client's own
ADA/Section-508 exposure. The platform should ship a per-client accessibility
statement (already templated at `/accessibility/`) and an internal conformance
checklist run at each release of the shared theme.

---

## 6. Audiences

| Audience | Relationship to the platform |
|---|---|
| **The operator** (you) | Signs up posts, runs provisioning, maintains the shared theme + CRM image, handles support |
| **Post officers** (per client) | Use the CRM admin; supply content/branding; the post "owns" its site |
| **Post members / prospective members / community** (per client) | Use the public site — the same audiences as the single-tenant version, multiplied across clients |
| **Members receiving SMS** (per client) | Touched, not direct — reply YES once, get occasional reminders |

---

## 7. Tech stack at a glance

### Shared website theme + per-client instances

| Layer | Choice |
|---|---|
| Static site generator | **Hugo** extended |
| Distribution | Shared **Hugo theme** (Hugo Module or git submodule); per-client instance supplies content/config/data |
| CSS | Theme's `main.css`; per-client brand tokens overridable via config |
| JS | Theme's small no-framework scripts (nav, lightbox, phone-reveal) |
| Forms | PHP endpoints, shared `websites` PHP-FPM tenant, Resend per client |
| Hosting | Caddy on the VPS serves `/srv/www/<domain>/` per client |
| CI/deploy | GitHub Actions per client instance (or a provisioning-driven build) |

### Shared CRM image + per-client containers

| Layer | Choice |
|---|---|
| Language | **Go** (single static binary, one Docker image) |
| Server | `net/http` stdlib |
| Database | **SQLite** per client (`modernc.org/sqlite`, pure-Go) |
| Auth | HMAC-signed session cookie; per-client admin credentials via env |
| SMS | Twilio per client (separate number/subaccount) |
| Hosting | One container per client, Caddy fronts `admin.<domain>` |

### Provisioning layer (to build)

| Layer | Choice (proposed) |
|---|---|
| Form | CLI reading a per-client YAML spec (v1); web control panel (later) |
| Output | Scaffolded site instance, CRM env + container config, Caddy blocks, residual-steps checklist |
| Language | Likely Go (reuse skills + share types with the CRM) or shell — TBD |

---

## 8. Where things live

### Local working directories (today — single-client; pre-refactor)

| | Path |
|---|---|
| Website (→ becomes theme + Post 5 instance) | `/home/darrell/code/american-legion-rome-ga/` |
| CRM (→ becomes shared image) | `/home/darrell/code/legion-rome-crm/` |

### Target repo structure (post-refactor — proposed)

| Repo | Purpose |
|---|---|
| `legion-post-theme` | The shared Hugo theme (extracted from the Post 5 layouts/assets) |
| `legion-rome` (Post 5 instance) | First client's content/config; pulls in the theme |
| `legion-rome-crm` | The shared CRM image (already org-agnostic) |
| `legion-post-platform` | Provisioning CLI + per-client specs + ops glue |

### Production paths (VPS)

| | Path |
|---|---|
| Per-client website files | `/srv/www/<domain>/` |
| Per-client website secrets | `/srv/secrets/<domain>.env` (Resend) |
| Per-client CRM app | `/srv/apps/crm-<client>/` |
| Per-client CRM secrets | `/srv/secrets/crm-<client>.env` (Twilio, admin creds) |
| Per-client Caddy blocks | `/etc/caddy/sites/<domain>.caddy`, `admin.<domain>.caddy` |

### Tooling installed locally (no sudo, in `~/.local/`)

- Hugo extended → `~/.local/bin/hugo`
- Go → `~/.local/go/bin/go`

---

## 9. From here to a SaaS — the refactor path

The current code is single-tenant Post 5. Becoming a platform is a
**refactor + extract**, not a rewrite. Rough sequence (details/sequencing
live in `TODO.md`):

1. **Extract the theme.** Move Post 5's `layouts/`, `assets/`, `static/`,
   shortcodes into a standalone `legion-post-theme`. Reduce Post-5 specifics
   to config/data. Post 5's repo becomes a thin instance that consumes the
   theme. *Nothing visible changes for Post 5 — same output.*
2. **Confirm the CRM is fully env-driven.** It already is; audit for any
   hardcoded "Post 5" / Rome strings and move them to config.
3. **WCAG 2.2 pass on the theme + CRM.** The six new 2.2 AA criteria
   (Section 5). Done once in the theme → inherited by all clients.
4. **Build the provisioning CLI.** Read a client YAML → emit website instance
   + CRM env/container + Caddy blocks + checklist.
5. **Dogfood by re-provisioning Post 5 through the tool.** If the tool can
   reproduce Post 5 from a spec, it works.
6. **Onboard client #2** to validate the model on a real second post.

**Post 5 stays launchable throughout.** The single-tenant site can go live as
the first client before the full platform tooling exists; the refactor doesn't
block Post 5's launch.

---

## 10. What "launched" means

### Platform v1 (operator can onboard a new post)

- [ ] Theme extracted; Post 5 builds from the theme with identical output
- [ ] CRM confirmed fully env-driven; one image runs any client
- [ ] WCAG 2.2 AA pass complete on theme + CRM
- [ ] Provisioning CLI produces a working website + CRM from a client spec
- [ ] Post 5 reproducible end-to-end through the provisioning tool
- [ ] Documented per-client onboarding runbook (DNS, Twilio, admin handoff)

### Per-client launch (e.g. Post 5 as client #1)

- [ ] DNS for the client's domain points at the VPS; TLS issued
- [ ] Website deployed and live; populated pages have real content
- [ ] Contact + application forms tested end-to-end with the client's Resend
- [ ] 301 redirects from the client's old URLs (if migrating off Legionsites)
- [ ] Lighthouse ≥95 across all categories
- [ ] CRM container live at `admin.<domain>`; admin handed credentials
- [ ] Twilio number purchased; opt-in flow exercised with a real member

Post 5's per-client launch does **not** wait for full platform tooling — it
can ship single-tenant first and be folded into the platform afterward.

---

## 11. Decisions worth not relitigating

| Decision | Rationale |
|---|---|
| **SaaS for Legion posts specifically** | Narrow template fits well; ~12k posts is a real market; we already have a working reference. |
| **Isolated env per client, not shared multi-tenant** | Data isolation + simple mental model for a small, high-trust client base; automation removes the toil. Revisit only at low-hundreds of clients. |
| **Custom domain per client** | Professional; the post owns its identity; Caddy auto-TLS makes per-domain certs free. |
| **WCAG 2.1 + 2.2 AA (not AAA)** | AA is the legal/industry standard and achievable; AAA is impractical and not required. |
| **Hugo theme as the sharing mechanism** | Native Hugo pattern; lets each client track or pin a theme version; no per-client layout forks. |
| **One CRM image, many containers** | The CRM is already env-driven; per-container isolation is the cheapest path to strong tenant separation. |
| **Provisioning CLI before control panel** | The CLI is the engine; a UI is a face on it. Build the engine first. |
| **Post 5 as reference client, launchable standalone** | De-risks the platform — we ship value to a real post while the tooling matures. |
| (Inherited) **Hugo over WordPress; Go+SQLite for CRM; PHP forms; phone-reveal; Resend; maps shortlinks** | Same rationale as the single-tenant plan; unchanged by the SaaS pivot. |

---

## 12. Open seams (still being shaped)

- **Pricing / billing model.** Flat monthly per post? Tiered (website-only vs.
  website+CRM)? Annual? No billing system designed yet; out of scope until
  there's a paying client #2.
- **Client content ownership / data portability.** A post should be able to
  leave with its content and member list. The isolated model makes this clean
  (hand them their content repo + a CRM DB export) but the export tooling
  isn't built.
- **Theme versioning across clients.** When the shared theme improves, how do
  clients adopt it — auto-track latest, or pin and upgrade deliberately?
  Pinning is safer; auto-track is less maintenance. Leaning pinned-with-
  managed-upgrades.
- **Per-client CI vs. central provisioning build.** Does each client instance
  get its own GitHub Actions pipeline, or does the provisioning layer own all
  builds centrally? Affects who can push changes to a given post's site.
- **Operator support model.** When a post officer needs a content change, do
  they edit (Decap CMS per client), email the operator, or both? Ties into the
  long-standing "will officers self-edit?" question.
- **Twilio account structure.** One Twilio account with a subaccount per
  client (clean reputation isolation, more setup) vs. shared messaging service
  (simpler, shared reputation risk). Leaning subaccount-per-client.
- **CRM scheduled reminders.** Manual blast is the near-term build; an
  automatic "24h before the event" scheduler is a per-client background loop
  worth adding once clients run it regularly.
