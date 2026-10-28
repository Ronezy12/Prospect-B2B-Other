@php($tone = ['nouveau' => 'info', 'a_qualifier' => 'warning', 'qualifie' => 'success', 'non_pertinent' => 'danger', 'en_sommeil' => ''][$status] ?? '')
<span @class(['badge', 'badge-'.$tone => $tone])>{{ config('prospection.company_statuses')[$status] ?? $status }}</span>
