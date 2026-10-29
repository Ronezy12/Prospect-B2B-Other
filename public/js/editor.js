// Éditeur visuel des modèles d'e-mails (GrapesJS + preset newsletter, licences BSD-3).
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var host = document.querySelector('[data-editor]');
    var form = document.querySelector('[data-editor-form]');
    if (!host || !form || !window.grapesjs) { return; }

    var htmlField = form.querySelector('[data-editor-html]');
    var designField = form.querySelector('[data-editor-design]');
    var preset = window['grapesjs-preset-newsletter'];

    var editor = window.grapesjs.init({
      container: host,
      height: '640px',
      fromElement: false,
      storageManager: false,
      noticeOnUnload: false,
      // Aucune donnée envoyée à l'éditeur de la bibliothèque ; icônes servies localement.
      telemetry: false,
      cssIcons: '/vendor/font-awesome/css/font-awesome.min.css',
      i18n: window.gjsLocaleFr ? { locale: 'fr', detectLocale: false, messages: { fr: window.gjsLocaleFr } } : {},
      plugins: preset ? [preset.default || preset] : [],
      pluginsOpts: {},
      assetManager: { upload: false, embedAsBase64: false },
      deviceManager: {
        devices: [
          { id: 'desktop', name: 'Ordinateur', width: '' },
          { id: 'mobile', name: 'Mobile', width: '375px', widthMedia: '480px' }
        ]
      }
    });

    // Contenu existant : projet GrapesJS si disponible, sinon HTML.
    var design = null;
    try { design = designField.value ? JSON.parse(designField.value) : null; } catch (e) { design = null; }
    if (design && design.pages) { editor.loadProjectData(design); } else { editor.setComponents(htmlField.value || ''); }

    // Blocs « Variables » : insèrent {{variable}} remplacé à l'envoi par la donnée du contact.
    var variables = {
      prenom: 'Prénom', nom: 'Nom', civilite: 'Civilité', fonction: 'Fonction', entreprise: 'Entreprise',
      ville: 'Ville', expediteur: 'Expéditeur'
    };
    Object.keys(variables).forEach(function (key) {
      editor.BlockManager.add('var-' + key, {
        label: variables[key], category: 'Variables',
        content: { type: 'text', content: '{{' + key + '}}', style: { display: 'inline' } }
      });
    });
    editor.BlockManager.add('var-desinscription', {
      label: 'Lien de désinscription', category: 'Variables',
      content: '<p style="font-size:12px;color:#888888"><a href="{{lien_desinscription}}" style="color:#888888">Se désinscrire</a></p>'
    });

    form.addEventListener('submit', function () {
      var html = editor.runCommand('gjs-get-inlined-html');
      htmlField.value = html || editor.getHtml();
      designField.value = JSON.stringify(editor.getProjectData());
    });
  });
})();
