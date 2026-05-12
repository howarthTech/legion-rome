# TODO — developer-side cleanup

Things to handle before launch but that don't need answers from the post.
Content-side gaps and questions for the post officers live in
[QUESTIONS-FOR-POST.md](./QUESTIONS-FOR-POST.md).

---

## Site config / placeholders

- [ ] **Replace placeholder Facebook URL** in [`hugo.toml`](./hugo.toml)
      (`facebookURL`) — currently points to The American Legion's national
      Facebook account. Update to Post 5's actual page once we confirm the URL.
- [ ] **Confirm the phone number** in [`hugo.toml`](./hugo.toml) (`postPhone`).
      The existing site has a one-digit conflict (951-204-8635 vs 951-201-8635)
      and 951 is a California area code. Pending Albert's answer.

## Workflow / deploy

- [ ] **Enable deploys when OPS is ready** — set five repo secrets
      (`DEPLOY_SSH_KEY`, `DEPLOY_KNOWN_HOSTS`, `DEPLOY_HOST`, `DEPLOY_USER`,
      `DEPLOY_PATH`) and flip `gh variable set DEPLOY_ENABLED --body "true"`.
      Recipe in [README.md](./README.md).
- [ ] **DNS** — confirm who manages `romelegion.org` nameservers and prepare
      to add an A record pointing at the droplet IP (`165.227.26.223` per the
      runbook).
- [ ] **Add `DEPLOY_ENABLED` is off documentation** to the repo About text
      (currently only in README).

## Future content + features (not blocking launch)

- [ ] **Combined ICS feed** so members can subscribe to "all Post 5 events" in
      one go (Apple Calendar / Google Calendar subscription URL).
- [ ] **Pagination of Past Events** — list will outgrow a single page after a
      year or so of additions. Cap at ~12 or paginate.
- [ ] **Decap or Sveltia CMS** — if/when an officer wants to self-edit, scaffold
      `/admin/` with the appropriate auth flow.
- [ ] **Real photo gallery albums** — the placeholder "The Farm" album is OK
      for launch; replace with real event albums when the post sends photos.
- [ ] **Recurring events** — the monthly meeting is currently entered one event
      at a time. Either auto-generate them or accept that someone re-enters
      monthly.
- [ ] **Donation / sponsor signup page** — link to a third-party processor
      (Givebutter, Donorbox, Stripe Checkout). Cannot take card numbers directly.
- [ ] **Hall rental inquiry form** — separate form distinct from the general
      contact form, with date / capacity / event-type fields.

## CRM / member management (major future feature)

A long-term ask: an admin-side member directory with SMS reminders for
upcoming meetings and events. **Not built. Not part of the v1 launch.**

### Requirements (as understood)

- **Admin authentication** — only Post 5 officers (Commander, Adjutant, etc.)
  should be able to manage the member list. Public site stays read-only.
- **Member list** — names and phone numbers at minimum. Likely also email,
  membership-renewal date, branch / era, role in the post family
  (Legion / Auxiliary / SAL), and an opt-in/opt-out flag for SMS.
- **SMS reminders** — admin can pick an event from the calendar and trigger a
  blast text to the opted-in members. Probably:
  - Scheduled automatic reminders (e.g. "tomorrow at 6 PM: Post 5 meeting")
  - Manual blasts ("Tonight's meeting moved indoors due to weather")
- **Bonus: import / export** of the member list (CSV) for backup and to seed
  initial data from whatever the post uses today (paper roster, Excel, etc.).

### What this is NOT

- Not a public-facing member portal (members signing themselves up online,
  paying dues, etc.). That's a much bigger lift and the national myLegion
  portal already covers it.
- Not a marketing platform. SMS is for event reminders to opted-in members
  only.

### Considerations before building

1. **TCPA compliance** (Telephone Consumer Protection Act). Sending SMS to
   members in the US legally requires:
   - **Express consent** in writing (or equivalent) before the first message.
     Implementable as an "I agree to receive SMS reminders" checkbox on the
     membership application form, plus a paper signup for existing members.
   - **Opt-out instructions** in every message ("Reply STOP to unsubscribe").
   - **No marketing content** — keep messages strictly informational.
   - **No texts to numbers on the National Do Not Call Registry** without
     verified consent.
   Real penalties exist (up to $1,500 per violation). Not a feature to ship
   carelessly.

2. **Build vs buy.** Three realistic paths:
   - **SaaS (recommended for first iteration):** services like SimpleTexting,
     EZ Texting, SlickText, or Twilio Studio. ~$20–50/month for low volume,
     fully managed compliance, member list lives in their app. Lowest dev
     effort. Tradeoff: another vendor to babysit.
   - **Twilio + small custom admin app:** flexible, cheaper at scale, but
     requires us to build the admin UI, store the member list, handle
     opt-outs, and own compliance. Probably overkill for ~100 members.
   - **Hybrid:** members live in our DB, but SMS sending goes through a
     SaaS via their API. Splits the difference.

3. **Architecture impact.** Adding admin auth + a database + SMS sending
   moves us off "pure static site." This becomes a sibling tenant on the VPS:
   - New `/srv/apps/legion-rome-crm/` directory
   - New compose file with an app container (Node/Python/PHP — TBD) + a
     database
   - New port allocation, resource budget row, backup drop-in
   - Separate GitHub repo (cleaner than mixing app code into the site repo)
   - Caddy block routes `admin.romelegion.org` (or `/admin/`) to the new app
   The static site repo stays untouched. The CRM is its own deploy.

4. **Auth.** For a 5-officer admin pool, GitHub OAuth or magic-link email
   login is plenty. No need for a full password-and-MFA system.

5. **Member list bootstrapping.** Whatever the post uses today (paper, Excel,
   the myLegion portal's export) needs to be a one-time import. Need to know
   what they have.

### Open questions to confirm with the post before scoping

- [ ] Roughly how many members would receive SMS reminders? (~50? ~200?
      Affects the SaaS-vs-custom decision.)
- [ ] Who are the admin users? (Just Commander + Adjutant? All officers?)
- [ ] Are members already SMS-opted-in somewhere, or would we collect consent
      from scratch?
- [ ] Is there an existing member roster we'd import (Excel, paper, myLegion
      export)?
- [ ] What's the budget appetite for a monthly SaaS subscription?

---

## Tech-debt / nice-to-haves

- [ ] **Swap the calendar emoji** (📅) on the "Add to Google Calendar" button
      for an inline SVG icon, matching the Apple-logo SVG on its sibling
      button. Currently mixed: SVG (Apple) + emoji (📅). Quick consistency fix.
- [ ] **Live deprecation** — `actions/checkout@v5` etc. will continue working
      with the Node 24 env var, but cleaner to migrate to actions that ship
      Node 24 natively once they're available.
- [ ] **Update the existing site's redirect map** — once we cut over, we'll
      want 301 redirects from popular Legionsites URLs (`/site/contactus`,
      `/site/eventscalendar`, etc.) to their new locations. Caddy `redir`
      directives, added to the site block.
- [ ] **Test the contact and application forms end-to-end** with a real
      Resend API key before launch (`make dev-forms`).
- [ ] **Lighthouse audit** before launch — aiming for ≥95 on Performance,
      Accessibility, Best Practices, SEO.
- [ ] **404 page polish** — current copy is minimal. Could add a Post 5 image
      and warmer tone.
