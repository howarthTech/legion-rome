# Legion Post Theme

Shared Hugo theme for **American Legion post websites** — part of the Legion
Post Platform (see [`../../plan.md`](../../plan.md)). It renders a complete
post site (home, about, membership, events, gallery, hall rental, contact,
flag etiquette, accessibility) from a per-client instance's config + content +
data. No post-specific content is baked into the theme; everything comes from
the instance.

Rome Post 5 (`romelegion.org`) is the first instance and reference
implementation.

## What lives here vs. in the instance

| In the theme (shared) | In the instance (per client) |
|---|---|
| `layouts/` — all templates, partials, shortcodes | `content/` — the post's pages |
| `assets/css/main.css` | `data/officers.yaml` — the roster |
| `assets/images/american-legion-seal.png` | `assets/images/` — the post's photos (hero, grounds, gallery) |
| `static/js/`, `static/fonts/`, `static/favicon.svg` | `hugo.toml` — config, params, menus |
| | `content/events/` — the post's events |

## Required instance params

The theme reads these from the instance's `[params]`. A provisioning tool
fills them per client. (See Post 5's `hugo.toml` for a complete, working
example.)

| Param | Example | Used by |
|---|---|---|
| `postName` | `Shanklin Attaway Post 5` | titles, footer, schema.org |
| `postShortName` | `Post 5` | header brand, body copy |
| `heroTitle` | `Shanklin Attaway Post 5 &mdash; Rome, Georgia` | homepage `<h1>` (HTML allowed) |
| `heroImageAlt` | `Two Post 5 officers …` | homepage hero image alt |
| `charterYear` | `1925` | footer, schema.org |
| `description` | `… serving veterans …` | meta description |
| `ogImage` | `images/hero-meeting.jpg` | social share image (instance asset) |
| `locality` / `region` / `regionLong` | `Rome` / `GA` / `Georgia` | copy, footer |
| `serviceArea` | `Rome and Floyd County` | homepage copy |
| `facebookSearch` | `American Legion Post 5 Rome GA` | events page hint |
| `schemaAltName` | `American Legion Post 5 Rome GA` | schema.org alternateName |
| `timezone` | `America/New_York` | calendar (.ics) feeds |
| `postEmail` / `postPhone` | … | contact, footer (phone uses reveal pattern) |
| `mailingAddress` | display string | footer |
| `meetingLocation` / `meetingSchedule` | display strings | footer, about |
| `[params.postal]` | `street`/`locality`/`region`/`postalCode` | schema.org PostalAddress |
| `[params.venue]` | `name`/`street`/`locality`/`region`/`postalCode` | schema.org meeting Place |
| `[params.mapShortlinks]` | `"Jones Bend" = "https://maps.app.goo.gl/…"` | verified map pins |
| `[params.brand]` *(optional)* | `navy`/`red`/`gold`/… hex colors | per-client color overrides (see below) |

### Brand colors (optional)

The default palette is Legion navy/red/gold. A client can override any token
under `[params.brand]` — values must be hex colors; non-hex values are ignored
with a build warning. If no brand params are set, no extra markup is emitted.

```toml
[params.brand]
  navy      = "#0a3161"   # --navy  (primary)
  navyDark  = "#061f3f"   # --navy-d
  navyLight = "#1d4584"   # --navy-l
  red       = "#b31942"   # --red   (accent)
  redDark   = "#8e1334"   # --red-d
  gold      = "#c8a85a"   # --gold  (highlight)
  goldDark  = "#9c7d3a"   # --gold-d
  cream     = "#faf6ec"   # --cream
```

**TOML gotcha:** the `[params.*]` sub-tables must appear *after* every simple
`key = value` under `[params]`. Once a sub-table header opens, all following
keys belong to it.

## Accessibility

The theme targets **WCAG 2.1 + 2.2 Level AA**; every instance inherits it.
See [`../../plan.md` §5](../../plan.md) for the criteria and the per-release
conformance checklist.

## Using the theme

In the instance `hugo.toml`:

```toml
theme = "legion-post-theme"
```

For local dev the theme lives under `themes/`. For the platform it will be
distributed as a Hugo Module or git submodule so instances can pin a version.
