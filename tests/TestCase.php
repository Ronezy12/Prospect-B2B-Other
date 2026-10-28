<?php

namespace Tests;

use App\Models\Company;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected bool $seedReferenceData = true;

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->seedReferenceData && in_array(RefreshDatabase::class, class_uses_recursive(static::class), true)) {
            $this->seed([RolesAndPermissionsSeeder::class, SectorSeeder::class]);
        }
    }

    protected function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes + ['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    protected function companyFor(?User $owner, array $attributes = []): Company
    {
        $company = new Company($attributes + ['name' => 'Entreprise '.uniqid(), 'status' => 'nouveau']);
        $company->owner_id = $owner?->id;
        $company->save();

        return $company;
    }
}
