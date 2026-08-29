<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DepartmentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_departments(): void
    {
        Department::factory()->create(['name' => 'Registry']);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/departments')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Registry');
    }

    public function test_admin_can_create_department(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/departments', ['name' => 'Finance'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Finance');

        $this->assertDatabaseHas('departments', ['name' => 'Finance']);
    }

    public function test_non_admin_cannot_create_department(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'department_staff']));

        $this->postJson('/api/departments', ['name' => 'Finance'])
            ->assertForbidden();
    }
}
