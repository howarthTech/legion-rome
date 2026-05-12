---
title: "Apply or Renew"
description: "Apply for membership, renew, or transfer your membership to Post 5."
---

Use this form to **apply for new membership**, **renew** your existing
membership, or **transfer** your membership to Post 5 from another post.

{{< todo >}}
Form below is a static draft of the structure used by the existing site's
application page. It is not yet wired to a form handler. Once OPS provisions
the Resend API key and PHP-FPM route is in place, this form will POST to
`/_form/application.php` (similar pattern to the contact form). For initial
launch, we may keep this as a "download the PDF and mail it in" page instead
— TBD with the post.
{{< /todo >}}

## What You'll Need

- A completed application (this form, or two printed copies via the
  [PDF version](#) — *TODO: post PDF*)
- A copy of your **DD214** with the Social Security number blacked out
- A check or money order for **$55** annual dues, payable to
  *American Legion Post 5*

Mail dues and DD214 separately to:

> American Legion Post 5
> {{< maps-link "P.O. Box 945, Rome, GA 30162" >}}

## Application

<div id="form-error-region" class="form-error" role="alert" hidden></div>

<form class="form" action="/_form/application.php" method="POST" novalidate>

<div class="form-field">
  <label for="app-type">I am submitting <span class="form-required" aria-hidden="true">*</span></label>
  <select id="app-type" name="application_type" required>
    <option value="">Choose one…</option>
    <option value="new">A new membership application</option>
    <option value="renewal">A renewal</option>
    <option value="transfer">A transfer to Post 5</option>
  </select>
</div>

<fieldset class="form-field">
  <legend><strong>Personal Information</strong></legend>
</fieldset>

<div class="form-field">
  <label for="app-first">First name <span class="form-required" aria-hidden="true">*</span></label>
  <input id="app-first" name="first_name" type="text" autocomplete="given-name" required>
</div>

<div class="form-field">
  <label for="app-middle">Middle initial</label>
  <input id="app-middle" name="middle_initial" type="text" maxlength="1" autocomplete="additional-name">
</div>

<div class="form-field">
  <label for="app-last">Last name <span class="form-required" aria-hidden="true">*</span></label>
  <input id="app-last" name="last_name" type="text" autocomplete="family-name" required>
</div>

<div class="form-field">
  <label for="app-email">Email <span class="form-required" aria-hidden="true">*</span></label>
  <input id="app-email" name="email" type="email" autocomplete="email" required>
</div>

<div class="form-field">
  <label for="app-phone">Phone</label>
  <input id="app-phone" name="phone" type="tel" autocomplete="tel">
</div>

<div class="form-field">
  <label for="app-dob">Date of birth</label>
  <input id="app-dob" name="date_of_birth" type="date" autocomplete="bday">
</div>

<div class="form-field">
  <label for="app-address">Mailing address</label>
  <input id="app-address" name="address" type="text" autocomplete="street-address">
</div>

<div class="form-field">
  <label for="app-city">City</label>
  <input id="app-city" name="city" type="text" autocomplete="address-level2">
</div>

<div class="form-field">
  <label for="app-state">State</label>
  <input id="app-state" name="state" type="text" autocomplete="address-level1" maxlength="2" placeholder="GA">
</div>

<div class="form-field">
  <label for="app-zip">ZIP</label>
  <input id="app-zip" name="zip" type="text" autocomplete="postal-code" inputmode="numeric" aria-describedby="app-zip-help">
  <p id="app-zip-help" class="form-help">5- or 9-digit US ZIP code.</p>
</div>

<fieldset class="form-field">
  <legend><strong>Service Information</strong></legend>
</fieldset>

<div class="form-field">
  <label for="app-branch">Branch of service <span class="form-required" aria-hidden="true">*</span></label>
  <select id="app-branch" name="branch" required>
    <option value="">Choose one…</option>
    <option>Army</option>
    <option>Navy</option>
    <option>Air Force</option>
    <option>Marines</option>
    <option>Space Force</option>
    <option>Coast Guard</option>
    <option>Merchant Marines</option>
  </select>
</div>

<div class="form-field">
  <label for="app-era">Service era</label>
  <select id="app-era" name="era">
    <option value="">Choose one…</option>
    <option>WWII (December 7, 1941 – December 31, 1946)</option>
    <option>Korea (June 25, 1950 – January 31, 1955)</option>
    <option>Vietnam (February 28, 1961 – May 7, 1975)</option>
    <option>Lebanon / Grenada (August 24, 1982 – July 31, 1984)</option>
    <option>Panama (December 20, 1989 – January 31, 1990)</option>
    <option>Gulf War / Global War on Terror (August 2, 1990 – present)</option>
  </select>
</div>

<div class="form-field">
  <label for="app-former">Former Department / Post (transfers only)</label>
  <input id="app-former" name="former_post" type="text">
</div>

<div class="form-field">
  <label for="app-membership-id">Existing membership ID (renewals/transfers)</label>
  <input id="app-membership-id" name="membership_id" type="text">
</div>

<div class="form-field">
  <label for="app-notes">Anything else we should know?</label>
  <textarea id="app-notes" name="notes" rows="4"></textarea>
</div>

<!-- Honeypot field — bots fill this, humans don't see it -->
<div class="form-honeypot" aria-hidden="true">
  <label for="app-website">Website (leave blank)</label>
  <input id="app-website" name="website" type="text" tabindex="-1" autocomplete="off">
</div>

<button type="submit" class="form-submit">Submit Application</button>
<p class="form-help" id="app-submit-help">
  After submitting, please mail your DD214 (SSN blacked out) and a check/money order
  for $55 to P.O. Box 945, Rome, GA 30162.
</p>
</form>

{{< form-flash >}}
