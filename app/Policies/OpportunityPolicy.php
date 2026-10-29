<?php

namespace App\Policies;

use App\Models\Opportunity;
use App\Models\User;

class OpportunityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('prospects.view');
    }

    public function view(User $user, Opportunity $opportunity): bool
    {
        return $user->can('prospects.view') && $opportunity->isVisibleTo($user) && $this->companyExists($opportunity);
    }

    public function create(User $user): bool
    {
        return $user->can('prospects.create');
    }

    public function update(User $user, Opportunity $opportunity): bool
    {
        return $user->can('prospects.update') && $opportunity->isVisibleTo($user) && $this->companyExists($opportunity);
    }

    public function delete(User $user, Opportunity $opportunity): bool
    {
        return $user->can('prospects.delete') && $opportunity->isVisibleTo($user);
    }

    /** Une opportunité dont l'entreprise est archivée n'est plus consultable ni modifiable. */
    private function companyExists(Opportunity $opportunity): bool
    {
        abort_unless($opportunity->company()->exists(), 404);

        return true;
    }
}
