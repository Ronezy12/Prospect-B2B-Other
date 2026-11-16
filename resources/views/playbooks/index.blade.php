@extends('layouts.app')
@section('title', 'Secteurs et modèles')

@section('content')
<div class="s-head">
    <div>
        <h1>Secteurs et modèles d'e-mails</h1>
        <p class="s-lead">Pour chaque type d'entreprise : ce qu'on leur apporte, ce qu'ils y gagnent, les questions pour les qualifier, et 10 e-mails prêts à l'emploi, du premier contact au partenariat signé.</p>
    </div>
</div>

<section class="s-card pb-rules">
    <h2><x-icon name="sparkles" /> Pour qu'un e-mail soit lu, compris et qu'on y réponde</h2>
    <ol>
        <li><strong>Un objet court et concret</strong>, qui parle de leur problème, pas de nous.</li>
        <li><strong>Moins de 120 mots.</strong> Il se lit en 20 secondes sur un téléphone.</li>
        <li><strong>Leur client d'abord</strong> : ce que le partenaire y gagne (ventes, temps, image), puis ce qu'on fait.</li>
        <li><strong>Une seule question à la fin</strong>, à laquelle on répond en une ligne.</li>
        <li><strong>Relancer 2 fois</strong>, avec un angle différent à chaque fois, puis s'arrêter poliment.</li>
        <li><strong>Envoyer en semaine, le matin</strong>, à la bonne personne (le décideur indiqué dans chaque fiche).</li>
        <li><strong>Un accord équilibré</strong> : nous sommes payés pour notre travail, eux gagnent des clients satisfaits ou une commission. C'est ce qui fait durer un partenariat.</li>
    </ol>
    <p class="s-hint">Le module compte les réponses reçues : c'est le vrai indicateur. Une ouverture détectée ne prouve pas que l'e-mail a été lu. En prospection par e-mail, un taux de réponse de quelques pour cent est déjà correct ; un meilleur ciblage et des relances font monter ce chiffre.</p>
</section>

<div class="pb-grid">
    @foreach ($playbooks as $p)
        <a class="pb-card" href="{{ route('pm.playbooks.show', $p['slug']) }}">
            <span class="pb-icon"><x-icon :name="$p['icon']" /></span>
            <strong>{{ $p['name'] }}</strong>
            <span class="pb-pitch">{{ $p['pitch'] }}</span>
            <span class="pb-more">10 e-mails · voir la fiche <x-icon name="chevron-right" /></span>
        </a>
    @endforeach
</div>
@endsection
