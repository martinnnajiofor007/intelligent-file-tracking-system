<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\File;
use App\Models\FileCategory;
use App\Models\FileIssue;
use App\Models\Transfer;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditLogApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeFile(): File
    {
        return File::factory()->create();
    }

    private function makeAuditLog(array $overrides = []): AuditLog
    {
        $createdAt = $overrides['created_at'] ?? now();
        unset($overrides['created_at']);

        $log = AuditLog::create(array_merge([
            'actor_user_id' => User::factory()->create()->id,
            'action' => 'file_registered',
            'entity_type' => File::class,
            'entity_id' => 1,
            'before' => null,
            'after' => ['status' => 'active'],
        ], $overrides));

        $log->created_at = $createdAt;
        $log->save();

        return $log;
    }

    // ---------- Listing ----------

    public function test_authenticated_user_can_list_audit_logs(): void
    {
        $this->makeAuditLog();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/audit-logs')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_unauthenticated_user_cannot_access_audit_logs(): void
    {
        $this->getJson('/api/audit-logs')->assertUnauthorized();
    }

    public function test_audit_logs_are_newest_first(): void
    {
        $older = $this->makeAuditLog(['created_at' => now()->subDays(2)]);
        $newer = $this->makeAuditLog(['created_at' => now()->subDay()]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/audit-logs')
            ->assertOk()
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id);
    }

    public function test_audit_logs_paginate(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->makeAuditLog();
        }

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/audit-logs?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.last_page', 3);
    }

    public function test_audit_logs_per_page_is_capped_at_100(): void
    {
        for ($i = 0; $i < 120; $i++) {
            $this->makeAuditLog();
        }

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/audit-logs?per_page=1000')
            ->assertOk()
            ->assertJsonCount(100, 'data')
            ->assertJsonPath('meta.per_page', 100);
    }

    public function test_file_audit_logs_per_page_is_capped_at_100(): void
    {
        $file = $this->makeFile();
        for ($i = 0; $i < 120; $i++) {
            $this->makeAuditLog(['entity_type' => File::class, 'entity_id' => $file->id]);
        }

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/files/{$file->id}/audit-logs?per_page=1000")
            ->assertOk()
            ->assertJsonCount(100, 'data')
            ->assertJsonPath('meta.per_page', 100);
    }

    public function test_invalid_from_date_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/audit-logs?from=not-a-date')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('from');
    }

    public function test_invalid_to_date_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/audit-logs?to=not-a-date')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('to');
    }

    // ---------- Filtering ----------

    public function test_filtering_by_action_works(): void
    {
        $this->makeAuditLog(['action' => 'file_registered']);
        $this->makeAuditLog(['action' => 'transfer_created']);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/audit-logs?action=transfer_created')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.action', 'transfer_created');
    }

    public function test_filtering_by_actor_works(): void
    {
        $actor = User::factory()->create();
        $this->makeAuditLog(['actor_user_id' => $actor->id]);
        $this->makeAuditLog();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/audit-logs?actor_user_id={$actor->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.actor.id', $actor->id);
    }

    public function test_filtering_by_entity_works(): void
    {
        $file = $this->makeFile();
        $this->makeAuditLog(['entity_type' => File::class, 'entity_id' => $file->id]);
        $this->makeAuditLog(['entity_type' => Transfer::class, 'entity_id' => 99]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/audit-logs?entity_type=' . urlencode(File::class) . '&entity_id=' . $file->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.entity_id', $file->id);
    }

    public function test_filtering_by_date_range_works(): void
    {
        $this->makeAuditLog(['created_at' => now()->subDays(10)]);
        $this->makeAuditLog(['created_at' => now()->subDay()]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/audit-logs?from=' . now()->subDays(5)->toISOString())
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    // ---------- File-specific audit history ----------

    public function test_file_specific_audit_history_works(): void
    {
        $file = $this->makeFile();
        $this->makeAuditLog(['entity_type' => File::class, 'entity_id' => $file->id, 'action' => 'file_registered']);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/files/{$file->id}/audit-logs")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.action', 'file_registered');
    }

    public function test_transfer_events_appear_in_file_audit_history(): void
    {
        $file = $this->makeFile();
        $transfer = Transfer::factory()->create(['file_id' => $file->id]);
        $this->makeAuditLog(['entity_type' => Transfer::class, 'entity_id' => $transfer->id, 'action' => 'transfer_created']);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/files/{$file->id}/audit-logs")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.action', 'transfer_created');
    }

    public function test_issue_events_appear_in_file_audit_history(): void
    {
        $file = $this->makeFile();
        $issue = FileIssue::factory()->create(['file_id' => $file->id]);
        $this->makeAuditLog(['entity_type' => FileIssue::class, 'entity_id' => $issue->id, 'action' => 'issue_created']);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/files/{$file->id}/audit-logs")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.action', 'issue_created');
    }

    public function test_file_audit_history_excludes_unrelated_entities(): void
    {
        $file = $this->makeFile();
        $otherFile = $this->makeFile();
        $this->makeAuditLog(['entity_type' => File::class, 'entity_id' => $file->id]);
        $this->makeAuditLog(['entity_type' => File::class, 'entity_id' => $otherFile->id]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/files/{$file->id}/audit-logs")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.entity_id', $file->id);
    }

    // ---------- Read-only enforcement ----------

    public function test_audit_logs_cannot_be_created_through_api(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/audit-logs', [
            'action' => 'forged',
            'entity_type' => File::class,
            'entity_id' => 1,
        ])->assertStatus(405);

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_audit_logs_cannot_be_edited_through_api(): void
    {
        $log = $this->makeAuditLog();

        Sanctum::actingAs(User::factory()->create());

        $this->patchJson("/api/audit-logs/{$log->id}", ['action' => 'forged'])
            ->assertStatus(404);

        $this->assertDatabaseHas('audit_logs', [
            'id' => $log->id,
            'action' => 'file_registered',
        ]);
    }

    public function test_audit_logs_cannot_be_deleted_through_api(): void
    {
        $log = $this->makeAuditLog();

        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson("/api/audit-logs/{$log->id}")
            ->assertStatus(404);

        $this->assertDatabaseHas('audit_logs', ['id' => $log->id]);
    }

    // ---------- Sensitive data protection ----------

    public function test_sensitive_fields_are_not_written_to_audit_payloads(): void
    {
        $actor = User::factory()->create();
        $service = app(AuditLogService::class);

        $service->record(
            $actor,
            'test_event',
            File::class,
            1,
            ['password' => 'secret', 'remember_token' => 'tok', 'safe' => 'value'],
            ['api_key' => 'abc', 'token' => 'xyz', 'nested' => ['password' => 'nope', 'ok' => 1]]
        );

        $log = AuditLog::where('action', 'test_event')->first();

        $this->assertNotNull($log);
        $this->assertArrayNotHasKey('password', $log->before);
        $this->assertArrayNotHasKey('remember_token', $log->before);
        $this->assertEquals('value', $log->before['safe']);
        $this->assertArrayNotHasKey('api_key', $log->after);
        $this->assertArrayNotHasKey('token', $log->after);
        $this->assertArrayNotHasKey('password', $log->after['nested']);
        $this->assertEquals(1, $log->after['nested']['ok']);
    }

    public function test_sensitive_credential_variants_are_stripped_including_nested(): void
    {
        $actor = User::factory()->create();
        $service = app(AuditLogService::class);

        $service->record(
            $actor,
            'test_variants',
            File::class,
            1,
            [
                'password_confirmation' => 'x',
                'password_hash' => 'y',
                'auth_token' => 'z',
                'client_secret' => 'w',
                'safe_field' => 'keep',
                'nested' => [
                    'password_confirmation' => 'a',
                    'auth_token' => 'b',
                    'client_secret' => 'c',
                    'keep' => 'yes',
                ],
            ],
            null
        );

        $log = AuditLog::where('action', 'test_variants')->first();

        $this->assertNotNull($log);
        $this->assertArrayNotHasKey('password_confirmation', $log->before);
        $this->assertArrayNotHasKey('password_hash', $log->before);
        $this->assertArrayNotHasKey('auth_token', $log->before);
        $this->assertArrayNotHasKey('client_secret', $log->before);
        $this->assertEquals('keep', $log->before['safe_field']);
        $this->assertArrayNotHasKey('password_confirmation', $log->before['nested']);
        $this->assertArrayNotHasKey('auth_token', $log->before['nested']);
        $this->assertArrayNotHasKey('client_secret', $log->before['nested']);
        $this->assertEquals('yes', $log->before['nested']['keep']);
    }

    // ---------- Existing events continue to be recorded ----------

    public function test_existing_audit_events_continue_to_be_recorded(): void
    {
        $department = Department::factory()->create();
        $category = FileCategory::factory()->create();
        $file = $this->makeFile();
        $issue = FileIssue::factory()->create(['file_id' => $file->id]);

        $actor = User::factory()->admin()->create();
        Sanctum::actingAs($actor);

        $this->postJson('/api/departments', ['name' => 'New Dept'])->assertCreated();
        $this->postJson('/api/file-categories', ['name' => 'New Cat'])->assertCreated();
        $this->postJson('/api/files', [
            'file_number' => 'REG/2026/999',
            'title' => 'Audit Trail File',
        ])->assertCreated();
        $this->patchJson("/api/issues/{$issue->id}", ['status' => FileIssue::STATUS_RESOLVED])->assertOk();

        $this->assertDatabaseHas('audit_logs', ['action' => 'department_created']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'file_category_created']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'file_registered']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'issue_status_changed']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'issue_resolved']);
    }
}
