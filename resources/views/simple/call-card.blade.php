{{-- Une personne à appeler : nom, téléphone en grand, boutons de résultat. --}}
@php($contact = $company->contacts->first())
@php($phone = $contact?->mobile ?? $contact?->phone ?? $company->phone)
<div class="s-card">
    <div class="s-card-head">
        <div>
            <a class="s-name" href="{{ route('pm.companies.show', $company) }}">{{ $company->name }}</a>
            <div class="s-sub">{{ $contact?->fullName() ?? 'Personne à contacter non renseignée' }}@if ($company->city) · {{ $company->city }}@endif</div>
            @isset($why)<div class="s-why">{{ $why }}</div>@endisset
        </div>
        @if ($phone)
            <a class="s-call" href="tel:{{ $phone }}">📞 <span>{{ \App\Support\Normalizer::displayPhone($phone) }}</span></a>
        @endif
    </div>
    <p class="s-ask">Après l'appel, touchez ce qui s'est passé :</p>
    @include('simple.outcomes', ['company' => $company])
</div>
