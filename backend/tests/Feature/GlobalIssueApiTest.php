<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\FileIssue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GlobalIssueApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeFile(array $overrides = []): File
    {
        return File::factory()->create($overrides);
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

    // ---------- Authentication ----------

    public function test_unauthenticated_user_cannot_list_issues(): void
    {
        $this->getJson('/api/issues')->assertUnauthorized();
    }

    // ---------- Listing & serialization ----------

    public function test_any_authenticated_user_can_list_issues(): void
    {
        $file = $this->makeFile();
        $this->createIssue($file);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/issues')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_issue_list_serializes_expected_shape(): void
    {
        $file = $this->makeFile();
        $reporter = User::factory()->create();
        $this->createIssue($file, [], $reporter);

        $issue = FileIssue::first();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/issues')
            ->assertOk()
            ->assertJsonPath('data.0.id', $issue->id)
            ->assertJsonPath('data.0.file.id', $file->id)
            ->assertJsonPath('data.0.file.file_number', $file->file_number)
            ->assertJsonPath('data.0.issue_type', 'damage')
            ->assertJsonPath('data.0.status', FileIssue::STATUS_OPEN)
            ->assertJsonPath('data.0.reported_by.id', $reporter->id);
    }

    public function test_issue_list_orders_newest_first(): void
    {
        $file = $this->makeFile();
        $older = FileIssue::factory()->create(['file_id' => $file->id, 'created_at' => now()->subDays(2)]);
        $newer = FileIssue::factory()->create(['file_id' => $file->id, 'created_at' => now()->subDay()]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/issues')
            ->assertOk()
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id);
    }

    // ---------- Pagination ----------

    public function test_issue_list_paginates_with_default_per_page(): void
    {
        $file = $this->makeFile();
        FileIssue::factory()->count(3)->create(['file_id' => $file->id]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/issues')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.total', 3);
    }

    public function test_issue_list_respects_per_page(): void
    {
        $file = $this->makeFile();
        FileIssue::factory()->count(5)->create(['file_id' => $file->id]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/issues?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.last_page', 3);
    }

    public function test_issue_list_caps_per_page_at_100(): void
    {
        $file = $this->makeFile();
        FileIssue::factory()->create(['file_id' => $file->id]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/issues?per_page=500')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 100);
    }

    // ---------- Filters ----------

    public function test_issue_list_filters_by_status(): void
    {
        $file = $this->makeFile();
        $this->createIssue($file);
        $issue = FileIssue::first();
        $issue->update(['status' => FileIssue::STATUS_RESOLVED]);

        $this->createIssue($file);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/issues?status=resolved')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', FileIssue::STATUS_RESOLVED);
    }

    public function test_issue_list_filters_by_file_number_search(): void
    {
        $file = $this->makeFile(['file_number' => 'REG/2026/SPECIAL']);
        $this->createIssue($file);

        $otherFile = $this->makeFile();
        $this->createIssue($otherFile);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/issues?search=SPECIAL')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.file.id', $file->id);
    }

    // ---------- Empty results ----------

    public function test_issue_list_returns_empty_when_no_issues(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/issues')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }

    // ---------- Invalid parameters ----------

    public function test_issue_list_rejects_invalid_status(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/issues?status=bogus')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_issue_list_rejects_invalid_per_page(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/issues?per_page=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }
}
