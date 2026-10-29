@extends('layouts.app')
@section('title', 'Suivi des e-mails')

@section('content')
<div class="page-header"><div><h1>Suivi des e-mails</h1><p class="subtitle">Mesure des ouvertures et des clics</p></div></div>

<div class="grid grid-sidebar">
    <form method="POST" action="{{ route('pm.settings.tracking.update') }}" class="card">
        @csrf @method('PUT')
        <h2>Autoriser la mesure d'audience</h2>
        <div class="choice-list">
            <label class="checkbox"><input type="hidden" name="opens" value="0"><input type="checkbox" name="opens" value="1" @checked($opens)> Ouvertures (pixel invisible dans l'e-mail)</label>
            <label class="checkbox"><input type="hidden" name="clicks" value="0"><input type="checkbox" name="clicks" value="1" @checked($clicks)> Clics (liens de redirection signés)</label>
        </div>
        <p class="small muted section-gap">Même activée ici, la mesure ne s'applique qu'aux campagnes qui la demandent, et uniquement aux contacts ayant consenti ({{ $consenting }} contact(s) actuellement). Les ouvertures et clics des autres contacts ne sont ni mesurés ni conservés, y compris lorsqu'un fournisseur les remonte.</p>
        <div class="form-actions"><button type="submit" class="btn btn-primary"><x-icon name="check" /> Enregistrer</button></div>
    </form>
    <section class="card">
        <h2><x-icon name="shield-check" /> Cadre réglementaire</h2>
        <p class="small">La CNIL a adopté le 12 mars 2026 une recommandation sur les pixels de suivi dans les e-mails : leur usage relève de l'article 82 de la loi Informatique et Libertés et requiert, par principe, le <strong>consentement préalable</strong> du destinataire. L'inaction vaut refus.</p>
        <p class="small">Le consentement se recueille sur la fiche du contact (case et source de la preuve). Il peut être retiré à tout moment en décochant la case.</p>
        <p class="small muted">Interprétation technique à valider par votre DPO ou un juriste. Une ouverture détectée n'est jamais une preuve de lecture.</p>
    </section>
</div>
@endsection
