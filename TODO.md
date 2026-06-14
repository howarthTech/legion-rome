# TODO

Open dev-side work, ordered by what unblocks the next action. Two tracks run
in parallel:

- **Track A — Post 5 launch** (client #1, single-tenant). Ships value to a
  real post now; does *not* wait on platform tooling.
- **Track B — Platform** (the SaaS refactor: theme extraction, WCAG 2.2,
  provisioning). Productizes the Post 5 work.

See [`plan.md`](./plan.md) for what the platform is and why. Content the post
officers owe us lives in [`QUESTIONS-FOR-POST.md`](./QUESTIONS-FOR-POST.md).
Items get deleted on ship — this file tracks only what's open.

---

## Track A — Post 5 launch (client #1)

### 🟢 Local, no external dependency

- [ ] **301 redirect map** from the old Legionsites URLs. Caddy `redir`
      directives in
      [`caddy/sites/romelegion.org.caddy`](./caddy/sites/romelegion.org.caddy).
      Candidates in [`site-inventory.md`](./site-inventory.md):
      `/site/contactus` → `/contact/`, `/site/eventscalendar` → `/events/`,
      `/post-officers` → `/about/`, `/site/application` →
      `/membership/apply/`, `/site/photogallery` → `/gallery/`,
      `/post-information` → `/about/`, `/post-history` → `/about/history/`.
- [ ] **Lighthouse audit + fixes.** Target ≥95 Performance / Accessibility /
      Best Practices / SEO against `make build` output.
- [ ] **Combined ICS feed** — "subscribe to all Post 5 events" calendar URL
      + a Subscribe button on [`/events/`](./content/events/_index.md).
- [ ] **Pagination of past events** — cap visible past events or paginate
      before the list grows past ~30.

### 🟡 Needs an external account (we do the work)

- [ ] **End-to-end form test** with a real Resend key (`make dev-forms`):
      create the Resend account, verify `romelegion.org` as sender (needs
      DNS access from OPS), generate a key, drop in
      `dev/secrets/romelegion.org.env`, submit both forms, confirm delivery.

### 🔴 Blocked on OPS (do last)

- [ ] **DNS for `romelegion.org`** → A record to the droplet
      (`165.227.26.223`).
- [ ] **Enable deploys** — five GH Actions secrets + `DEPLOY_ENABLED=true`.
      Recipe in [README.md](./README.md#enabling-deploys).
- [ ] **Cutover** — first real deploy, verify live, activate redirects,
      watch logs.

---

## Track B — Platform (SaaS refactor)

Sequenced so Post 5's output never changes until each step is proven. See
[`plan.md` §9](./plan.md) for the rationale.

### Step 1 — Extract the shared theme 🟢 local

- [ ] **Create `legion-post-theme`.** Move `layouts/`, `assets/`, `static/`,
      and the shortcodes out of the Post 5 repo into a standalone Hugo theme.
- [ ] **Reduce Post-5 specifics to config/data.** Audit templates for any
      hardcoded "Shanklin Attaway" / "Rome" / "Post 5" / phone / address and
      route them through `site.Params` or `data/`.
- [ ] **Make Post 5 a thin instance** that consumes the theme (Hugo Module or
      submodule). **Acceptance: `make build` produces byte-identical output to
      today** (or only trivial diffs we understand).
- [ ] **Expose brand tokens** — surface `--navy` / `--red` / `--gold` / logo
      as config-overridable so a client can theme within the Legion palette.

### Step 2 — Confirm CRM is fully tenant-agnostic 🟢 local

- [ ] **Grep the CRM for hardcoded org strings** ("Post 5", "Rome",
      "romelegion", any phone/address). Move anything found to env/config.
      Most is already env-driven (`ORG_NAME`, etc.) — this is a verification
      pass.
- [ ] **Confirm one image runs any client** — same binary, different
      `*.env` + DB volume + route. Document the per-client env contract.

### Step 3 — WCAG 2.2 AA pass (theme + CRM) 🟢 local

Done once in the theme → inherited by every client. See
[`plan.md` §5](./plan.md) for the full criteria table.

- [ ] **2.4.11 Focus Not Obscured** — verify focused elements aren't hidden
      under the sticky header at any scroll position; tune `scroll-padding-top`.
- [ ] **2.5.8 Target Size ≥24×24** — audit every link/button (nav, event
      actions, phone-reveal, footer links, gallery thumbnails, pagination).
- [ ] **2.5.7 Dragging Movements** — confirm no drag-only interaction exists
      (lightbox is click/keys); document the guarantee.
- [ ] **3.2.6 Consistent Help** — formalize a consistent contact affordance
      across all pages.
- [ ] **3.3.7 Redundant Entry** — audit the application form so no datum is
      requested twice; ensure autofill works throughout.
- [ ] **3.3.8 Accessible Authentication** — CRM login keeps username+password
      with paste / password-manager / autofill allowed; **no CAPTCHA or
      cognitive-test gate**. Verify.
- [ ] **Update `/accessibility/`** statement to claim WCAG 2.1 **+ 2.2** AA.
- [ ] **Add an internal conformance checklist** run at each theme release.

### Step 4 — Provisioning CLI 🟢 local (build in the platform repo)

- [ ] **Scaffold `legion-post-platform`** repo for the provisioning tool +
      per-client specs.
- [ ] **Define the client spec schema** (YAML): org name, custom domain,
      officers, contact, branding, social URLs, map shortlinks.
- [ ] **Website provisioning** — spec → scaffolded content instance +
      `hugo.toml` + `data/` + build + `caddy/sites/<domain>.caddy`.
- [ ] **CRM provisioning** — spec → `/srv/secrets/crm-<client>.env` (admin
      creds + session secret + Twilio placeholders) + loopback port allocation
      + container/compose config + named volume + `admin.<domain>.caddy`.
- [ ] **Residual-steps checklist output** — DNS to point, Twilio number to
      buy, admin password to hand off.

### Step 5 — Dogfood + onboard client #2 🟡 needs a real second post

- [ ] **Re-provision Post 5 from a spec** through the CLI; confirm it
      reproduces the live environment. (If it can rebuild client #1, it works.)
- [ ] **Onboard a real second post** to validate the model end-to-end.

### 🔴 Platform items blocked on OPS / external

- [ ] **Per-client tenancy on the VPS** — confirm with OPS the directory +
      port + resource-budget convention for many `/srv/www/<domain>/` sites
      and `/srv/apps/crm-<client>/` containers.
- [ ] **Twilio account structure** — set up subaccount-per-client (see
      [`plan.md` §12](./plan.md)).

---

## 🔵 CRM feature work (separate repo)

[howarthTech/legion-rome-crm](https://github.com/howarthTech/legion-rome-crm).
These ship regardless of track and benefit every client.

### 🟢 Local

- [ ] **Event-reminder send flow** — admin picks an event from the site's
      `/events/events.json` feed (live), blast goes to all `OPTED_IN`
      members. Send screen + send loop + audit logging.
- [ ] **Quiet-hours guard** — no SMS 9 PM–9 AM local.
- [ ] **Production deploy artifacts** — Dockerfile + compose + Caddy block,
      written so the provisioning layer can template them per client.

### 🟡 Needs Twilio

- [ ] **Twilio account + first sending number** — ~$1/mo/number,
      ~$0.0075/SMS.

### Open with the post

- [ ] Are members already SMS-opted-in somewhere we can import consent from,
      or collect from scratch?
- [ ] Budget appetite for the Twilio bill (~$1–5/mo per post).

---

## ⚪ Decisions to make (not yet actionable)

Tracked in [`plan.md` §12](./plan.md): pricing/billing, data portability/
export, theme versioning across clients, per-client CI vs. central builds,
operator support model. Surface these when a paying client #2 is real.
