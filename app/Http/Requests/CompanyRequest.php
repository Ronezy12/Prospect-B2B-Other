<?php

namespace App\Http\Requests;

use App\Support\Normalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // vérifié par la policy dans le contrôleur
    }

    public function rules(): array
    {
        $creating = $this->isMethod('post');

        return [
            'name' => ['required', 'string', 'max:190'],
            'registration_number' => ['nullable', 'string', 'max:20'],
            'sector_id' => ['nullable', Rule::exists('pm_sectors', 'id')->whereNull('parent_id')],
            'subsector_id' => ['nullable', Rule::exists('pm_sectors', 'id')->where('parent_id', $this->input('sector_id'))],
            'website' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9 +().\-]{6,}$/'],
            'address' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:12'],
            'city' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'size:2', 'alpha'],
            'source_id' => ['nullable', 'exists:pm_sources,id'],
            'acquired_at' => ['nullable', 'date', 'before_or_equal:today'],
            'status' => ['required', Rule::in(array_keys(config('prospection.company_statuses')))],
            'owner_id' => ['nullable', Rule::exists('users', 'id')->where('is_active', true)],
            'tags' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'confirm_duplicate' => ['nullable', 'boolean'],

            // Premier interlocuteur (création uniquement, facultatif).
            'contact.first_name' => [$creating ? 'nullable' : 'prohibited', 'string', 'max:100'],
            'contact.last_name' => [$creating ? 'nullable' : 'prohibited', 'string', 'max:100'],
            'contact.job_title' => [$creating ? 'nullable' : 'prohibited', 'string', 'max:150'],
            'contact.email' => [$creating ? 'nullable' : 'prohibited', 'email', 'max:190'],
            'contact.phone' => [$creating ? 'nullable' : 'prohibited', 'string', 'max:40'],
            'contact.audience_type' => ['nullable', Rule::in(array_keys(config('prospection.audience_types')))],
            'contact.legal_basis' => ['nullable', Rule::in(array_keys(config('prospection.legal_bases')))],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if (filled($this->input('registration_number')) && ! Normalizer::registrationNumber($this->input('registration_number'))) {
                $validator->errors()->add('registration_number', 'Le SIREN doit comporter 9 chiffres, le SIRET 14.');
            }
            if (filled($this->input('website')) && ! Normalizer::domain($this->input('website'))) {
                $validator->errors()->add('website', 'Adresse de site web invalide.');
            }
        }];
    }

    public function attributes(): array
    {
        return [
            'name' => 'raison sociale',
            'registration_number' => 'SIREN / SIRET',
            'sector_id' => 'secteur',
            'subsector_id' => 'sous-secteur',
            'postal_code' => 'code postal',
            'acquired_at' => 'date d\'acquisition',
            'owner_id' => 'responsable',
            'contact.email' => 'e-mail du contact',
        ];
    }
}
