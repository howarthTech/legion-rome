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

## Tech-debt / nice-to-haves

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
