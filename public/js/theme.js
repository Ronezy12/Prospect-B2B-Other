// Applique le thème choisi avant l'affichage (évite le flash de couleurs).
(function () {
  try {
    var t = localStorage.getItem('pm-theme');
    if (t === 'dark' || t === 'light') { document.documentElement.setAttribute('data-theme', t); }
  } catch (e) { /* stockage indisponible : thème du système */ }
})();
