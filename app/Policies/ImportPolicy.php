<?php

namespace App\Policies;

use App\Models\Import;
use App\Models\User;

class ImportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('prospects.import');
    }

    /** Chacun voit ses imports ; la vue globale demande le périmètre « tout ». */
    public function view(User $user, Import $import): bool
    {
        return $user->can('prospects.import')
            && ($import->user_id === $user->id || $user->can('prospects.view_all'));
    }

    public function create(User $user): bool
    {
        return $user->can('prospects.import');
    }

    public function update(User $user, Import $import): bool
    {
        return $user->can('prospects.import') && $import->user_id === $user->id;
    }
}
