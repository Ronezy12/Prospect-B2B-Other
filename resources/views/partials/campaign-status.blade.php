@php($tone = ['brouillon' => '', 'programmee' => 'info', 'en_cours' => 'primary', 'en_pause' => 'warning', 'terminee' => 'success', 'annulee' => 'danger'][$status] ?? '')
<span @class(['badge', 'badge-'.$tone => $tone])>{{ config('prospection.campaign_statuses')[$status] ?? $status }}</span>
