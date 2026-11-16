<article class="s-card">
    <div class="s-card-head">
        <x-avatar :name="$profile->company->name" />
        <div class="s-card-id">
            <a class="s-name" href="{{ route('pm.social.show', $profile) }}">{{ $profile->contact?->fullName() ?? $profile->company->name }}</a>
            <div class="s-sub">@include('social.partials.platform', ['platform' => $profile->platform]) {{ $profile->company->name }}@if ($profile->company->city) · {{ $profile->company->city }}@endif</div>
            <div class="s-why"><x-icon name="clock" /> {{ $profile->statusLabel() }}@if ($profile->next_action_at) · prévu le {{ $profile->next_action_at->format('d/m') }}@endif</div>
        </div>
        <a class="s-call neutral" href="{{ $profile->url }}" target="_blank" rel="noopener noreferrer nofollow"><x-icon name="external-link" /><span>Ouvrir le profil</span></a>
    </div>
    <form method="POST" action="{{ route('pm.social.quick', $profile) }}" class="s-outcomes small" aria-label="Action réalisée">
        @csrf
        <button type="submit" name="action" value="invitation" class="s-out"><x-icon name="user-round-plus" /><span>Invitation envoyée</span></button>
        <button type="submit" name="action" value="message" class="s-out"><x-icon name="send" /><span>Message envoyé</span></button>
        <button type="submit" name="action" value="reponse" class="s-out good"><x-icon name="message-circle-reply" /><span>A répondu</span></button>
        <button type="submit" name="action" value="non" class="s-out bad"><x-icon name="circle-x" /><span>Pas intéressé</span></button>
    </form>
</article>
