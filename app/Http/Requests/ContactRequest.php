<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // vérifié par la policy dans le contrôleur
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', Rule::exists('pm_companies', 'id')->whereNull('deleted_at')],
            'civility' => ['nullable', Rule::in(['M.', 'Mme'])],
            'first_name' => ['nullable', 'string', 'max:100', 'required_without_all:last_name,email'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'job_title' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9 +().\-]{6,}$/'],
            'mobile' => ['nullable', 'string', 'max:40', 'regex:/^[0-9 +().\-]{6,}$/'],
            'linkedin_url' => ['nullable', 'url:https', 'max:255', 'regex:#^https://([a-z]{2,3}\.)?linkedin\.com/#i'],
            'audience_type' => ['required', Rule::in(array_keys(config('prospection.audience_types')))],
            'legal_basis' => ['required', Rule::in(array_keys(config('prospection.legal_bases')))],
            'source_id' => ['nullable', 'exists:pm_sources,id'],
            'collected_at' => ['nullable', 'date', 'before_or_equal:today'],
            'collection_note' => ['nullable', 'string', 'max:255'],
            'is_primary' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'tracking_consent' => ['nullable', 'boolean'],
            'tracking_consent_source' => ['required_if_accepted:tracking_consent', 'nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'company_id' => 'entreprise',
            'first_name' => 'prénom',
            'last_name' => 'nom',
            'linkedin_url' => 'profil LinkedIn',
            'audience_type' => 'type de contact',
            'legal_basis' => 'base légale',
            'collected_at' => 'date de collecte',
        ];
    }

    public function messages(): array
    {
        return ['first_name.required_without_all' => 'Indiquez au moins un nom, un prénom ou un e-mail.'];
    }
}
