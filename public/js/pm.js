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

    // Thème clair / sombre (préférence locale, sinon thème du système)
    var themeBtn = document.querySelector('[data-theme-toggle]');
    if (themeBtn) {
      themeBtn.addEventListener('click', function () {
        var root = document.documentElement;
        var current = root.getAttribute('data-theme') ||
          (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        var next = current === 'dark' ? 'light' : 'dark';
        root.setAttribute('data-theme', next);
        try { localStorage.setItem('pm-theme', next); } catch (e) { /* ignoré */ }
      });
    }

    // Confirmation des actions sensibles
    // Réseaux sociaux : copier un message type, compteur de caractères.
    document.querySelectorAll('[data-copy]').forEach(function (button) {
      button.addEventListener('click', function () {
        var field = document.getElementById(button.getAttribute('data-copy'));
        if (!field) { return; }
        var done = function () {
          var label = button.lastChild;
          var previous = label.textContent;
          label.textContent = ' Copié';
          setTimeout(function () { label.textContent = previous; }, 1800);
        };
        if (navigator.clipboard && window.isSecureContext) {
          navigator.clipboard.writeText(field.value).then(done, function () { field.select(); document.execCommand('copy'); done(); });
        } else {
          field.select(); document.execCommand('copy'); done();
        }
      });
    });
    var updateCount = function (field, output, limit) {
      var n = Array.from(field.value).length;
      output.textContent = output.hasAttribute('data-count-for') ? n + ' caractères' : n;
      output.classList.toggle('over-limit', !!limit && n > limit);
    };
    document.querySelectorAll('[data-count-for]').forEach(function (output) {
      var field = document.getElementById(output.getAttribute('data-count-for'));
      var limit = parseInt(output.getAttribute('data-limit') || '0', 10);
      if (field) { field.addEventListener('input', function () { updateCount(field, output, limit); }); updateCount(field, output, limit); }
    });
    document.querySelectorAll('textarea[data-count]').forEach(function (field) {
      var output = document.getElementById(field.getAttribute('data-count'));
      if (output) { field.addEventListener('input', function () { updateCount(field, output, 0); }); }
    });

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

    // Kanban : glisser-déposer des opportunités, enregistré côté serveur.
    var board = document.querySelector('[data-board]');
    if (board) {
      var token = (document.querySelector('meta[name=csrf-token]') || {}).content;
      var dragged = null, origin = null, originNext = null;

      var notice = function (msg, ok) {
        var el = document.querySelector('[data-board-notice]');
        if (!el) { return; }
        el.textContent = msg;
        el.className = 'alert ' + (ok ? 'alert-success' : 'alert-danger');
        el.hidden = false;
        window.clearTimeout(el._t);
        el._t = window.setTimeout(function () { el.hidden = true; }, 3500);
      };
      var bump = function (column, delta) {
        var n = column.closest('.column').querySelector('[data-count]');
        if (n) { n.textContent = Math.max(0, parseInt(n.textContent, 10) + delta); }
      };
      var afterElement = function (container, y) {
        var cards = Array.prototype.filter.call(container.querySelectorAll('.deal'), function (c) { return c !== dragged; });
        for (var i = 0; i < cards.length; i++) {
          var box = cards[i].getBoundingClientRect();
          if (y < box.top + box.height / 2) { return cards[i]; }
        }
        return null;
      };

      board.querySelectorAll('.deal[draggable=true]').forEach(function (card) {
        card.addEventListener('dragstart', function (e) {
          dragged = card; origin = card.parentNode; originNext = card.nextSibling;
          card.classList.add('dragging');
          e.dataTransfer.effectAllowed = 'move';
          e.dataTransfer.setData('text/plain', card.getAttribute('data-id'));
        });
        card.addEventListener('dragend', function () {
          card.classList.remove('dragging');
          board.querySelectorAll('.drop-target').forEach(function (z) { z.classList.remove('drop-target'); });
        });
      });

      board.querySelectorAll('[data-stage-id]').forEach(function (zone) {
        zone.addEventListener('dragover', function (e) {
          if (!dragged) { return; }
          e.preventDefault();
          zone.classList.add('drop-target');
          var after = afterElement(zone, e.clientY);
          if (after) { zone.insertBefore(dragged, after); } else { zone.appendChild(dragged); }
        });
        zone.addEventListener('dragleave', function (e) {
          if (!zone.contains(e.relatedTarget)) { zone.classList.remove('drop-target'); }
        });
        zone.addEventListener('drop', function (e) {
          if (!dragged) { return; }
          e.preventDefault();
          zone.classList.remove('drop-target');
          var card = dragged, from = origin, fromNext = originNext;
          dragged = null;
          var position = Array.prototype.indexOf.call(zone.querySelectorAll('.deal'), card);
          var changed = from !== zone;
          card.classList.add('saving');
          fetch(card.getAttribute('data-move-url'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ stage_id: zone.getAttribute('data-stage-id'), position: position }),
            credentials: 'same-origin'
          }).then(function (r) {
            return r.json().then(function (body) { return { ok: r.ok, body: body }; });
          }).then(function (res) {
            card.classList.remove('saving');
            if (!res.ok) { throw new Error((res.body && res.body.message) || 'Déplacement refusé.'); }
            if (changed) {
              bump(from, -1); bump(zone, 1);
              notice('« ' + card.getAttribute('data-title') + ' » déplacée vers « ' + res.body.stage + ' ».', true);
            }
          }).catch(function (err) {
            card.classList.remove('saving');
            if (fromNext && fromNext.parentNode === from) { from.insertBefore(card, fromNext); } else { from.appendChild(card); }
            notice(err.message || 'Le déplacement n\'a pas pu être enregistré.', false);
          });
        });
      });
    }

    // Formulaire d'opportunité : étapes de la pipeline choisie uniquement
    var pipelineSelect = document.querySelector('[data-pipeline-select]');
    var stageSelect = document.querySelector('[data-stage-select]');
    if (pipelineSelect && stageSelect) {
      var syncStages = function () {
        var firstVisible = null;
        Array.prototype.forEach.call(stageSelect.options, function (opt) {
          var match = opt.getAttribute('data-pipeline') === pipelineSelect.value;
          opt.hidden = !match; opt.disabled = !match;
          if (match && !firstVisible) { firstVisible = opt; }
        });
        var sel = stageSelect.options[stageSelect.selectedIndex];
        if ((!sel || sel.disabled) && firstVisible) { stageSelect.value = firstVisible.value; }
      };
      pipelineSelect.addEventListener('change', syncStages);
      syncStages();
    }

    // Rafraîchissement de la page de suivi d'import en cours
    var poll = document.querySelector('[data-refresh-seconds]');
    if (poll) {
      window.setTimeout(function () { window.location.reload(); }, parseInt(poll.getAttribute('data-refresh-seconds'), 10) * 1000);
    }
  });
})();
