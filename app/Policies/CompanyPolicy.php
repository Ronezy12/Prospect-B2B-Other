<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

/** Droits vérifiés côté serveur : permission d'action ET périmètre de données. */
class CompanyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('prospects.view');
    }

    public function view(User $user, Company $company): bool
    {
        return $user->can('prospects.view') && $company->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $user->can('prospects.create');
    }

    public function update(User $user, Company $company): bool
    {
        return $user->can('prospects.update') && $company->isVisibleTo($user);
    }

    public function delete(User $user, Company $company): bool
    {
        return $user->can('prospects.delete') && $company->isVisibleTo($user);
    }

    public function convert(User $user, Company $company): bool
    {
        return $user->can('prospects.convert') && ! $company->isClient() && $company->isVisibleTo($user);
    }

    public function export(User $user): bool
    {
        return $user->can('prospects.export');
    }
}
