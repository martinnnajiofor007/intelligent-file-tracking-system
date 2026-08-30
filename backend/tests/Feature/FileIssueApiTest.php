<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\File;
use App\Models\FileIssue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FileIssueApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeFile(): File
    {
        return File::factory()->create();
    }

    private function createIssue(File $file, array $overrides = [], ?User $actor = null): FileIssue
    {
        $actor = $actor ?? User::factory()->create();

        Sanctum::actingAs($actor);

        $this->postJson("/api/files/{$file->id}/issues", array_merge([
            'issue_type' => 'damage',
            'description' => 'File cover is torn.',
        ], $overrides))->assertCreated();

        return FileIssue::first();
    }

    // ---------- Creation ----------

    public function test_authenticated_user_can_create_issue(): void
    {
        $file = $this->makeFile();

        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/files/{$file->id}/issues", [
            'issue_type' => 'damage',
            'description' => 'File cover is torn.',
        ])->assertCreated()
            ->assertJsonPath('data.issue_type', 'damage')
            ->assertJsonPath('data.status', FileIssue::STATUS_OPEN)
            ->assertJsonPath('data.file.id', $file->id);
    }

    public function test_unauthenticated_user_cannot_create_issue(): void
    {
        $file = $this->makeFile();

        $this->postJson("/api/files/{$file->id}/issues", [
            'issue_type' => 'damage',
            'description' => 'File cover is torn.',
        ])->assertUnauthorized();
    }

    public function test_reporter_is_taken_from_authenticated_user(): void
    {
        $file = $this->makeFile();
        $reporter = User::factory()->create();

        $this->createIssue($file, [], $reporter);

        $this->assertDatabaseHas('file_issues', [
            'file_id' => $file->id,
            'reported_by_user_id' => $reporter->id,
        ]);
    }

    public function test_client_cannot_spoof_reporter(): void
    {
        $file = $this->makeFile();
        $reporter = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->createIssue($file, [
            'reported_by_user_id' => $otherUser->id,
            'resolved_by_user_id' => $otherUser->id,
            'resolved_at' => now()->toISOString(),
            'status' => FileIssue::STATUS_RESOLVED,
        ], $reporter);

        $this->assertDatabaseHas('file_issues', [
            'file_id' => $file->id,
            'reported_by_user_id' => $reporter->id,
            'status' => FileIssue::STATUS_OPEN,
            'resolved_by_user_id' => null,
            'resolved_at' => null,
        ]);
    }

    public function test_issue_belongs_to_correct_file(): void
    {
        $fileA = $this->makeFile();
        $fileB = $this->makeFile();

        $this->createIssue($fileA);

        $this->assertDatabaseHas('file_issues', [
            'file_id' => $fileA->id,
        ]);
        $this->assertDatabaseMissing('file_issues', [
            'file_id' => $fileB->id,
        ]);
    }

    public function test_issue_requires_valid_fields(): void
    {
        $file = $this->makeFile();

        Sanctum::actingAs(User::factory()->create());

        $this->postJson("/api/files/{$file->id}/issues", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['issue_type', 'description']);
    }

    public function test_issue_creation_logs_audit_entry(): void
    {
        $file = $this->makeFile();
        $reporter = User::factory()->create();

        $this->createIssue($file, [], $reporter);

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $reporter->id,
            'action' => 'issue_created',
            'entity_type' => FileIssue::class,
            'entity_id' => FileIssue::first()->id,
        ]);
    }

    // ---------- Viewing / listing ----------

    public function test_issue_can_be_retrieved(): void
    {
        $file = $this->makeFile();
        $reporter = User::factory()->create();
        $this->createIssue($file, [], $reporter);

        $issue = FileIssue::first();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/issues/{$issue->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $issue->id)
            ->assertJsonPath('data.file.id', $file->id)
            ->assertJsonPath('data.reported_by.id', $reporter->id);
    }

    public function test_file_issue_list_works(): void
    {
        $file = $this->makeFile();
        FileIssue::factory()->count(3)->create(['file_id' => $file->id]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/files/{$file->id}/issues")
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.total', 3);
    }

    public function test_file_issue_list_orders_newest_first(): void
    {
        $file = $this->makeFile();
        $older = FileIssue::factory()->create(['file_id' => $file->id, 'created_at' => now()->subDays(2)]);
        $newer = FileIssue::factory()->create(['file_id' => $file->id, 'created_at' => now()->subDay()]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/files/{$file->id}/issues")
            ->assertOk()
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id);
    }

    public function test_file_issue_list_paginates(): void
    {
        $file = $this->makeFile();
        FileIssue::factory()->count(5)->create(['file_id' => $file->id]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/files/{$file->id}/issues?per_page=2")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.last_page', 3);
    }

    public function test_file_issue_list_caps_per_page_at_100(): void
    {
        $file = $this->makeFile();
        FileIssue::factory()->count(3)->create(['file_id' => $file->id]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/files/{$file->id}/issues?per_page=999999")
            ->assertOk()
            ->assertJsonPath('meta.per_page', 100);
    }

    // ---------- Status changes ----------

    public function test_authorized_user_can_change_status(): void
    {
        $file = $this->makeFile();
        $this->createIssue($file);

        $issue = FileIssue::first();

        Sanctum::actingAs(User::factory()->supervisor()->create());

        $this->patchJson("/api/issues/{$issue->id}", ['status' => FileIssue::STATUS_IN_PROGRESS])
            ->assertOk()
            ->assertJsonPath('data.status', FileIssue::STATUS_IN_PROGRESS);
    }

    public function test_unauthorized_user_cannot_change_status(): void
    {
        $file = $this->makeFile();
        $this->createIssue($file);

        $issue = FileIssue::first();

        Sanctum::actingAs(User::factory()->create());

        $this->patchJson("/api/issues/{$issue->id}", ['status' => FileIssue::STATUS_IN_PROGRESS])
            ->assertForbidden();

        $this->assertDatabaseHas('file_issues', [
            'id' => $issue->id,
            'status' => FileIssue::STATUS_OPEN,
        ]);
    }

    public function test_issue_resolution_records_resolver(): void
    {
        $file = $this->makeFile();
        $this->createIssue($file);

        $issue = FileIssue::first();
        $resolver = User::factory()->admin()->create();

        Sanctum::actingAs($resolver);

        $this->patchJson("/api/issues/{$issue->id}", ['status' => FileIssue::STATUS_RESOLVED])
            ->assertOk()
            ->assertJsonPath('data.status', FileIssue::STATUS_RESOLVED)
            ->assertJsonPath('data.resolved_by.id', $resolver->id);
    }

    public function test_issue_resolution_records_resolved_at(): void
    {
        $file = $this->makeFile();
        $this->createIssue($file);

        $issue = FileIssue::first();

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->patchJson("/api/issues/{$issue->id}", ['status' => FileIssue::STATUS_RESOLVED])
            ->assertOk();

        $this->assertNotNull($issue->fresh()->resolved_at);
    }

    public function test_reopening_clears_resolution_fields(): void
    {
        $file = $this->makeFile();
        $this->createIssue($file);

        $issue = FileIssue::first();
        $resolver = User::factory()->admin()->create();

        Sanctum::actingAs($resolver);

        $this->patchJson("/api/issues/{$issue->id}", ['status' => FileIssue::STATUS_RESOLVED])->assertOk();
        $this->patchJson("/api/issues/{$issue->id}", ['status' => FileIssue::STATUS_OPEN])->assertOk();

        $this->assertDatabaseHas('file_issues', [
            'id' => $issue->id,
            'status' => FileIssue::STATUS_OPEN,
            'resolved_by_user_id' => null,
            'resolved_at' => null,
        ]);
    }

    public function test_invalid_status_is_rejected(): void
    {
        $file = $this->makeFile();
        $this->createIssue($file);

        $issue = FileIssue::first();

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->patchJson("/api/issues/{$issue->id}", ['status' => 'bogus'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_invalid_status_transition_is_rejected(): void
    {
        $file = $this->makeFile();
        $this->createIssue($file);

        $issue = FileIssue::first();

        Sanctum::actingAs(User::factory()->admin()->create());

        // open -> resolved is valid, but resolved -> dismissed is not.
        $this->patchJson("/api/issues/{$issue->id}", ['status' => FileIssue::STATUS_RESOLVED])->assertOk();
        $this->patchJson("/api/issues/{$issue->id}", ['status' => FileIssue::STATUS_DISMISSED])
            ->assertStatus(422);

        $this->assertDatabaseHas('file_issues', [
            'id' => $issue->id,
            'status' => FileIssue::STATUS_RESOLVED,
        ]);
    }

    public function test_same_status_transition_is_rejected(): void
    {
        $file = $this->makeFile();
        $this->createIssue($file);

        $issue = FileIssue::first();

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->patchJson("/api/issues/{$issue->id}", ['status' => FileIssue::STATUS_OPEN])
            ->assertStatus(422);
    }

    // ---------- Audit logging ----------

    public function test_status_change_logs_audit_entry(): void
    {
        $file = $this->makeFile();
        $this->createIssue($file);

        $issue = FileIssue::first();
        $actor = User::factory()->supervisor()->create();

        Sanctum::actingAs($actor);

        $this->patchJson("/api/issues/{$issue->id}", ['status' => FileIssue::STATUS_IN_PROGRESS])->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $actor->id,
            'action' => 'issue_status_changed',
            'entity_type' => FileIssue::class,
            'entity_id' => $issue->id,
        ]);
    }

    public function test_resolution_logs_audit_entry_with_before_after(): void
    {
        $file = $this->makeFile();
        $this->createIssue($file);

        $issue = FileIssue::first();
        $actor = User::factory()->admin()->create();

        Sanctum::actingAs($actor);

        $this->patchJson("/api/issues/{$issue->id}", ['status' => FileIssue::STATUS_RESOLVED])->assertOk();

        $log = AuditLog::where('action', 'issue_resolved')->where('entity_id', $issue->id)->first();

        $this->assertNotNull($log);
        $this->assertEquals(FileIssue::STATUS_OPEN, $log->before['status']);
        $this->assertEquals(FileIssue::STATUS_RESOLVED, $log->after['status']);
        $this->assertEquals($actor->id, $log->after['resolved_by_user_id']);
    }

    public function test_dismissal_logs_audit_entry(): void
    {
        $file = $this->makeFile();
        $this->createIssue($file);

        $issue = FileIssue::first();
        $actor = User::factory()->admin()->create();

        Sanctum::actingAs($actor);

        $this->patchJson("/api/issues/{$issue->id}", ['status' => FileIssue::STATUS_DISMISSED])->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'issue_dismissed',
            'entity_type' => FileIssue::class,
            'entity_id' => $issue->id,
        ]);
    }

    // ---------- Data integrity ----------

    public function test_file_with_issues_cannot_be_deleted(): void
    {
        $file = $this->makeFile();
        $this->createIssue($file);

        $this->expectException(\Illuminate\Database\QueryException::class);

        $file->delete();
    }

    public function test_reporter_with_issues_cannot_be_deleted(): void
    {
        $file = $this->makeFile();
        $reporter = User::factory()->create();
        $this->createIssue($file, [], $reporter);

        $this->expectException(\Illuminate\Database\QueryException::class);

        $reporter->delete();
    }
}
