# romelegion.org — Site Inventory

**Source:** Crawl of https://romelegion.org/ on 2026-05-11
**Platform:** Legionsites.com (hosted CMS)
**Total pages crawled:** 29 internal pages

This document organizes every page on the existing site as **populated** (real, post-specific content worth migrating) or **unpopulated** (empty placeholders or generic American-Legion template content the post never customized).

---

## Summary

| Bucket | Count |
|---|---|
| ✅ Populated (real content) | 11 |
| ⚠️ Partially populated (some real info, mostly thin) | 4 |
| ❌ Unpopulated (empty placeholder or generic template) | 14 |
| **Total** | **29** |

Roughly **half the existing site is empty or boilerplate** the post never customized. The rebuild can start with substantially fewer pages.

---

## ✅ Populated Pages

Real, post-specific content. These are the pages worth migrating (with edits) into the rebuild.

### `/` and `/home` — Homepage
Welcome message, mission, charter year (1925), upcoming meetings, event carousel, booster/sponsor CTA, scholarship CTA. Mentions both "Shanklin Attaway Post 5" and "Rome Post 5" (naming inconsistency).

### `/post-information`
Meeting location, post commander (Albert Hollis), meeting schedule ("every 2nd Monday at 6 pm"), location at Walker Farm, links to Post 5 Facebook page and group.

### `/post-updates`
Event log with real dates: Feb 9 2026 meeting, Feb 19 2026 Birthday Bash at Landmark Family Restaurant, March 9 2026 meeting, May 11 2026 meeting (officer elections), May 17 2026 SAL meeting, July 4 2026 party. Locations include "The Farm, 493 Jones Bend Road."

### `/site/eventscalendar`
Two upcoming real events:
- **Post 5 Meeting** — Mon May 11, 2026, 6:00–8:00 PM, "At the The Farm." Contact: Albert Hollis, 951-201-8635. *Officer Nominations.*
- **Sons of American Legion Meeting** — Sun May 17, 2026, 2:00–3:30 PM, "At the Post." Contact: Ronnie Grist, 678-232-3381. *Officer voting, membership renewal, new member sign-ups.*

### `/site/contactus`
Working contact form. Fields: Name, Email, Phone, Subject (routing dropdown), Message. Routing options:
- Commander Albert Hollis — 951-204-8635
- Sr Vice Commander George Sifuentes — 706-728-8897
- Post Historian Will Adams — 706-368-9770

### `/post-officers`
Full officer roster with contact info:
| Role | Name | Phone | Email |
|---|---|---|---|
| Commander | Albert Hollis | (951) 204-8635 | romepost5@gmail.com |
| Sr Vice Commander | George Sifuentes | (706) 728-8897 | grulo65@gmail.com |
| Adjutant | Rick Hunt | (912) 312-0258 | — |
| Finance Officer | Richard Bingham | (706) 290-4962 | — |
| Post Historian | Will Adams | (706) 368-9770 | wjadams42@gmail.com |

No officer photos.

### `/auxiliary`
Real content. Meets **2nd Monday at 6 pm at Brookdale Senior Living, 180 Woodrow Wilson Way, Rome, GA 30165**. Membership eligibility explained. Recruitment contact: **Louise Burgess, (706) 266-9816**. No officers listed.

### `/membership-requirements`
Real eligibility info. Requirements:
- Served in U.S. Armed Forces during Apr 6, 1917 – Nov 11, 1918, **or** any time after Dec 7, 1941
- At least one day of active duty, honorably discharged or still serving honorably

New-member process: app (2 printed copies) + DD214 + annual dues + card on approval. Transfer process documented.

### `/site/application`
Working online application form. Fields: name, email, phone, DOB, full address, membership ID, military branch (Army/Navy/Air Force/Marines/Space Force/Coast Guard/Merchant Marines), service era (WWII–GWOT), former dept/post, renewal/transfer/new toggle, detachment/squadron, family-member relationship + veteran status, digital signature with font choice. Dues: **$55.00**. DD214 with SSN blacked out submitted separately.

### `/why-join`
Real, substantive copy: camaraderie ("Your buddy still needs you!"), funeral honor guards, parades/memorials, Boy State sponsorships, scholarships, "serve our God, our Country and our Community." Notes the Legion is "the largest veteran's organization in the United States with over 3 million members" (founded 1919).

### `/flag-etiquette`
Real content: when to display the flag throughout the year, ceremonial flag-folding instructions, symbolic meaning of the 13 folds used at military funerals.

---

## ⚠️ Partially Populated Pages

Some real info but thin — decide during rebuild whether to expand, merge, or remove.

### `/special-events`
One real event (American Legion Birthday Dinner at Landmark Family Restaurant, Mar 19 2026, 5 PM, open to Post/Unit/Squad 5 + one guest each). Otherwise duplicates the events list. **Recommendation:** merge into a single Events page.

### `/sons-of-legion`
Minimal: eligibility explained, one contact (**Ronnie Grist, 678-232-3381**), one upcoming meeting referenced. No officers, no activities, no meeting frequency.

