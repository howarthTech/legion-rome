# Accessibility conformance — legion-post-theme

Target: **WCAG 2.1 + 2.2 Level AA**. Because the theme is shared, conformance
here is inherited by every client site. Run this checklist at each theme
release and before onboarding a new client.

## How to test

- **Automated:** Lighthouse (Accessibility ≥ 95) and axe DevTools on a
  representative set of pages (home, a content page, events, a form, the
  gallery+lightbox).
- **Manual keyboard:** Tab through every page start to finish — focus is
  always visible, order is logical, nothing is reachable-but-hidden, the
  lightbox traps/returns focus correctly, Esc closes it.
- **Manual screen reader:** at least one pass with VoiceOver (macOS/iOS) or
  NVDA (Windows) on the home page, a form, and the gallery.
- **Zoom/reflow:** 320px width and 200% zoom — no horizontal scroll, nothing
  clipped.

## WCAG 2.1 AA (baseline — established)

- [ ] 1.1.1 Non-text content — decorative images `alt=""`; meaningful images
      have descriptive alt
- [ ] 1.3.1 Info & relationships — semantic landmarks, labelled form fields,
      `aria-describedby` for help text
- [ ] 1.4.3 Contrast ≥ 4.5:1 (text), ≥ 3:1 (large text, UI components)
- [ ] 1.4.10 Reflow — usable at 320px, no horizontal scroll
- [ ] 1.4.11 Non-text contrast — focus ring + control borders ≥ 3:1
- [ ] 2.1.1 / 2.1.2 Keyboard — everything operable, no trap (native `<dialog>`)
- [ ] 2.4.1 Bypass blocks — skip link
- [ ] 2.4.7 Focus visible — 3px gold outline, 3px offset
- [ ] 3.1.1 Language — `<html lang>`
- [ ] 3.3.1 / 3.3.2 / 3.3.3 — error identification, labels/instructions,
      error suggestions on forms
- [ ] 4.1.2 / 4.1.3 — name/role/value; `role="alert"` / `role="status"` for
      messages; `aria-live` lightbox caption

## WCAG 2.2 AA (added — verify each release)

- [ ] **2.4.11 Focus Not Obscured (Minimum)** — `scroll-padding-top` /
      `scroll-margin-top` (6.5rem) ≥ the sticky header's tallest height
      (~96px desktop). A keyboard-focused element scrolled to is never fully
      hidden under the header. *Re-check if the header height changes.*
- [ ] **2.5.7 Dragging Movements** — no functionality requires dragging. The
      gallery lightbox is click + arrow keys; nav is click; no sliders, no
      drag-to-reorder. *Guarantee: do not introduce drag-only UI without a
      single-pointer alternative.*
- [ ] **2.5.8 Target Size (Minimum)** — interactive targets ≥ 24×24px:
      nav toggle (44×44), nav links, buttons, event actions, phone-reveal
      (min-height 24px), footer "Resources" links (min-height 24px), gallery
      thumbnails (large). Inline links within sentences use the spec's inline
      exception.
- [ ] **3.2.6 Consistent Help** — the footer contact block (phone, email,
      mailing address) and the "Contact" nav item appear in the same relative
      location on every page.
- [ ] **3.3.7 Redundant Entry** — forms never ask for the same information
      twice in one process; no "confirm email/password" re-entry fields; all
      fields carry `autocomplete` so browsers can fill them.
- [ ] **3.3.8 Accessible Authentication (Minimum)** — the **CRM** login is
      username + password with `autocomplete="username"` /
      `"current-password"`; paste and password managers are allowed; there is
      no CAPTCHA or cognitive-function test. (Public-site form honeypots are
      hidden anti-spam fields, not a user-facing test — compliant.)

## Notes

- WCAG 2.2 **removed** 4.1.1 Parsing — no action needed.
- AAA-level 2.2 criteria (2.4.12, 2.4.13, 3.3.9) are **out of scope** by
  policy; the platform targets AA.
- The public per-client accessibility statement lives at `/accessibility/`
  in each instance's content and should name the same AA target.
