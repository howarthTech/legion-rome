---
title: "Accessibility"
description: "Our commitment to making romelegion.org usable for everyone, the standards we follow, and how to report a problem."
---

We want every veteran, family member, and community member to be able to use
this website — regardless of how they navigate the web or what assistive
technology they rely on. This page describes the steps we've taken, the
standards we're working toward, and how to let us know if something on the
site is getting in your way.

## Standards we follow

We aim to meet the **[Web Content Accessibility Guidelines (WCAG)
2.1](https://www.w3.org/TR/WCAG21/) and
[2.2](https://www.w3.org/TR/WCAG22/) Level AA**, the international standard for
web accessibility. Specifically, the site is built and reviewed against:

- **Perceivable** — text alternatives for non-decorative images, sufficient
  color contrast (≥ 4.5 : 1 for normal text, ≥ 3 : 1 for large text and UI
  components), and content that adapts cleanly to mobile, tablet, desktop,
  and zoomed-in views.
- **Operable** — every interactive element is reachable and usable with
  only a keyboard. A "skip to main content" link appears at the top of every
  page for screen-reader and keyboard users. Tap targets meet the WCAG 2.2
  minimum size, and a keyboard-focused element is never hidden behind the
  sticky page header.
- **Understandable** — predictable navigation, clear form labels, plain
  language, and inline error messages when something goes wrong.
- **Robust** — semantic HTML5 landmarks (`<header>`, `<nav>`, `<main>`,
  `<footer>`), valid ARIA where needed, and the site degrades gracefully if
  JavaScript fails.

## Specific features

- **Reduced motion respected.** If your operating system is set to "reduce
  motion," animations and scrolling effects are turned off automatically.
- **Keyboard navigation.** All menus, forms, the photo gallery lightbox, and
  the calendar can be operated with Tab, Shift+Tab, Enter, Space, and arrow
  keys. The lightbox closes with Esc and steps through photos with ← / →.
- **Visible focus indicators.** Wherever you can put keyboard focus, you'll
  see a clearly visible gold outline.
- **Screen-reader landmarks.** Headings step down logically (one `<h1>` per
  page, `<h2>` for major sections, `<h3>` for subsections); ARIA labels
  describe icon-only buttons; live regions announce form errors.
- **Mobile reflow.** The site fits and stays usable at 320 px wide without
  horizontal scrolling.

## What we haven't done yet

We're not perfect — a small all-volunteer post can't audit like a Fortune 500
can. Known gaps we're tracking:

- We haven't run a professional manual accessibility audit. We rely on
  automated tooling (Lighthouse, axe DevTools) plus our own manual keyboard
  testing.
- Older event photos may lack detailed captions. New photos are captioned
  as they're added.
- The membership application is a long form. We're considering breaking it
  into smaller steps in a future revision.

## Help us improve

**If you run into something on this site that's hard to use — please tell
us.** Even one sentence (*"the contact form's submit button doesn't work
with my screen reader"*) helps us fix it.

- **Email:** <a href="mailto:romepost5@gmail.com">romepost5@gmail.com</a>
- **Phone:** {{< phone "(951) 204-8635" >}}
- **Mail:** {{< maps-link "P.O. Box 945, Rome, GA 30162" >}}

Please include:

1. The page address (URL) where you hit the problem
2. Roughly what you were trying to do
3. Whatever assistive technology you use (if any) — e.g. *VoiceOver on
   iPhone*, *NVDA on Windows 11*, *high-contrast mode*

We'll respond as quickly as we reasonably can.

## Conformance statement

This statement was last reviewed and updated on **{{< current-month-year >}}**.
Our target conformance level is **WCAG 2.1 + 2.2 Level AA**. We test in current
versions of Chrome, Safari, Firefox, and Edge on Windows, macOS, iOS, and
Android, using keyboard, mouse, and touch.
