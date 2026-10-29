/*
 * Visite guidée interactive : met en évidence les éléments d'un écran, étape par étape.
 * Les étapes viennent du serveur (bloc JSON #pm-tour). Aucune dépendance externe,
 * compatible avec la politique de sécurité de contenu (pas de script ni de style en ligne).
 */
(function () {
  'use strict';

  var data = document.getElementById('pm-tour');
  if (!data) { return; }

  var tour;
  try { tour = JSON.parse(data.textContent); } catch (e) { return; }
  var key = data.getAttribute('data-key');
  var endpoint = data.getAttribute('data-endpoint');
  var csrf = document.querySelector('meta[name="csrf-token"]');

  var steps = [];
  var index = 0;
  var spot, bubble, previousFocus;

  function visible(el) {
    if (!el) { return false; }
    var r = el.getBoundingClientRect();
    // Élément hors écran sur le côté (ex. menu replié sur mobile) : étape sautée.
    return r.width > 0 && r.height > 0 && r.right > 0 && r.left < window.innerWidth && getComputedStyle(el).visibility !== 'hidden';
  }

  // Étapes disponibles sur cette page (un élément absent ou masqué est sauté).
  function resolveSteps() {
    return (tour.steps || []).map(function (step) {
      if (!step.target) { return { step: step, el: null }; }
      var el = null;
      try {
        el = Array.prototype.slice.call(document.querySelectorAll(step.target)).filter(visible)[0] || null;
      } catch (e) { el = null; }
      return el ? { step: step, el: el } : null;
    }).filter(Boolean);
  }

  function markDone() {
    if (!endpoint || !csrf) { return; }
    var body = new FormData();
    body.append('key', key);
    fetch(endpoint, { method: 'POST', body: body, credentials: 'same-origin', headers: { 'X-CSRF-TOKEN': csrf.getAttribute('content'), 'Accept': 'application/json' } })
      .catch(function () { /* sans conséquence : la visite sera reproposée */ });
  }

  function el(tag, cls, text) {
    var node = document.createElement(tag);
    if (cls) { node.className = cls; }
    if (text) { node.textContent = text; }
    return node;
  }

  function build() {
    spot = el('div', 'tour-spot');
    spot.setAttribute('aria-hidden', 'true');
    bubble = el('div', 'tour-bubble');
    bubble.setAttribute('role', 'dialog');
    bubble.setAttribute('aria-modal', 'false');
    bubble.setAttribute('aria-labelledby', 'tour-title');
    bubble.tabIndex = -1;
    document.body.appendChild(spot);
    document.body.appendChild(bubble);
    document.addEventListener('keydown', onKey);
    window.addEventListener('resize', place);
    window.addEventListener('scroll', place, true);
  }

  function close() {
    if (!bubble) { return; }
    document.removeEventListener('keydown', onKey);
    window.removeEventListener('resize', place);
    window.removeEventListener('scroll', place, true);
    spot.remove();
    bubble.remove();
    spot = bubble = null;
    document.body.classList.remove('tour-active');
    markDone();
    if (previousFocus && previousFocus.focus) { previousFocus.focus(); }
  }

  function onKey(e) {
    if (e.key === 'Escape') { close(); }
    else if (e.key === 'ArrowRight') { go(index + 1); }
    else if (e.key === 'ArrowLeft') { go(index - 1); }
  }

  function render() {
    var current = steps[index];
    var last = index === steps.length - 1;
    bubble.textContent = '';

    var head = el('div', 'tour-head');
    head.appendChild(el('span', 'tour-count', (index + 1) + ' / ' + steps.length));
    var skip = el('button', 'tour-close', '×');
    skip.type = 'button';
    skip.setAttribute('aria-label', 'Fermer la visite guidée');
    skip.addEventListener('click', function () { close(); });
    head.appendChild(skip);
    bubble.appendChild(head);

    var title = el('h3', 'tour-title', current.step.title);
    title.id = 'tour-title';
    bubble.appendChild(title);
    bubble.appendChild(el('p', 'tour-text', current.step.text));

    if (last && tour.next && tour.next.url) {
      var tip = el('div', 'tour-next');
      tip.appendChild(el('span', 'tour-next-label', 'Suggestion pour continuer'));
      var link = el('a', 'btn btn-sm btn-primary', tour.next.label + ' →');
      link.href = tour.next.url;
      link.addEventListener('click', function () { markDone(); });
      tip.appendChild(link);
      bubble.appendChild(tip);
    }

    var dots = el('div', 'tour-dots');
    steps.forEach(function (_, i) { dots.appendChild(el('span', i === index ? 'on' : '')); });

    var actions = el('div', 'tour-actions');
    actions.appendChild(dots);
    if (index > 0) {
      var prev = el('button', 'btn btn-sm', 'Précédent');
      prev.type = 'button';
      prev.addEventListener('click', function () { go(index - 1); });
      actions.appendChild(prev);
    }
    var next = el('button', 'btn btn-sm btn-primary', last ? 'Terminer' : 'Suivant');
    next.type = 'button';
    next.addEventListener('click', function () { last ? close() : go(index + 1); });
    actions.appendChild(next);
    bubble.appendChild(actions);

    if (current.el) {
      current.el.scrollIntoView({ block: 'center', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
    }
    // Laisse le défilement se terminer avant de positionner la bulle.
    setTimeout(place, current.el ? 260 : 0);
    place();
    next.focus({ preventScroll: true });
  }

  function place() {
    if (!bubble) { return; }
    var current = steps[index];
    var margin = 12;
    var vw = window.innerWidth;
    var vh = window.innerHeight;
    var mobile = vw < 640;

    if (!current.el) {
      spot.className = 'tour-spot tour-spot-full';
      bubble.className = 'tour-bubble tour-center';
      bubble.style.top = '';
      bubble.style.left = '';
      return;
    }

    var r = current.el.getBoundingClientRect();
    var pad = 6;
    spot.className = 'tour-spot';
    spot.style.top = (r.top - pad) + 'px';
    spot.style.left = (r.left - pad) + 'px';
    spot.style.width = (r.width + pad * 2) + 'px';
    spot.style.height = (r.height + pad * 2) + 'px';

    if (mobile) {
      bubble.className = 'tour-bubble tour-sheet';
      bubble.style.top = '';
      bubble.style.left = '';
      return;
    }

    bubble.className = 'tour-bubble';
    var bw = bubble.offsetWidth;
    var bh = bubble.offsetHeight;
    var top;
    if (r.bottom + margin + bh < vh) { top = r.bottom + margin; }
    else if (r.top - margin - bh > 0) { top = r.top - margin - bh; }
    else { top = Math.max(margin, Math.min(vh - bh - margin, r.top + r.height / 2 - bh / 2)); }
    var left = Math.max(margin, Math.min(vw - bw - margin, r.left + r.width / 2 - bw / 2));
    // Élément très haut (ex. tableau) : bulle placée à côté plutôt que dessus.
    if (r.height > vh * 0.6 && r.right + margin + bw < vw) { left = r.right + margin; top = Math.max(margin, Math.min(vh - bh - margin, vh / 2 - bh / 2)); }
    bubble.style.top = top + 'px';
    bubble.style.left = left + 'px';
  }

  function go(i) {
    if (i < 0 || i >= steps.length) { return; }
    index = i;
    render();
  }

  function start() {
    steps = resolveSteps();
    if (!steps.length) { return; }
    if (bubble) { close(); }
    previousFocus = document.activeElement;
    index = 0;
    document.body.classList.add('tour-active');
    build();
    render();
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-tour-start]').forEach(function (button) {
      button.addEventListener('click', start);
    });
    if (data.getAttribute('data-autostart') === '1') {
      // Laisse la page (et l'éditeur éventuel) finir de s'afficher.
      setTimeout(start, 500);
    }
  });
})();
