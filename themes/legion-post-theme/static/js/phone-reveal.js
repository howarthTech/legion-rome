// Phone-reveal — decodes a base64 phone number from a button's data-p64
// attribute on click and replaces the button with a tel: link. Stores the
// "I've already opted to see phones on this site" preference in localStorage
// so returning visitors get phone numbers without the extra click.
//
// Markup expected:
//   <button class="phone-reveal" data-p64="…" aria-label="…">…</button>
//
// Resulting markup after reveal:
//   <a href="tel:…" class="phone-revealed">(951) 204-8635</a>
//
// ~60 lines, no dependencies.

(function () {
  'use strict';
  var STORAGE_KEY = 'rome-legion:phones-visible';

  function decode(b64) {
    try { return atob(b64); } catch (e) { return ''; }
  }

  function reveal(btn) {
    var encoded = btn.getAttribute('data-p64');
    var phone = decode(encoded);
    if (!phone) return;
    // tel: links want digits + leading + only — strip anything else
    var tel = phone.replace(/[^0-9+]/g, '');
    if (!tel.startsWith('+')) tel = '+1' + tel.replace(/^1/, '');  // assume US

    var link = document.createElement('a');
    link.href = 'tel:' + tel;
    link.className = 'phone-revealed';
    link.textContent = phone;
    // Match the button's parent context for screen readers — the new link is
    // a normal phone link with default semantics.
    btn.replaceWith(link);
  }

  function init() {
    var buttons = document.querySelectorAll('button.phone-reveal');
    var auto = false;
    try { auto = localStorage.getItem(STORAGE_KEY) === '1'; } catch (e) {}

    buttons.forEach(function (btn) {
      if (auto) {
        // User has revealed phones before on this device — skip the click step
        reveal(btn);
        return;
      }
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        reveal(btn);
        try { localStorage.setItem(STORAGE_KEY, '1'); } catch (err) {}
        // After the first reveal in this pageview, auto-reveal the rest so
        // the user doesn't have to click each one individually.
        document.querySelectorAll('button.phone-reveal').forEach(reveal);
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
