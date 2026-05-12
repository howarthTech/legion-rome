// Tiny vanilla-JS lightbox using the native <dialog> element.
// ~80 lines, no dependencies, full keyboard support (Esc, ←, →).
//
// Markup contract (set up by layouts/gallery/single.html):
//   <div data-lightbox-album>             ← parent collecting the triggers
//     <a data-lightbox-trigger data-full="…" data-caption="…">
//       <img …>
//     </a>
//   </div>
//   <dialog class="lightbox" id="lightbox">
//     [data-lightbox-close] [data-lightbox-prev] [data-lightbox-next]
//     <img class="lightbox-img"> <figcaption class="lightbox-caption">

(function () {
  'use strict';

  var dlg = document.getElementById('lightbox');
  if (!dlg || typeof dlg.showModal !== 'function') return;  // older browser: anchors still work

  var img = dlg.querySelector('.lightbox-img');
  var cap = dlg.querySelector('.lightbox-caption');
  var btnClose = dlg.querySelector('[data-lightbox-close]');
  var btnPrev  = dlg.querySelector('[data-lightbox-prev]');
  var btnNext  = dlg.querySelector('[data-lightbox-next]');

  // Collect every trigger in document order across all albums on the page
  var triggers = Array.prototype.slice.call(
    document.querySelectorAll('[data-lightbox-trigger]')
  );
  if (triggers.length === 0) return;

  var current = 0;

  function show(idx) {
    if (idx < 0) idx = triggers.length - 1;
    if (idx >= triggers.length) idx = 0;
    current = idx;
    var t = triggers[idx];
    img.src = t.getAttribute('data-full') || t.href;
    var caption = t.getAttribute('data-caption') || '';
    cap.textContent = caption;
    cap.hidden = !caption;
    img.alt = caption;
    if (!dlg.open) dlg.showModal();
  }

  triggers.forEach(function (t, i) {
    t.addEventListener('click', function (e) {
      e.preventDefault();
      show(i);
    });
  });

  btnClose && btnClose.addEventListener('click', function () { dlg.close(); });
  btnPrev  && btnPrev.addEventListener('click',  function () { show(current - 1); });
  btnNext  && btnNext.addEventListener('click',  function () { show(current + 1); });

  // Click backdrop (the <dialog> itself, not its content) to close
  dlg.addEventListener('click', function (e) {
    if (e.target === dlg) dlg.close();
  });

  // Arrow keys when open
  document.addEventListener('keydown', function (e) {
    if (!dlg.open) return;
    if (e.key === 'ArrowLeft')  { e.preventDefault(); show(current - 1); }
    if (e.key === 'ArrowRight') { e.preventDefault(); show(current + 1); }
    // Esc closes natively
  });
})();
