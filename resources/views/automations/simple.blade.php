@extends('layouts.app')
@section('title', 'Relances automatiques')

@section('content')
<div class="page-header">
    <div>
        <h1>Relances automatiques</h1>
        <p class="subtitle">Allumez ce que l'outil doit faire à votre place. Vous pouvez éteindre à tout moment.</p>
    </div>
</div>
@error('automation')<div class="alert alert-danger"><x-icon name="circle-alert" /><span>{{ $message }}</span></div>@enderror

<div class="rule-list">
    @foreach ($rules as $rule)
        @php($on = $rule['automation']?->status === 'active')
        <form method="POST" action="{{ route('pm.automations.toggle', $rule['key']) }}" @class(['rule-row', 'on' => $on])>
            @csrf
            <input type="hidden" name="on" value="{{ $on ? 0 : 1 }}">
            <div class="grow">
                <div class="rule-sentence"><span class="rule-when">Quand</span> {{ $rule['when'] }} <span class="rule-then">alors</span> {{ $rule['then'] }}</div>
                @if ($rule['automation'] && $rule['automation']->status === 'en_pause')<div class="small muted">Désactivée</div>@endif
            </div>
            <button type="submit" class="switch" role="switch" aria-checked="{{ $on ? 'true' : 'false' }}" aria-label="{{ $on ? 'Désactiver' : 'Activer' }} : quand {{ $rule['when'] }}"><span class="knob"></span><span class="switch-label">{{ $on ? 'Activé' : 'Désactivé' }}</span></button>
        </form>
    @endforeach
</div>

<p class="small muted section-gap">Besoin d'une règle sur mesure ? <a href="{{ route('pm.automations.index') }}">Mode avancé</a>{{ $customCount ? ' ('.$customCount.' règle(s) personnalisée(s))' : '' }}.</p>
@endsection
