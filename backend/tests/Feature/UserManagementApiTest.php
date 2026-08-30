<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserManagementApiTest extends TestCase
{
    use RefreshDatabase;

    // ---------- User creation ----------

    public function test_unauthenticated_user_cannot_create_user(): void
    {
        $this->postJson('/api/users', [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'department_staff',
        ])->assertUnauthorized();
    }

    public function test_unauthorized_role_cannot_create_user(): void
    {
        $user = User::factory()->departmentStaff()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/users', [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'department_staff',
        ])->assertForbidden();
    }

    public function test_authorized_admin_can_create_user(): void
    {
        $admin = User::factory()->admin()->create();

        Sanctum::actingAs($admin);

        $this->postJson('/api/users', [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'registry_staff',
        ])->assertCreated()
            ->assertJsonPath('data.name', 'New User')
            ->assertJsonPath('data.email', 'new@example.com')
            ->assertJsonPath('data.role', 'registry_staff')
            ->assertJsonMissing(['password']);
    }

    public function test_created_user_password_is_hashed(): void
    {
        $admin = User::factory()->admin()->create();

        Sanctum::actingAs($admin);

        $this->postJson('/api/users', [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'department_staff',
        ])->assertCreated();

        $user = User::where('email', 'new@example.com')->first();
        $this->assertNotEquals('password123', $user->password);
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    public function test_duplicate_email_is_rejected(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);
        $admin = User::factory()->admin()->create();

        Sanctum::actingAs($admin);

        $this->postJson('/api/users', [
            'name' => 'New User',
            'email' => 'existing@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'department_staff',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_invalid_role_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        Sanctum::actingAs($admin);

        $this->postJson('/api/users', [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'superuser',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('role');
    }

    public function test_invalid_password_confirmation_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        Sanctum::actingAs($admin);

        $this->postJson('/api/users', [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different',
            'role' => 'department_staff',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_user_creation_is_audited(): void
    {
        $admin = User::factory()->admin()->create();

        Sanctum::actingAs($admin);

        $this->postJson('/api/users', [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'department_staff',
        ])->assertCreated();

        $user = User::where('email', 'new@example.com')->first();

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'action' => 'user_created',
            'entity_type' => 'App\Models\User',
            'entity_id' => $user->id,
        ]);
    }

    // ---------- User update ----------

    public function test_unauthenticated_user_cannot_update_user(): void
    {
        $target = User::factory()->create();

        $this->patchJson("/api/users/{$target->id}", [
            'name' => 'Changed',
            'email' => 'changed@example.com',
            'role' => 'department_staff',
        ])->assertUnauthorized();
    }

    public function test_non_admin_cannot_update_user(): void
    {
        $target = User::factory()->create();
        $staff = User::factory()->departmentStaff()->create();

        Sanctum::actingAs($staff);

        $this->patchJson("/api/users/{$target->id}", [
            'name' => 'Changed',
            'email' => 'changed@example.com',
            'role' => 'department_staff',
        ])->assertForbidden();
    }

    public function test_admin_can_update_user(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create(['name' => 'Original', 'email' => 'orig@example.com']);
        $department = Department::factory()->create(['name' => 'Finance']);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/users/{$target->id}", [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'role' => 'supervisor',
            'department_id' => $department->id,
        ])->assertOk()
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.email', 'updated@example.com')
            ->assertJsonPath('data.role', 'supervisor')
            ->assertJsonPath('data.department.id', $department->id);

        $this->assertDatabaseHas('users', [
            'id' => $target->id,
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'role' => 'supervisor',
            'department_id' => $department->id,
        ]);
    }

    public function test_admin_can_toggle_user_active_status(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create(['is_active' => true]);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/users/{$target->id}", [
            'name' => $target->name,
            'email' => $target->email,
            'role' => $target->role,
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('users', ['id' => $target->id, 'is_active' => false]);
    }

    public function test_duplicate_email_is_rejected_on_user_update(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['email' => 'other@example.com']);
        $target = User::factory()->create(['email' => 'me@example.com']);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/users/{$target->id}", [
            'name' => 'Me',
            'email' => 'other@example.com',
            'role' => 'department_staff',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_user_can_keep_own_email_on_update(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create(['name' => 'Me', 'email' => 'me@example.com']);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/users/{$target->id}", [
            'name' => 'Me Updated',
            'email' => 'me@example.com',
            'role' => 'department_staff',
        ])->assertOk()
            ->assertJsonPath('data.email', 'me@example.com');
    }

    public function test_invalid_role_is_rejected_on_user_update(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();

        Sanctum::actingAs($admin);

        $this->patchJson("/api/users/{$target->id}", [
            'name' => 'Me',
            'email' => $target->email,
            'role' => 'superuser',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('role');
    }

    public function test_invalid_department_is_rejected_on_user_update(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();

        Sanctum::actingAs($admin);

        $this->patchJson("/api/users/{$target->id}", [
            'name' => 'Me',
            'email' => $target->email,
            'role' => 'department_staff',
            'department_id' => 999,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('department_id');
    }

    public function test_user_update_is_audited(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create(['name' => 'Original', 'email' => 'orig@example.com']);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/users/{$target->id}", [
            'name' => 'Updated',
            'email' => 'updated@example.com',
            'role' => 'supervisor',
        ])->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'action' => 'user_updated',
            'entity_type' => 'App\Models\User',
            'entity_id' => $target->id,
        ]);
    }

    public function test_user_update_audit_does_not_expose_sensitive_data(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create(['name' => 'Original', 'email' => 'orig@example.com']);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/users/{$target->id}", [
            'name' => 'Updated',
            'email' => 'updated@example.com',
            'role' => 'supervisor',
        ])->assertOk();

        $audit = \App\Models\AuditLog::where('action', 'user_updated')
            ->where('entity_id', $target->id)
            ->first();

        $this->assertNotNull($audit);
        $this->assertStringNotContainsString('password', json_encode($audit->before));
        $this->assertStringNotContainsString('password', json_encode($audit->after));
        $this->assertStringNotContainsString('token', json_encode($audit->after));
    }

    // ---------- Admin password reset ----------

    public function test_unauthenticated_user_cannot_reset_password(): void
    {
        $target = User::factory()->create();

        $this->patchJson("/api/users/{$target->id}/password", [
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ])->assertUnauthorized();
    }

    public function test_non_admin_cannot_reset_password(): void
    {
        $target = User::factory()->create();
        $staff = User::factory()->departmentStaff()->create();

        Sanctum::actingAs($staff);

        $this->patchJson("/api/users/{$target->id}/password", [
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ])->assertForbidden();
    }

    public function test_admin_can_reset_password(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create(['password' => Hash::make('old-password')]);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/users/{$target->id}/password", [
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ])->assertOk()
            ->assertJsonPath('message', 'Password reset successfully.');

        $this->assertTrue(Hash::check('new-password123', $target->fresh()->password));
        $this->assertFalse(Hash::check('old-password', $target->fresh()->password));
    }

    public function test_reset_password_is_hashed(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create(['password' => Hash::make('old-password')]);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/users/{$target->id}/password", [
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ])->assertOk();

        $this->assertNotEquals('new-password123', $target->fresh()->password);
    }

    public function test_reset_password_revokes_target_tokens_but_keeps_admin_session(): void
    {
        $admin = User::factory()->admin()->create();
        $adminToken = $admin->createToken('admin')->plainTextToken;
        $target = User::factory()->create(['password' => Hash::make('old-password')]);
        $targetToken = $target->createToken('target')->plainTextToken;

        $this->withToken($adminToken)->patchJson("/api/users/{$target->id}/password", [
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ])->assertOk();

        // Target's token revoked
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $target->id,
            'token' => hash('sha256', explode('|', $targetToken)[1]),
        ]);
        // Admin's token kept
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $admin->id,
            'token' => hash('sha256', explode('|', $adminToken)[1]),
        ]);
    }

    public function test_reset_password_does_not_expose_password_in_response(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create(['password' => Hash::make('old-password')]);

        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/users/{$target->id}/password", [
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ])->assertOk();

        $this->assertStringNotContainsString('new-password123', $response->getContent());
    }

    public function test_reset_password_does_not_expose_password_in_audit(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create(['password' => Hash::make('old-password')]);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/users/{$target->id}/password", [
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ])->assertOk();

        $audit = \App\Models\AuditLog::where('action', 'user_password_reset')
            ->where('entity_id', $target->id)
            ->first();

        $this->assertNotNull($audit);
        $this->assertStringNotContainsString('new-password123', json_encode($audit->after));
        $this->assertStringNotContainsString('password', json_encode($audit->after));
    }

    public function test_reset_password_requires_confirmation(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();

        Sanctum::actingAs($admin);

        $this->patchJson("/api/users/{$target->id}/password", [
            'password' => 'new-password123',
            'password_confirmation' => 'different',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    // ---------- Profile ----------

    public function test_unauthenticated_user_cannot_update_profile(): void
    {
        $this->patchJson('/api/auth/profile', [
            'name' => 'Changed',
            'email' => 'changed@example.com',
        ])->assertUnauthorized();
    }

    public function test_authenticated_user_can_update_own_profile(): void
    {
        $user = User::factory()->create(['name' => 'Original', 'email' => 'orig@example.com']);

        Sanctum::actingAs($user);

        $this->patchJson('/api/auth/profile', [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.email', 'updated@example.com');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ]);
    }

    public function test_duplicate_email_is_rejected_on_profile_update(): void
    {
        User::factory()->create(['email' => 'other@example.com']);
        $user = User::factory()->create(['email' => 'me@example.com']);

        Sanctum::actingAs($user);

        $this->patchJson('/api/auth/profile', [
            'name' => 'Me',
            'email' => 'other@example.com',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_user_can_keep_own_email_on_profile_update(): void
    {
        $user = User::factory()->create(['name' => 'Me', 'email' => 'me@example.com']);

        Sanctum::actingAs($user);

        $this->patchJson('/api/auth/profile', [
            'name' => 'Me Updated',
            'email' => 'me@example.com',
        ])->assertOk()
            ->assertJsonPath('data.email', 'me@example.com');
    }

    public function test_user_cannot_change_role_through_profile_update(): void
    {
        $user = User::factory()->departmentStaff()->create(['name' => 'Me', 'email' => 'me@example.com']);

        Sanctum::actingAs($user);

        $this->patchJson('/api/auth/profile', [
            'name' => 'Me',
            'email' => 'me@example.com',
            'role' => 'admin',
        ])->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'role' => 'department_staff',
        ]);
    }

    // ---------- Change password ----------

    public function test_current_password_is_required(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);

        Sanctum::actingAs($user);

        $this->postJson('/api/auth/change-password', [
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');
    }

    public function test_incorrect_current_password_is_rejected(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);

        Sanctum::actingAs($user);

        $this->postJson('/api/auth/change-password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');
    }

    public function test_valid_password_change_succeeds(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);

        Sanctum::actingAs($user);

        $this->postJson('/api/auth/change-password', [
            'current_password' => 'old-password',
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ])->assertOk()
            ->assertJsonPath('message', 'Password changed successfully.');

        $this->assertTrue(Hash::check('new-password123', $user->fresh()->password));
        $this->assertFalse(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_new_password_is_hashed(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);

        Sanctum::actingAs($user);

        $this->postJson('/api/auth/change-password', [
            'current_password' => 'old-password',
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ])->assertOk();

        $this->assertNotEquals('new-password123', $user->fresh()->password);
    }

    public function test_password_is_not_exposed_in_response(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/auth/change-password', [
            'current_password' => 'old-password',
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ])->assertOk();

        $this->assertStringNotContainsString('new-password123', $response->getContent());
        $this->assertStringNotContainsString('old-password', $response->getContent());
    }

    public function test_password_change_revokes_other_tokens_but_keeps_current(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);
        $currentToken = $user->createToken('current')->plainTextToken;
        $otherToken = $user->createToken('other')->plainTextToken;

        $this->withToken($currentToken)->postJson('/api/auth/change-password', [
            'current_password' => 'old-password',
            'password' => 'new-password123',
            'password_confirmation' => 'new-password123',
        ])->assertOk();

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'token' => hash('sha256', explode('|', $currentToken)[1]),
        ]);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'token' => hash('sha256', explode('|', $otherToken)[1]),
        ]);
    }
}
