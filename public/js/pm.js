// Prospection & Marketing — comportements d'interface (sans dépendance, compatible CSP).
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    // Menu mobile
    var toggle = document.querySelector('[data-sidebar-toggle]');
    var sidebar = document.querySelector('.sidebar');
    if (toggle && sidebar) {
      toggle.addEventListener('click', function () {
        var open = sidebar.classList.toggle('open');
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
    }

    // Confirmation des actions sensibles
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
      form.addEventListener('submit', function (event) {
        if (!window.confirm(form.getAttribute('data-confirm'))) {
          event.preventDefault();
        }
      });
    });

    // Sélection multiple et actions groupées
    var bulkForm = document.querySelector('[data-bulk-form]');
    if (bulkForm) {
      var master = document.querySelector('[data-check-all]');
      var boxes = function () { return document.querySelectorAll('[data-bulk-item]'); };
      var counter = bulkForm.querySelector('[data-bulk-count]');
      var submit = bulkForm.querySelector('[data-bulk-submit]');
      var action = bulkForm.querySelector('[name=action]');

      var refresh = function () {
        var count = Array.prototype.filter.call(boxes(), function (b) { return b.checked; }).length;
        if (counter) { counter.textContent = count; }
        if (submit) { submit.disabled = count === 0 || !action.value; }
      };

      if (master) {
        master.addEventListener('change', function () {
          boxes().forEach(function (b) { b.checked = master.checked; });
          refresh();
        });
      }
      boxes().forEach(function (b) { b.addEventListener('change', refresh); });

      action.addEventListener('change', function () {
        bulkForm.querySelectorAll('[data-bulk-option]').forEach(function (el) {
          var visible = el.getAttribute('data-bulk-option') === action.value;
          el.hidden = !visible;
          el.querySelectorAll('select, input').forEach(function (input) { input.disabled = !visible; });
        });
        refresh();
      });
      bulkForm.querySelectorAll('[data-bulk-option] select, [data-bulk-option] input').forEach(function (i) { i.disabled = true; });

      bulkForm.addEventListener('submit', function (event) {
        if (action.value === 'delete' && !window.confirm('Supprimer les fiches sélectionnées ?')) {
          event.preventDefault();
        }
      });
      refresh();
    }

    // Sous-secteurs dépendants du secteur
    var sector = document.querySelector('[data-sector-select]');
    var subsector = document.querySelector('[data-subsector-select]');
    if (sector && subsector) {
      var syncSub = function () {
        Array.prototype.forEach.call(subsector.options, function (opt) {
          if (!opt.value) { return; }
          var match = opt.getAttribute('data-parent') === sector.value;
          opt.hidden = !match;
          opt.disabled = !match;
          if (!match && opt.selected) { subsector.value = ''; }
        });
      };
      sector.addEventListener('change', syncSub);
      syncSub();
    }

    // Rafraîchissement de la page de suivi d'import en cours
    var poll = document.querySelector('[data-refresh-seconds]');
    if (poll) {
      window.setTimeout(function () { window.location.reload(); }, parseInt(poll.getAttribute('data-refresh-seconds'), 10) * 1000);
    }
  });
})();
