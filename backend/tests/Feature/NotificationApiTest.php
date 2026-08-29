<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\File;
use App\Models\FileCategory;
use App\Models\FileIssue;
use App\Models\Notification;
use App\Models\Transfer;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeFile(): File
    {
        $department = Department::factory()->create();
        $holder = User::factory()->create(['department_id' => $department->id]);
        $category = FileCategory::factory()->create(['default_due_days' => 5]);

        return File::factory()->create([
            'category_id' => $category->id,
            'confirmed_department_id' => $department->id,
            'confirmed_holder_user_id' => $holder->id,
            'registered_by_user_id' => User::factory()->registryStaff()->create()->id,
        ]);
    }

    private function makeDestination(): array
    {
        $department = Department::factory()->create();
        $holder = User::factory()->create(['department_id' => $department->id]);

        return ['department' => $department, 'holder' => $holder];
    }

    private function createTransferViaApi(File $file, array $destination, ?User $actor = null): Transfer
    {
        $actor = $actor ?? User::factory()->registryStaff()->create();

        Sanctum::actingAs($actor);

        $this->postJson('/api/transfers', [
            'file_id' => $file->id,
            'to_department_id' => $destination['department']->id,
            'to_holder_user_id' => $destination['holder']->id,
        ])->assertCreated();

        return Transfer::first();
    }

    // ---------- Notification creation ----------

    public function test_notification_creation_records_recipient_type_and_entity(): void
    {
        $recipient = User::factory()->create();
        $service = app(NotificationService::class);

        $notification = $service->create(
            $recipient->id,
            'transfer_created',
            'Title',
            'Message',
            Transfer::class,
            42,
            ['file_id' => 7]
        );

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'user_id' => $recipient->id,
            'type' => 'transfer_created',
            'related_type' => Transfer::class,
            'related_id' => 42,
        ]);
        $this->assertEquals(['file_id' => 7], $notification->metadata);
    }

    public function test_notification_does_not_store_sensitive_data(): void
    {
        $recipient = User::factory()->create();
        $service = app(NotificationService::class);

        $service->create(
            $recipient->id,
            'transfer_created',
            'Title',
            'Message',
            Transfer::class,
            1,
            ['password' => 'secret', 'token' => 'abc', 'safe' => 'value']
        );

        $notification = Notification::first();
        $this->assertArrayNotHasKey('password', $notification->metadata);
        $this->assertArrayNotHasKey('token', $notification->metadata);
        $this->assertEquals('value', $notification->metadata['safe']);
    }

    // ---------- Transfer notifications ----------

    public function test_transfer_creation_notifies_intended_recipient(): void
    {
        $file = $this->makeFile();
        $destination = $this->makeDestination();

        $this->createTransferViaApi($file, $destination);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $destination['holder']->id,
            'type' => 'transfer_created',
            'related_type' => Transfer::class,
            'related_id' => Transfer::first()->id,
        ]);
    }

    public function test_transfer_acknowledgement_notifies_requester(): void
    {
        $file = $this->makeFile();
        $destination = $this->makeDestination();
        $requester = User::factory()->registryStaff()->create();
        $this->createTransferViaApi($file, $destination, $requester);

        Sanctum::actingAs($destination['holder']);
        $this->postJson("/api/transfers/{$file->transfers()->first()->id}/acknowledge")->assertOk();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $requester->id,
            'type' => 'transfer_acknowledged',
            'related_type' => Transfer::class,
            'related_id' => $file->transfers()->first()->id,
        ]);
    }

    public function test_transfer_rejection_notifies_requester(): void
    {
        $file = $this->makeFile();
        $destination = $this->makeDestination();
        $requester = User::factory()->registryStaff()->create();
        $this->createTransferViaApi($file, $destination, $requester);

        Sanctum::actingAs($destination['holder']);
        $this->postJson("/api/transfers/{$file->transfers()->first()->id}/reject")->assertOk();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $requester->id,
            'type' => 'transfer_rejected',
            'related_type' => Transfer::class,
            'related_id' => $file->transfers()->first()->id,
        ]);
    }

    // ---------- Issue notifications ----------

    public function test_issue_creation_notifies_responsible_users(): void
    {
        $file = $this->makeFile();
        $admin = User::factory()->admin()->create();
        $supervisor = User::factory()->supervisor()->create();
        $registry = User::factory()->registryStaff()->create();
        $reporter = User::factory()->create();

        Sanctum::actingAs($reporter);
        $this->postJson("/api/files/{$file->id}/issues", [
            'issue_type' => 'damage',
            'description' => 'Torn cover.',
        ])->assertCreated();

        $issue = FileIssue::first();

        foreach ([$admin->id, $supervisor->id, $registry->id] as $userId) {
            $this->assertDatabaseHas('notifications', [
                'user_id' => $userId,
                'type' => 'issue_reported',
                'related_type' => FileIssue::class,
                'related_id' => $issue->id,
            ]);
        }

        // Reporter is excluded from self-notification.
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $reporter->id,
            'type' => 'issue_reported',
        ]);
    }

    public function test_issue_status_change_notifies_reporter(): void
    {
        $file = $this->makeFile();
        $reporter = User::factory()->create();

        Sanctum::actingAs($reporter);
        $this->postJson("/api/files/{$file->id}/issues", [
            'issue_type' => 'damage',
            'description' => 'Torn cover.',
        ])->assertCreated();

        $issue = FileIssue::first();

        Sanctum::actingAs(User::factory()->admin()->create());
        $this->patchJson("/api/issues/{$issue->id}", ['status' => FileIssue::STATUS_RESOLVED])->assertOk();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $reporter->id,
            'type' => 'issue_status_changed',
            'related_type' => FileIssue::class,
            'related_id' => $issue->id,
        ]);
    }

    public function test_issue_reopening_notifies_reporter(): void
    {
        $file = $this->makeFile();
        $reporter = User::factory()->create();

        Sanctum::actingAs($reporter);
        $this->postJson("/api/files/{$file->id}/issues", [
            'issue_type' => 'damage',
            'description' => 'Torn cover.',
        ])->assertCreated();

        $issue = FileIssue::first();

        Sanctum::actingAs(User::factory()->admin()->create());
        $this->patchJson("/api/issues/{$issue->id}", ['status' => FileIssue::STATUS_RESOLVED])->assertOk();
        $this->patchJson("/api/issues/{$issue->id}", ['status' => FileIssue::STATUS_OPEN])->assertOk();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $reporter->id,
            'type' => 'issue_status_changed',
            'related_type' => FileIssue::class,
            'related_id' => $issue->id,
        ]);
    }

    // ---------- Overdue notifications ----------

    public function test_overdue_transfer_receives_notification(): void
    {
        $file = $this->makeFile();
        $destination = $this->makeDestination();
        $this->createTransferViaApi($file, $destination);

        $transfer = $file->transfers()->first();
        $transfer->update(['due_at' => now()->subDay()]);

        $this->artisan('transfers:notify-overdue')->assertExitCode(0);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $destination['holder']->id,
            'type' => 'transfer_overdue',
            'related_type' => Transfer::class,
            'related_id' => $transfer->id,
        ]);
    }

    public function test_future_transfer_does_not_receive_overdue_notification(): void
    {
        $file = $this->makeFile();
        $destination = $this->makeDestination();
        $this->createTransferViaApi($file, $destination);

        $transfer = $file->transfers()->first();
        $transfer->update(['due_at' => now()->addDay()]);

        $this->artisan('transfers:notify-overdue')->assertExitCode(0);

        $this->assertDatabaseMissing('notifications', [
            'type' => 'transfer_overdue',
        ]);
    }

    public function test_acknowledged_transfer_does_not_receive_overdue_notification(): void
    {
        $file = $this->makeFile();
        $destination = $this->makeDestination();
        $this->createTransferViaApi($file, $destination);

        $transfer = $file->transfers()->first();
        $transfer->update(['due_at' => now()->subDay()]);

        Sanctum::actingAs($destination['holder']);
        $this->postJson("/api/transfers/{$transfer->id}/acknowledge")->assertOk();

        $this->artisan('transfers:notify-overdue')->assertExitCode(0);

        $this->assertDatabaseMissing('notifications', [
            'type' => 'transfer_overdue',
        ]);
    }

    public function test_rejected_transfer_does_not_receive_overdue_notification(): void
    {
        $file = $this->makeFile();
        $destination = $this->makeDestination();
        $this->createTransferViaApi($file, $destination);

        $transfer = $file->transfers()->first();
        $transfer->update(['due_at' => now()->subDay()]);

        Sanctum::actingAs($destination['holder']);
        $this->postJson("/api/transfers/{$transfer->id}/reject")->assertOk();

        $this->artisan('transfers:notify-overdue')->assertExitCode(0);

        $this->assertDatabaseMissing('notifications', [
            'type' => 'transfer_overdue',
        ]);
    }

    public function test_null_due_at_transfer_does_not_receive_overdue_notification(): void
    {
        $file = $this->makeFile();
        $destination = $this->makeDestination();
        $this->createTransferViaApi($file, $destination);

        $transfer = $file->transfers()->first();
        $transfer->update(['due_at' => null]);

        $this->artisan('transfers:notify-overdue')->assertExitCode(0);

        $this->assertDatabaseMissing('notifications', [
            'type' => 'transfer_overdue',
        ]);
    }

    public function test_running_overdue_notification_twice_does_not_create_duplicates(): void
    {
        $file = $this->makeFile();
        $destination = $this->makeDestination();
        $this->createTransferViaApi($file, $destination);

        $transfer = $file->transfers()->first();
        $transfer->update(['due_at' => now()->subDay()]);

        $this->artisan('transfers:notify-overdue')->assertExitCode(0);
        $this->artisan('transfers:notify-overdue')->assertExitCode(0);

        $this->assertEquals(1, Notification::where('type', 'transfer_overdue')->count());
        $this->assertDatabaseHas('notifications', [
            'user_id' => $destination['holder']->id,
            'type' => 'transfer_overdue',
        ]);
    }

    public function test_notify_transfer_overdue_returns_false_on_duplicate(): void
    {
        $file = $this->makeFile();
        $destination = $this->makeDestination();
        $this->createTransferViaApi($file, $destination);

        $transfer = $file->transfers()->first();
        $transfer->update(['due_at' => now()->subDay()]);

        $service = app(NotificationService::class);

        $this->assertTrue($service->notifyTransferOverdue($transfer));
        $this->assertFalse($service->notifyTransferOverdue($transfer));

        $this->assertEquals(1, Notification::where('type', 'transfer_overdue')->count());
    }

    public function test_dedup_key_unique_constraint_blocks_duplicate_insert(): void
    {
        $file = $this->makeFile();
        $destination = $this->makeDestination();
        $this->createTransferViaApi($file, $destination);

        $transfer = $file->transfers()->first();
        $service = app(NotificationService::class);

        // Insert the first overdue notification directly with its dedup key.
        $service->notifyTransferOverdue($transfer);

        // A second insert with the same dedup key must violate the unique index.
        $this->expectException(\Illuminate\Database\QueryException::class);

        Notification::create([
            'user_id' => $destination['holder']->id,
            'type' => 'transfer_overdue',
            'title' => 'Overdue transfer',
            'message' => 'Duplicate',
            'related_type' => Transfer::class,
            'related_id' => $transfer->id,
            'dedup_key' => 'transfer_overdue:' . $destination['holder']->id . ':' . Transfer::class . ':' . $transfer->id,
        ]);
    }

    public function test_other_notification_types_are_not_blocked_by_dedup_key(): void
    {
        $file = $this->makeFile();
        $reporter = User::factory()->create();

        Sanctum::actingAs($reporter);
        $this->postJson("/api/files/{$file->id}/issues", [
            'issue_type' => 'damage',
            'description' => 'Torn cover.',
        ])->assertCreated();

        $issue = FileIssue::first();

        // Multiple status changes for the same issue must each produce a
        // notification (no dedup_key), proving the unique index does not
        // block legitimate one-shot notifications.
        Sanctum::actingAs(User::factory()->admin()->create());
        $this->patchJson("/api/issues/{$issue->id}", ['status' => FileIssue::STATUS_IN_PROGRESS])->assertOk();
        $this->patchJson("/api/issues/{$issue->id}", ['status' => FileIssue::STATUS_RESOLVED])->assertOk();

        $this->assertEquals(2, Notification::where('type', 'issue_status_changed')
            ->where('related_id', $issue->id)
            ->count());
    }

    public function test_overdue_notification_processing_does_not_change_transfer_status(): void
    {
        $file = $this->makeFile();
        $destination = $this->makeDestination();
        $this->createTransferViaApi($file, $destination);

        $transfer = $file->transfers()->first();
        $transfer->update(['due_at' => now()->subDay()]);

        $this->artisan('transfers:notify-overdue')->assertExitCode(0);

        $this->assertDatabaseHas('transfers', [
            'id' => $transfer->id,
            'status' => Transfer::STATUS_PENDING,
        ]);
    }

    public function test_overdue_notification_processing_does_not_change_confirmed_custody(): void
    {
        $file = $this->makeFile();
        $destination = $this->makeDestination();
        $this->createTransferViaApi($file, $destination);

        $transfer = $file->transfers()->first();
        $transfer->update(['due_at' => now()->subDay()]);

        $this->artisan('transfers:notify-overdue')->assertExitCode(0);

        $this->assertDatabaseHas('files', [
            'id' => $file->id,
            'confirmed_department_id' => $file->confirmed_department_id,
            'confirmed_holder_user_id' => $file->confirmed_holder_user_id,
        ]);
    }

    // ---------- API security ----------

    public function test_unauthenticated_requests_fail(): void
    {
        $this->getJson('/api/notifications')->assertUnauthorized();
        $this->postJson('/api/notifications/read-all')->assertUnauthorized();
    }

    public function test_user_only_sees_their_own_notifications(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $mine = Notification::factory()->create(['user_id' => $user->id]);
        Notification::factory()->create(['user_id' => $other->id]);

        Sanctum::actingAs($user);

        $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id);
    }

    public function test_user_cannot_retrieve_another_users_notification(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $notification = Notification::factory()->create(['user_id' => $other->id]);

        Sanctum::actingAs($user);

        $this->getJson("/api/notifications/{$notification->id}")->assertNotFound();
    }

    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $notification = Notification::factory()->create(['user_id' => $other->id]);

        Sanctum::actingAs($user);

        $this->patchJson("/api/notifications/{$notification->id}/read")->assertNotFound();

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'read_at' => null,
        ]);
    }

    public function test_notifications_are_newest_first(): void
    {
        $user = User::factory()->create();
        $older = Notification::factory()->create(['user_id' => $user->id, 'created_at' => now()->subDays(2)]);
        $newer = Notification::factory()->create(['user_id' => $user->id, 'created_at' => now()->subDay()]);

        Sanctum::actingAs($user);

        $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id);
    }

    public function test_notifications_paginate(): void
    {
        $user = User::factory()->create();
        Notification::factory()->count(5)->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $this->getJson('/api/notifications?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.last_page', 3);
    }

    public function test_notifications_per_page_is_capped_at_100(): void
    {
        $user = User::factory()->create();
        Notification::factory()->count(120)->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $this->getJson('/api/notifications?per_page=1000')
            ->assertOk()
            ->assertJsonCount(100, 'data')
            ->assertJsonPath('meta.per_page', 100);
    }

    public function test_unread_filtering_works(): void
    {
        $user = User::factory()->create();
        Notification::factory()->create(['user_id' => $user->id, 'read_at' => now()]);
        Notification::factory()->create(['user_id' => $user->id, 'read_at' => null]);

        Sanctum::actingAs($user);

        $this->getJson('/api/notifications?unread=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.is_read', false);
    }

    public function test_mark_single_notification_as_read(): void
    {
        $user = User::factory()->create();
        $notification = Notification::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $this->patchJson("/api/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('data.is_read', true);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_mark_all_notifications_as_read(): void
    {
        $user = User::factory()->create();
        Notification::factory()->count(3)->create(['user_id' => $user->id, 'read_at' => null]);
        Notification::factory()->create(['user_id' => $user->id, 'read_at' => now()]);

        Sanctum::actingAs($user);

        $this->postJson('/api/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('data.updated', 3);

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $user->id,
            'read_at' => null,
        ]);
    }

    // ---------- Scheduler ----------

    public function test_scheduled_task_is_registered(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('transfers:notify-overdue');
    }

    public function test_command_can_be_executed_safely_more_than_once(): void
    {
        $file = $this->makeFile();
        $destination = $this->makeDestination();
        $this->createTransferViaApi($file, $destination);

        $transfer = $file->transfers()->first();
        $transfer->update(['due_at' => now()->subDay()]);

        $this->artisan('transfers:notify-overdue')->assertExitCode(0);
        $this->artisan('transfers:notify-overdue')->assertExitCode(0);

        $this->assertEquals(1, Notification::where('type', 'transfer_overdue')->count());
    }
}
