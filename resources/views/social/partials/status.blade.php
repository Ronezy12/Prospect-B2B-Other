<span @class([
    'badge',
    'badge-primary' => in_array($status, ['invitation_envoyee', 'message_envoye', 'connecte'], true),
    'badge-warning' => $status === 'en_discussion',
    'badge-success' => $status === 'rdv_obtenu',
    'badge-danger' => $status === 'ne_plus_contacter',
])>{{ config('prospection.social.statuses')[$status] ?? $status }}</span>