### `/site/photogallery`
Three albums: "Demo Gallery", "We Care Vet Fair 2025", "Memorial Day 2025". Thumbnails present but **no captions or descriptions**. "Demo Gallery" leftover from initial setup should be deleted.

### `/membership`
Hub page with no real content of its own — just routes to TAL/ALA/SAL sub-pages. No dues amount, no application link directly. **Recommendation:** consolidate with `/membership-requirements` and `/site/application` into a single Membership page.

---

## ❌ Unpopulated Pages

Either explicit Legionsites template placeholders the post never filled in, OR generic national-Legion copy with no local content. These do **not** need to be migrated as-is.

### Empty template placeholders ("type your content here")

#### `/post-location`
Placeholder text: "copy your Post's location map here. Add appropriate directions and instructions, as required." **No map, no directions.** Address only appears in the global footer.

#### `/board-of-directors`
Placeholder text only: "List the names of your Board of Directors, and pertinent information. Photos of your Directors could be posted on this page." **No board members listed.**

#### `/committees`
Placeholder header only: "List the Names of Current Committees, Members, Time and Dates, Topics and Agenda, etc." **No committees listed.**

#### `/newsletter`
Generic placeholder filenames only: `NewsletterOne.pdf`, `NewsletterTwo.pdf`, `NewsletterThree.pdf`, `NewsletterFour.pdf`. Suggestion text from template still visible: "Show current newsletter under Latest Newsletter link and show newsletters by month and year under the Archives link." **No real newsletter content.**

#### `/post-history`
Generic Legion template with blank fields ("five veterans met to establish the post", first commander/adjutant/finance officer positions, etc.). Instruction text still visible: "Assign your Historian to write your Post History." **No actual history for Shanklin Attaway Post 5.**

#### `/rental-information`
Placeholder text: "Type in information regarding the Rental Of Your Facilities, Services, Photos, and other options you offer." **No pricing, photos, booking process, or contact.**

#### `/site/resourcerentals`
Empty rental calendar widget. Header "Below the calendar are listed the details of our resources" followed by **no resource details**. No bookings shown.

#### `/auxiliary-bulletin`
No bulletin content. Functions as a navigation hub only.

### Generic national-Legion copy (no local customization)

#### `/oratorical`
Generic national Oratorical Contest description. Generic CTA: "Start an Oratorical Contest for your children at your Post." **No Post 5 local contest info** — no dates, eligibility, registration, or past participants.

#### `/legion-riders`
National Legion Riders program description (Rolling Thunder, Patriot Guard, motorcycle safety). Ends with "For more information: www.legion.org/riders." **No local Post 5 chapter info, officers, rides, or contacts** — likely no local chapter exists.

#### `/benefits`
Empty BENEFITS header with one generic line about "Member Discount Programs" referring users to the national Legion site. **No real local benefits content.**

#### `/national-headquarters`
Generic national chartering paragraph plus a link to legion.org. **Could be a single nav link instead of a page.**

#### `/state-headquarters`
**Single link** to georgialegion.org. No content. **Could be a single nav link instead of a page.**

#### `/affiliated-websites`
Duplicates the same outbound links that already appear in the site footer (legion.org, mylegion.org, national Legion social media). **Redundant — delete.**

---

## Notable Real Facts Embedded in the Site

(Not always surfaced where you'd expect.)

- **Post meeting location ("The Farm" / Walker Farm):** 493 Jones Bend Rd NE, Rome, GA 30165 — *not on the homepage; only discoverable via `/post-information` and `/post-updates`.*
- **Auxiliary meeting location:** Brookdale Senior Living, 180 Woodrow Wilson Way, Rome, GA 30165
- **Mailing address:** P.O. Box 945, Rome, GA 30162
- **Post email:** romepost5@gmail.com — *only on `/post-officers`, not on `/site/contactus`.*
- **Annual dues:** $55.00 (from the application page)
- **Charter year:** 1925
- **Facebook:** Post 5 has both a Facebook page and a Facebook group (linked from `/post-information`)

## Known Issues Worth Flagging Before Rebuild

1. **Phone-number conflict:** Contact page and officers page list Albert Hollis as **(951) 204-8635**. Events calendar lists him as **(951) 201-8635**. One of these is wrong by a single digit.
2. **Area code 951 is California (Inland Empire)** — verify this is correct for a commander based in Rome, GA.
3. **Naming inconsistency:** "Shanklin Attaway Post 5" (header/branding) vs "Rome Post 5" (body copy).
4. **Contact form omits the published post email** (romepost5@gmail.com), only routes to phone numbers.
5. **"Demo Gallery" leftover** from the Legionsites template still visible in the photo gallery.
6. **Newsletter PDFs have generic placeholder names** (NewsletterOne–Four) — almost certainly never replaced with real newsletters.

---

## Migration Recommendation

**Pages to bring forward (with edits):** ~11 populated + reworked versions of the 4 partial ones = roughly **10–12 pages of real content** in the rebuild.

**Pages to drop entirely:** the 14 unpopulated pages, unless the post specifically wants to populate them — at which point we'd build them fresh rather than migrate.

**Net result:** the rebuild is meaningfully smaller than the existing site, and most of the work is content authoring (post history, rental info, officer photos) rather than copy/paste.
