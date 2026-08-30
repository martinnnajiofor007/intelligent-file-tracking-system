<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\File;
use App\Models\FileCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_register_file(): void
    {
        $department = Department::factory()->create(['name' => 'Registry']);
        $holder = User::factory()->create(['department_id' => $department->id]);
        $category = FileCategory::factory()->create(['name' => 'General Registry']);
        $registryUser = User::factory()->registryStaff()->create();

        Sanctum::actingAs($registryUser);

        $response = $this->postJson('/api/files', [
            'file_number' => 'REG/2026/001',
            'title' => 'General Administrative Correspondence',
            'description' => 'Initial registry file.',
            'category_id' => $category->id,
            'confirmed_department_id' => $department->id,
            'confirmed_holder_user_id' => $holder->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.file_number', 'REG/2026/001')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.confirmed_department.name', 'Registry')
            ->assertJsonPath('data.confirmed_holder.id', $holder->id)
            ->assertJsonPath('data.registered_by.id', $registryUser->id);
    }

    public function test_unauthorized_user_cannot_register_file(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'department_staff']));

        $this->postJson('/api/files', [
            'file_number' => 'REG/2026/001',
            'title' => 'General Administrative Correspondence',
        ])->assertForbidden();
    }

    public function test_duplicate_file_number_is_rejected(): void
    {
        File::factory()->create(['file_number' => 'REG/2026/001']);

        Sanctum::actingAs(User::factory()->registryStaff()->create());

        $this->postJson('/api/files', [
            'file_number' => 'REG/2026/001',
            'title' => 'Duplicate File',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('file_number');
    }

    public function test_server_controls_registration_fields(): void
    {
        $registryUser = User::factory()->registryStaff()->create();
        $otherUser = User::factory()->create();

        Sanctum::actingAs($registryUser);

        $this->postJson('/api/files', [
            'file_number' => 'REG/2026/002',
            'title' => 'Server Controlled Fields',
            'status' => 'archived',
            'registered_by_user_id' => $otherUser->id,
            'registered_at' => now()->subYear()->toISOString(),
        ])->assertCreated()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.registered_by.id', $registryUser->id);

        $this->assertDatabaseHas('files', [
            'file_number' => 'REG/2026/002',
            'status' => 'active',
            'registered_by_user_id' => $registryUser->id,
        ]);
    }

    public function test_authenticated_user_can_list_files(): void
    {
        File::factory()->create([
            'file_number' => 'FIN/2026/002',
            'title' => 'Budget Release Request',
        ]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/files?search=Budget')
            ->assertOk()
            ->assertJsonPath('data.0.file_number', 'FIN/2026/002');
    }

    public function test_authenticated_user_can_view_file_details(): void
    {
        $file = File::factory()->create(['file_number' => 'HR/2026/003']);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/files/{$file->id}")
            ->assertOk()
            ->assertJsonPath('data.file_number', 'HR/2026/003')
            ->assertJsonStructure([
                'data' => [
                    'file_number',
                    'title',
                    'description',
                    'category',
                    'status',
                    'confirmed_department',
                    'confirmed_holder',
                    'registered_by',
                    'registered_at',
                    'created_at',
                    'updated_at',
                ],
            ]);
    }

    public function test_file_list_caps_per_page_at_100(): void
    {
        File::factory()->count(3)->create();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/files?per_page=999999')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 100);
    }

    // ---------- Status filter validation ----------

    public function test_valid_status_filter_is_accepted(): void
    {
        File::factory()->create(['file_number' => 'FIN/2026/002', 'status' => File::STATUS_ACTIVE]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/files?status=active')
            ->assertOk()
            ->assertJsonPath('data.0.status', File::STATUS_ACTIVE);
    }

    public function test_invalid_status_filter_is_rejected(): void
    {
        File::factory()->create();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/files?status=HACKED')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_invalid_status_filter_does_not_return_empty_200(): void
    {
        File::factory()->create();

        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/files?status=HACKED');

        $response->assertStatus(422);
        $this->assertNotEquals(200, $response->getStatusCode());
    }

    public function test_omitted_status_filter_returns_all_files(): void
    {
        File::factory()->count(3)->create();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/files')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }
}
