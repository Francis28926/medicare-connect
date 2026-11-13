// Live regions: any element with data-live refreshes itself every 10 seconds
// (real-time queue updates, SRS 3.2) without losing focus on the rest of the page.
(function () {
  var regions = document.querySelectorAll('[data-live]');
  if (!regions.length) return;
  setInterval(function () {
    if (document.hidden) return;
    if (document.activeElement && /^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName)) return;
    fetch(location.href, { credentials: 'same-origin' })
      .then(function (r) { return r.text(); })
      .then(function (html) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        regions.forEach(function (el) {
          var fresh = doc.querySelector('[data-live="' + el.getAttribute('data-live') + '"]');
          if (fresh) el.innerHTML = fresh.innerHTML;
        });
      }).catch(function () {});
  }, 10000);
})();

// Confirm dialogs for destructive actions: <form data-confirm="Are you sure?">
document.addEventListener('submit', function (e) {
  var m = e.target.getAttribute && e.target.getAttribute('data-confirm');
  if (m && !window.confirm(m)) e.preventDefault();
});

// Prescription rows: add another medication line
(function () {
  var btn = document.getElementById('add-med');
  if (!btn) return;
  btn.addEventListener('click', function () {
    var rows = document.getElementById('med-rows');
    var first = rows.querySelector('.med-row');
    var clone = first.cloneNode(true);
    clone.querySelectorAll('input').forEach(function (i) { i.value = ''; });
    rows.appendChild(clone);
  });
})();
