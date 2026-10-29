<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('prospects.view');
    }

    public function view(User $user, Task $task): bool
    {
        return $user->can('prospects.view') && $task->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $user->can('prospects.update');
    }

    public function update(User $user, Task $task): bool
    {
        return $user->can('prospects.update') && $task->isVisibleTo($user);
    }

    public function delete(User $user, Task $task): bool
    {
        return $user->can('prospects.update') && $task->isVisibleTo($user)
            && ($task->created_by === $user->id || $user->can('prospects.delete'));
    }
}
