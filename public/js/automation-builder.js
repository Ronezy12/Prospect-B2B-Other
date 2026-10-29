// Constructeur de scénarios : ajout, suppression, réordonnancement des étapes et branches.
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('[data-builder]');
    if (!form) { return; }
    var list = form.querySelector('[data-steps]');
    var template = form.querySelector('[data-step-template]');
    var counter = 1000;

    var cards = function () { return Array.prototype.slice.call(list.querySelectorAll('[data-step]')); };

    var refreshPanels = function (card) {
      var type = card.querySelector('[data-step-type]').value;
      card.querySelectorAll('[data-step-panel]').forEach(function (p) { p.hidden = p.getAttribute('data-step-panel') !== type; });
      var action = card.querySelector('[data-action-select]').value;
      card.querySelectorAll('[data-for-action]').forEach(function (f) {
        f.hidden = f.getAttribute('data-for-action').split(' ').indexOf(action) === -1;
      });
    };

    var renumber = function () {
      var all = cards();
      all.forEach(function (card, index) {
        card.querySelector('[data-step-num]').textContent = 'Étape ' + (index + 1);
        card.querySelectorAll('[name^="steps["]').forEach(function (input) {
          input.name = input.name.replace(/^steps\[[^\]]+\]/, 'steps[' + index + ']');
        });
      });
      // Les branches désignent une étape par identifiant stable (data-uid).
      all.forEach(function (card) {
        card.querySelectorAll('[data-branch]').forEach(function (select) {
          var current = select.value || select.getAttribute('data-current') || '';
          select.innerHTML = '';
          all.forEach(function (other, i) {
            if (other === card) { return; }
            var opt = document.createElement('option');
            opt.value = other.getAttribute('data-uid');
            opt.textContent = 'Étape ' + (i + 1);
            select.appendChild(opt);
          });
          var end = document.createElement('option');
          end.value = 'end'; end.textContent = 'Fin du scénario';
          select.appendChild(end);
          select.value = Array.prototype.some.call(select.options, function (o) { return o.value === current; }) ? current : 'end';
        });
      });
    };

    var bind = function (card) {
      if (!card.getAttribute('data-uid')) { card.setAttribute('data-uid', 'u' + (counter++)); }
      card.querySelector('[data-step-type]').addEventListener('change', function () { refreshPanels(card); });
      card.querySelector('[data-action-select]').addEventListener('change', function () { refreshPanels(card); });
      card.querySelector('[data-step-remove]').addEventListener('click', function () { card.remove(); renumber(); });
      card.querySelector('[data-step-up]').addEventListener('click', function () {
        if (card.previousElementSibling) { list.insertBefore(card, card.previousElementSibling); renumber(); }
      });
      card.querySelector('[data-step-down]').addEventListener('click', function () {
        if (card.nextElementSibling) { list.insertBefore(card.nextElementSibling, card); renumber(); }
      });
      refreshPanels(card);
    };

    cards().forEach(bind);
    renumber();

    form.querySelectorAll('[data-add-step]').forEach(function (button) {
      button.addEventListener('click', function () {
        var html = template.innerHTML.replace(/__INDEX__/g, String(counter));
        var wrap = document.createElement('div');
        wrap.innerHTML = html.trim();
        var card = wrap.firstElementChild;
        card.querySelector('[data-step-type]').value = button.getAttribute('data-add-step');
        list.appendChild(card);
        bind(card);
        renumber();
      });
    });

    // À l'envoi : les identifiants stables deviennent s1, s2… selon la position finale.
    form.addEventListener('submit', function () {
      var map = {};
      cards().forEach(function (card, i) { map[card.getAttribute('data-uid')] = 's' + (i + 1); });
      form.querySelectorAll('[data-branch]').forEach(function (select) {
        var target = map[select.value] || 'end';
        var opt = document.createElement('option');
        opt.value = target; opt.selected = true;
        select.appendChild(opt);
        select.value = target;
      });
    });
  });
})();
