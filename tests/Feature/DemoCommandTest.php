<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_data_is_created_and_marked_as_example(): void
    {
        $this->artisan('pm:demo')->assertSuccessful();
        $this->artisan('pm:demo')->assertSuccessful(); // idempotente

        $this->assertSame(24, Company::count());
        $this->assertSame(24, Company::whereHas('tags', fn ($q) => $q->where('name', 'Exemple démo'))->count());
        $this->assertTrue(User::where('email', 'admin@demo.test')->firstOrFail()->hasRole('administrateur'));
        $this->assertSame(0, Company::where('website', 'not like', '%.test')->count());
    }

    public function test_demo_data_is_refused_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('pm:demo')->assertFailed();
        $this->assertSame(0, Company::count());
    }
}
