# TODO

Open dev-side items only, ordered from "can do right now without waiting on
anyone" to "blocked on someone else." Content questions for the post officers
live in [QUESTIONS-FOR-POST.md](./QUESTIONS-FOR-POST.md).

Items get deleted from this file when they ship; they don't move to a "done"
section.

---

## 🟢 Can do locally right now — no external dependency

### Medium effort (1–2 hours each)
- [ ] **301 redirect map** from the old Legionsites URLs. Caddy `redir`
      directives in
      [`caddy/sites/romelegion.org.caddy`](./caddy/sites/romelegion.org.caddy).
      Old URLs documented in [`site-inventory.md`](./site-inventory.md);
      top candidates: `/site/contactus` → `/contact/`,
      `/site/eventscalendar` → `/events/`, `/post-officers` → `/about/`,
      `/site/application` → `/membership/apply/`,
      `/site/photogallery` → `/gallery/`, `/post-information` → `/about/`,
      `/post-history` → `/about/history/`. Doesn't take effect until
      cutover but the file should be ready.
- [ ] **Lighthouse audit + fixes.** Target ≥95 on Performance,
      Accessibility, Best Practices, SEO. Run locally against
      `make build` output served over a simple `python -m http.server`
      or similar; fix whatever it flags.
- [ ] **Combined ICS feed** — "subscribe to all Post 5 events" calendar
      URL. New Hugo output format, similar to the per-event `.ics` we
      already generate. Plus a "Subscribe to events" button on
      [`/events/`](./content/events/_index.md).
- [ ] **Pagination of past events.** The unbounded list is fine today but
      will outgrow a single page in ~6–12 months. Cap at ~12 visible or
      paginate via Hugo's `Paginate` method.

### Larger features (half-day+)
- [ ] **Decap or Sveltia CMS** — scaffold `/admin/` editor with the right
      config for non-technical officers to self-edit without git/markdown
      knowledge. Mostly a YAML config file + branch / collection definitions.
- [ ] **Donation / sponsor page.** A `/support/` page with copy + an
      outbound link to a third-party processor (Givebutter or Donorbox
      are the post's cleanest options — no PCI compliance burden on us).
- [ ] **Hall rental inquiry form** — separate form distinct from the
      general contact form, with date / capacity / event-type fields.
      New PHP endpoint at `/_form/rental.php` mirroring the existing
      contact/application pattern.
- [ ] **Recurring events.** Monthly meeting is currently one event file
      per month entered by hand. Either auto-generate the next ~6 months
      from a recurrence rule in a Hugo data file, or accept the manual
      workflow and just document it.

## 🟡 Requires signing up for an external account (we keep doing the work)

- [ ] **Test the contact + application forms end-to-end** with a real
      Resend API key (`make dev-forms`). Need to: create a Resend account,
      add `romelegion.org` as a sending domain, paste the verifications
      into DNS once OPS gives us nameserver access, generate an API key,
      drop it in `dev/secrets/romelegion.org.env`, run `make dev-forms`,
      submit each form, verify the email arrives. Today the forms are
      dry-run tested only.

## 🔴 Blocked on OPS (do last)

- [ ] **DNS for `romelegion.org`.** Confirm who manages the nameservers
      and point an A record at the droplet (`165.227.26.223` per the
      [hosting-pattern runbook](./runbooks/hosting-pattern.md#caddy-site-blocks)).
- [ ] **Enable deploys.** Five GH Actions secrets + a repo variable.
      Recipe in [README.md](./README.md#enabling-deploys). Waits on OPS
      provisioning the deploy SSH key and the DNS record above.
- [ ] **Cutover.** Once DNS is live and deploys are enabled: trigger the
      first real deploy, verify the live site, set up the 301 redirects
      above, watch logs for a day or two.

---

## 🔵 CRM project (separate repo)

[howarthTech/legion-rome-crm](https://github.com/howarthTech/legion-rome-crm)
is its own sibling project; v1 of the static site launches independently
of it. Cross-listing the open work so it's visible from one place.

### Can do locally right now
- [ ] **Event-reminder send flow.** Admin picks an event from the static
      site's `/events/events.json` feed (now live), blast goes to all
      `OPTED_IN` members. Needs a send screen, the send loop, and audit
      logging.
- [ ] **Quiet-hours guard.** Don't text between 9 PM and 9 AM local time.
      Tiny check in the send flow.
- [ ] **Production deploy artifacts.** Dockerfile, docker-compose.yml,
      Caddy site block for `admin.romelegion.org`. Can be written without
      OPS — they review, we deploy.

### Requires a Twilio account
- [ ] **Set up Twilio + buy a sending number.** ~$1/month for a US local
      number, ~$0.0075/SMS outbound. At 10–50 members × 1–2 sends/month
      that's roughly $1–5/mo total.

### Open with the post
- [ ] Are members already SMS-opted-in somewhere we can import consent
      from, or do we collect from scratch on first contact?
- [ ] Budget appetite for the Twilio bill (~$1–5/month).

### Blocked on OPS (do last)
- [ ] Deploy the CRM as a new VPS tenant. Pending OPS resource-budget
      conversation for the new `/srv/apps/legion-rome-crm/` allocation.
