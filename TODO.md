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

- [ ] **Real Lighthouse run.** Structural audit is done (meta, canonical,
      single-h1, OG image now present, minified+fingerprinted CSS, sized
      images). Still want a real Chrome+Lighthouse run for the actual scores
      and anything the static audit can't catch (e.g. runtime CLS, contrast
      edge cases). Needs Chrome + the `lighthouse` CLI installed.

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

**Steps 1–3 shipped** (theme extracted with byte-identical output, CRM
verified tenant-agnostic, WCAG 2.2 AA pass complete). Remaining:

### Step 1 follow-up 🟢 local

- [ ] **Expose brand tokens** — surface `--navy` / `--red` / `--gold` / logo
      as config-overridable so a client can theme within the Legion palette.
      (Theme is extracted; this is the one remaining sub-item — color tokens
      are still hardcoded in the theme CSS.)
- [ ] **Distribute the theme as a Hugo Module or submodule** so instances can
      pin a version. Currently a local `themes/` dir, which is fine for Post 5
      but won't scale to many client repos.

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
