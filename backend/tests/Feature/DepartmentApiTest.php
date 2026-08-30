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

    // ---------- Listing ----------

    public function test_authenticated_user_can_list_departments(): void
    {
        Department::factory()->create(['name' => 'Registry']);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/departments')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Registry');
    }

    public function test_unauthenticated_user_cannot_list_departments(): void
    {
        $this->getJson('/api/departments')->assertUnauthorized();
    }

    // ---------- Authorization ----------

    public function test_admin_can_create_department(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/departments', ['name' => 'Finance'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Finance');

        $this->assertDatabaseHas('departments', ['name' => 'Finance']);
    }

    public function test_unauthenticated_user_cannot_create_department(): void
    {
        $this->postJson('/api/departments', ['name' => 'Finance'])
            ->assertUnauthorized();
    }

    public function test_registry_staff_cannot_create_department(): void
    {
        Sanctum::actingAs(User::factory()->registryStaff()->create());

        $this->postJson('/api/departments', ['name' => 'Finance'])
            ->assertForbidden();
    }

    public function test_department_staff_cannot_create_department(): void
    {
        Sanctum::actingAs(User::factory()->departmentStaff()->create());

        $this->postJson('/api/departments', ['name' => 'Finance'])
            ->assertForbidden();
    }

    public function test_supervisor_cannot_create_department(): void
    {
        Sanctum::actingAs(User::factory()->supervisor()->create());

        $this->postJson('/api/departments', ['name' => 'Finance'])
            ->assertForbidden();
    }

    // ---------- Validation ----------

    public function test_missing_name_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/departments', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_duplicate_department_name_is_rejected(): void
    {
        Department::factory()->create(['name' => 'Finance']);
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/departments', ['name' => 'Finance'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_name_over_max_length_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/departments', ['name' => str_repeat('a', 256)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_invalid_parent_id_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/departments', ['name' => 'Finance', 'parent_id' => 999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('parent_id');
    }

    // ---------- Successful creation ----------

    public function test_department_is_created_correctly(): void
    {
        $parent = Department::factory()->create(['name' => 'Registry']);
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/departments', ['name' => 'Finance', 'parent_id' => $parent->id])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Finance')
            ->assertJsonPath('data.parent.id', $parent->id);

        $this->assertDatabaseHas('departments', [
            'name' => 'Finance',
            'parent_id' => $parent->id,
        ]);
    }

    public function test_department_creation_is_audited(): void
    {
        $admin = User::factory()->admin()->create();

        Sanctum::actingAs($admin);

        $this->postJson('/api/departments', ['name' => 'Finance'])
            ->assertCreated();

        $department = Department::where('name', 'Finance')->first();

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'action' => 'department_created',
            'entity_type' => 'App\Models\Department',
            'entity_id' => $department->id,
        ]);
    }

    public function test_department_creation_audit_does_not_expose_sensitive_data(): void
    {
        $admin = User::factory()->admin()->create();

        Sanctum::actingAs($admin);

        $this->postJson('/api/departments', ['name' => 'Finance'])
            ->assertCreated();

        $department = Department::where('name', 'Finance')->first();
        $audit = \App\Models\AuditLog::where('action', 'department_created')
            ->where('entity_id', $department->id)
            ->first();

        $this->assertNotNull($audit);
        $this->assertStringNotContainsString('password', json_encode($audit->after));
        $this->assertStringNotContainsString('token', json_encode($audit->after));
    }

    // ---------- Department update ----------

    public function test_unauthenticated_user_cannot_update_department(): void
    {
        $department = Department::factory()->create(['name' => 'Finance']);

        $this->patchJson("/api/departments/{$department->id}", ['name' => 'Changed'])
            ->assertUnauthorized();
    }

    public function test_non_admin_cannot_update_department(): void
    {
        $department = Department::factory()->create(['name' => 'Finance']);
        $staff = User::factory()->departmentStaff()->create();

        Sanctum::actingAs($staff);

        $this->patchJson("/api/departments/{$department->id}", ['name' => 'Changed'])
            ->assertForbidden();
    }

    public function test_admin_can_update_department(): void
    {
        $admin = User::factory()->admin()->create();
        $parent = Department::factory()->create(['name' => 'Registry']);
        $department = Department::factory()->create(['name' => 'Finance']);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/departments/{$department->id}", [
            'name' => 'Finance & Accounting',
            'parent_id' => $parent->id,
        ])->assertOk()
            ->assertJsonPath('data.name', 'Finance & Accounting')
            ->assertJsonPath('data.parent.id', $parent->id);

        $this->assertDatabaseHas('departments', [
            'id' => $department->id,
            'name' => 'Finance & Accounting',
            'parent_id' => $parent->id,
        ]);
    }

    public function test_duplicate_department_name_is_rejected_on_update(): void
    {
        $admin = User::factory()->admin()->create();
        Department::factory()->create(['name' => 'Legal']);
        $department = Department::factory()->create(['name' => 'Finance']);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/departments/{$department->id}", ['name' => 'Legal'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_department_can_keep_own_name_on_update(): void
    {
        $admin = User::factory()->admin()->create();
        $department = Department::factory()->create(['name' => 'Finance']);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/departments/{$department->id}", ['name' => 'Finance'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Finance');
    }

    public function test_invalid_parent_is_rejected_on_department_update(): void
    {
        $admin = User::factory()->admin()->create();
        $department = Department::factory()->create(['name' => 'Finance']);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/departments/{$department->id}", [
            'name' => 'Finance',
            'parent_id' => 999,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('parent_id');
    }

    public function test_department_cannot_be_its_own_parent(): void
    {
        $admin = User::factory()->admin()->create();
        $department = Department::factory()->create(['name' => 'Finance']);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/departments/{$department->id}", [
            'name' => 'Finance',
            'parent_id' => $department->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('parent_id');
    }

    public function test_department_cannot_select_descendant_as_parent(): void
    {
        $admin = User::factory()->admin()->create();
        $parent = Department::factory()->create(['name' => 'Registry']);
        $child = Department::factory()->create(['name' => 'Finance', 'parent_id' => $parent->id]);

        Sanctum::actingAs($admin);

        // Try to make the parent a child of its own child -> circular
        $this->patchJson("/api/departments/{$parent->id}", [
            'name' => 'Registry',
            'parent_id' => $child->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('parent_id');
    }

    public function test_department_update_is_audited(): void
    {
        $admin = User::factory()->admin()->create();
        $department = Department::factory()->create(['name' => 'Finance']);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/departments/{$department->id}", ['name' => 'Finance & Accounting'])
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'action' => 'department_updated',
            'entity_type' => 'App\Models\Department',
            'entity_id' => $department->id,
        ]);
    }

    public function test_department_update_audit_records_before_after_safely(): void
    {
        $admin = User::factory()->admin()->create();
        $department = Department::factory()->create(['name' => 'Finance']);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/departments/{$department->id}", ['name' => 'Finance & Accounting'])
            ->assertOk();

        $audit = \App\Models\AuditLog::where('action', 'department_updated')
            ->where('entity_id', $department->id)
            ->first();

        $this->assertNotNull($audit);
        $this->assertEquals('Finance', $audit->before['name']);
        $this->assertEquals('Finance & Accounting', $audit->after['name']);
        $this->assertStringNotContainsString('password', json_encode($audit->after));
        $this->assertStringNotContainsString('token', json_encode($audit->after));
    }
}
