<?php

namespace App\Policies;

use App\Models\Contact;
use App\Models\User;

class ContactPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('prospects.view');
    }

    public function view(User $user, Contact $contact): bool
    {
        return $user->can('prospects.view') && $contact->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $user->can('prospects.create');
    }

    public function update(User $user, Contact $contact): bool
    {
        return $user->can('prospects.update') && $contact->isVisibleTo($user);
    }

    public function delete(User $user, Contact $contact): bool
    {
        return $user->can('prospects.delete') && $contact->isVisibleTo($user);
    }
}
