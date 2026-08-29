<?php

namespace Tests\Feature;

use App\Models\FileCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FileCategoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_categories(): void
    {
        FileCategory::factory()->create(['name' => 'Finance']);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/file-categories')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Finance');
    }

    public function test_admin_can_create_category(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/file-categories', [
            'name' => 'Procurement',
            'default_due_days' => 14,
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Procurement')
            ->assertJsonPath('data.default_due_days', 14);

        $this->assertDatabaseHas('file_categories', ['name' => 'Procurement']);
    }

    public function test_non_admin_cannot_create_category(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'department_staff']));

        $this->postJson('/api/file-categories', ['name' => 'Legal'])
            ->assertForbidden();
    }
}
