@if (! empty($estimate['reasons']))
    <div class="removed">
        <span class="small muted">Retirées automatiquement :</span>
        <ul class="small muted">@foreach ($estimate['reasons'] as $reason)<li>{{ $reason }}</li>@endforeach</ul>
    </div>
@endif
