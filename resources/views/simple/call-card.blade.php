@php($contact = $company->contacts->first())
@php($phone = $contact?->mobile ?? $contact?->phone ?? $company->phone)
<article class="s-card">
    <div class="s-card-head">
        <x-avatar :name="$company->name" />
        <div class="s-card-id">
            <a class="s-name" href="{{ route('pm.companies.show', $company) }}">{{ $company->name }}</a>
            <div class="s-sub">{{ $contact?->fullName() ?? 'Interlocuteur non renseigné' }}@if ($contact?->job_title) · {{ $contact->job_title }}@endif @if ($company->city) · {{ $company->city }}@endif</div>
            @isset($why)
                <div @class(['s-why', 'late' => $late ?? false])><x-icon name="{{ ($late ?? false) ? 'alarm-clock' : (($new ?? false) ? 'user-round-plus' : 'clock') }}" /> {{ ($late ?? false) ? 'En retard · ' : '' }}{{ $why }}</div>
            @endisset
        </div>
        @if ($phone)
            <a class="s-call" href="tel:{{ $phone }}"><x-icon name="phone" /><span>{{ \App\Support\Normalizer::displayPhone($phone) }}</span></a>
        @endif
    </div>
    @include('simple.outcomes', ['company' => $company])
</article>
