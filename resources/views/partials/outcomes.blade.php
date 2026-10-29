{{-- Boutons « Que s'est-il passé ? » : un clic, l'outil met à jour la fiche, l'affaire et les rappels. --}}
@php($compact = $compact ?? false)
@php($uid = 'o'.$company->id.($compact ? 'c' : ''))
@can('update', $company)
    @if ($compact)
        <details class="outcome-menu">
            <summary class="btn btn-sm btn-primary">Résultat</summary>
            <div class="outcome-pop">
    @endif
    <form method="POST" action="{{ route('pm.companies.outcome', $company) }}" class="outcomes">
        @csrf
        @unless ($compact)<p class="outcomes-q">Vous venez de le contacter ? Que s'est-il passé ?</p>@endunless
        <div class="outcome-buttons">
            @foreach (config('prospection.quick_outcomes') as $key => $o)
                @continue(! empty($o['asks_date']))
                <button type="submit" name="outcome" value="{{ $key }}" @class(['outcome', 'tone-'.$o['tone'] => $o['tone']])><x-icon :name="$o['icon']" /> {{ $o['label'] }}</button>
            @endforeach
        </div>
        <div class="outcome-extra">
            <label class="sr-only" for="{{ $uid }}-note">Note (facultatif)</label>
            <input type="text" id="{{ $uid }}-note" name="note" maxlength="1000" placeholder="Note facultative (ex. rappeler après 14 h)">
            <span class="outcome-rdv">
                <label for="{{ $uid }}-date">Rendez-vous le</label>
                <input type="date" id="{{ $uid }}-date" name="date" min="{{ now()->toDateString() }}" value="{{ (fn ($d) => $d->isSaturday() ? $d->addDays(2) : ($d->isSunday() ? $d->addDay() : $d))(now()->addDays(2))->toDateString() }}">
                <button type="submit" name="outcome" value="rdv" class="outcome tone-good"><x-icon name="calendar" /> Rendez-vous pris</button>
            </span>
        </div>
    </form>
    @if ($compact)
            </div>
        </details>
    @endif
@endcan
