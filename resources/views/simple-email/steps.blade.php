<div class="steps wizard-steps">
    @foreach ([1 => 'À qui ?', 2 => 'Quel message ?', 3 => 'Vérifier et envoyer'] as $n => $label)
        <span @class(['step', 'current' => $current === $n, 'done' => $current > $n])><b>@if ($current > $n)<x-icon name="check" />@else{{ $n }}@endif</b> {{ $label }}</span>
    @endforeach
</div>
