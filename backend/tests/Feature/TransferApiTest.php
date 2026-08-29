<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\File;
use App\Models\FileCategory;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TransferApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeFile(array $overrides = []): File
    {
        $fromDepartment = Department::factory()->create();
        $fromHolder = User::factory()->create(['department_id' => $fromDepartment->id]);
        $category = FileCategory::factory()->create(['name' => 'General Registry', 'default_due_days' => 5]);

        return File::factory()->create(array_merge([
            'category_id' => $category->id,
            'confirmed_department_id' => $fromDepartment->id,
            'confirmed_holder_user_id' => $fromHolder->id,
            'registered_by_user_id' => User::factory()->registryStaff()->create()->id,
        ], $overrides));
    }

    private function makeDestination(): array
    {
        $department = Department::factory()->create();
        $holder = User::factory()->create(['department_id' => $department->id]);

        return [
            'department' => $department,
            'holder' => $holder,
        ];
    }

    private function createTransfer(File $file, array $destination, ?User $actor = null): Transfer
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

    // ---------- A. Creating a transfer ----------

    public function test_creating_a_transfer_creates_a_pending_transfer(): void
    {
        $file = $this->makeFile();
        $destination = $this->makeDestination();

        $this->createTransfer($file, $destination);

        $this->assertDatabaseHas('transfers', [
            'file_id' => $file->id,
            'status' => Transfer::STATUS_PENDING,
        ]);
    }

    public function test_creating_a_transfer_captures_current_confirmed_custody_as_from(): void
    {
        $file = $this->makeFile();
        $destination = $this->makeDestination();

        $this->createTransfer($file, $destination);

        $this->assertDatabaseHas('transfers', [
            'file_id' => $file->id,
            'from_department_id' => $file->confirmed_department_id,
            'from_holder_user_id' => $file->confirmed_holder_user_id,
        ]);
    }

    public function test_creating_a_transfer_captures_intended_destination_as_to(): void
    {
        $file = $this->makeFile();
        $destination = $this->makeDestination();

        $this->createTransfer($file, $destination);

        $this->assertDatabaseHas('transfers', [
            'file_id' => $file->id,
            'to_department_id' => $destination['department']->id,
            'to_holder_user_id' => $destination['holder']->id,
        ]);
    }

    public function test_creating_a_transfer_sets_requested_by_server_side(): void
    {
        $file = $this->makeFile();
        $destination = $this->makeDestination();
        $actor = User::factory()->registryStaff()->create();

        $this->createTransfer($file, $destination, $actor);

        $this->assertDatabaseHas('transfers', [
            'file_id' => $file->id,
            'requested_by_user_id' => $actor->id,
        ]);
    }

    public function test_creating_a_transfer_does_not_modify_confirmed_custody(): void
    {
        $file = $this->makeFile();
        $destination = $this->makeDestination();

        $this->createTransfer($file, $destination);

        $this->assertDatabaseHas('files', [
            'id' => $file->id,
            'confirmed_department_id' => $file->confirmed_department_id,
            'confirmed_holder_user_id' => $file->confirmed_holder_user_id,
        ]);
    }

    public function test_creating_a_transfer_sets_due_at_from_category_due_days(): void
    {
        $file = $this->makeFile();
        $destination = $this->makeDestination();

        $this->createTransfer($file, $destination);

        $transfer = Transfer::first();
        $this->assertNotNull($transfer->due_at);
        $this->assertEqualsWithDelta(
            now()->addDays(5)->timestamp,
            $transfer->due_at->timestamp,
            5
        );
    }

    public function test_creating_a_transfer_logs_an_audit_entry(): void
    {
        $file = $this->makeFile();
        $destination = $this->makeDestination();

        $this->createTransfer($file, $destination);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'transfer_created',
            'entity_type' => Transfer::class,
            'entity_id' => Transfer::first()->id,
        ]);
    }

    // ---------- B. Acknowledgement ----------

    public function test_pending_transfer_can_be_acknowledged_by_intended_recipient(): void
    {
        $file = $this->makeFile();
        $destination = $this->makeDestination();
        $this->createTransfer($file, $destination);

        Sanctum::actingAs($destination['holder']);

        $this->postJson("/api/transfers/{$file->transfers()->first()->id}/acknowledge")
            ->assertOk()
            ->assertJsonPath('data.status', Transfer::STATUS_ACKNOWLEDGED)
            ->assertJsonPath('data.acknowledged_by.id', $destination['holder']->id);

        $this->assertNotNull(Transfer::first()->acknowledged_at);
    }

    public function test_acknowledgement_moves_confirmed_custody_to_destination(): void
    {
        $file = $this->makeFile();
        $destination = $this->makeDestination();
        $this->createTransfer($file, $destination);

        Sanctum::actingAs($destination['holder']);

        $this->postJson("/api/transfers/{$file->transfers()->first()->id}/acknowledge")->assertOk();

        $this->assertDatabaseHas('files', [
            'id' => $file->id,
            'confirmed_department_id' => $destination['department']->id,
            'confirmed_holder_user_id' => $destination['holder']->id,
        ]);
    }

    public function test_acknowledgement_logs_an_audit_entry(): void
    {
        $file = $this->makeFile();
        $destination = $this->makeDestination();
        $this->createTransfer($file, $destination);

        Sanctum::actingAs($destination['holder']);

        $this->postJson("/api/transfers/{$file->transfers()->first()->id}/acknowledge")->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'transfer_acknowledged',
            'entity_type' => Transfer::class,
            'entity_id' => $file->transfers()->first()->id,
        ]);
    }

    // ---------- C. Rejection ----------

    public function test_pending_transfer_can_be_rejected_by_intended_recipient(): void
    {
        $file = $this->makeFile();
        $destination = $this->makeDestination();
        $this->createTransfer($file, $destination);

        Sanctum::actingAs($destination['holder']);

        $this->postJson("/api/transfers/{$file->transfers()->first()->id}/reject")
            ->assertOk()
            ->assertJsonPath('data.status', Transfer::STATUS_REJECTED)
            ->assertJsonPath('data.rejected_by.id', $destination['holder']->id);

        $this->assertNotNull(Transfer::first()->rejected_at);
    }

    public function test_rejection_does_not_change_confirmed_custody(): void
    {
        $file = $this->makeFile();
        $destination = $this->makeDestination();
        $this->createTransfer($file, $destination);

        Sanctum::actingAs($destination['holder']);

        $this->postJson("/api/transfers/{$file->transfers()->first()->id}/reject")->assertOk();

        $this->assertDatabaseHas('files', [
            'id' => $file->id,
            'confirmed_department_id' => $file->confirmed_department_id,
            'confirmed_holder_user_id' => $file->confirmed_holder_user_id,
        ]);
    }

    public function test_rejection_logs_an_audit_entry(): void
    {
        $file = $this->makeFile();
        $destination = $this->makeDestination();
        $this->createTransfer($file, $destination);

        Sanctum::actingAs($destination['holder']);

        $this->postJson("/api/transfers/{$file->transfers()->first()->id}/reject")->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'transfer_rejected',
            'entity_type' => Transfer::class,
            'entity_id' => $file->transfers()->first()->id,
        ]);
    }

    // ---------- D. Authorization ----------

    public function test_unauthenticated_user_cannot_create_transfer(): void
    {
        $file = $this->makeFile();
        $destination = $this->makeDestination();

        $this->postJson('/api/transfers', [
            'file_id' => $file->id,
            'to_department_id' => $destination['department']->id,
            'to_holder_user_id' => $destination['holder']->id,
        ])->assertUnauthorized();
    }

    public function test_user_cannot_acknowledge_transfer_intended_for_someone_else(): void
    {
        $file = $this->makeFile();
        $destination = $this->makeDestination();
        $this->createTransfer($file, $destination);

        $stranger = User::factory()->create();

        Sanctum::actingAs($stranger);

        $this->postJson("/api/transfers/{$file->transfers()->first()->id}/acknowledge")
            ->assertForbidden();

        $this->assertDatabaseHas('transfers', [
            'id' => $file->transfers()->first()->id,
            'status' => Transfer::STATUS_PENDING,
        ]);
    }

    public function test_user_cannot_reject_transfer_intended_for_someone_else(): void
    {
        $file = $this->makeFile();
        $destination = $this->makeDestination();
        $this->createTransfer($file, $destination);

        $stranger = User::factory()->create();

        Sanctum::actingAs($stranger);

        $this->postJson("/api/transfers/{$file->transfers()->first()->id}/reject")
            ->assertForbidden();
    }

    public function test_admin_can_acknowledge_transfer(): void
    {
        $file = $this->makeFile();
        $destination = $this->makeDestination();
        $this->createTransfer($file, $destination);

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson("/api/transfers/{$file->transfers()->first()->id}/acknowledge")
            ->assertOk()
            ->assertJsonPath('data.status', Transfer::STATUS_ACKNOWLEDGED);
    }

    public function test_supervisor_can_acknowledge_transfer(): void
    {
        $file = $this->makeFile();
        $destination = $this->makeDestination();
        $this->createTransfer($file, $destination);

        Sanctum::actingAs(User::factory()->supervisor()->create());

        $this->postJson("/api/transfers/{$file->transfers()->first()->id}/acknowledge")
            ->assertOk()
            ->assertJsonPath('data.status', Transfer::STATUS_ACKNOWLEDGED);
    }

    public function test_admin_can_create_transfer(): void
    {
        $file = $this->makeFile();
        $destination = $this->makeDestination();

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/transfers', [
            'file_id' => $file->id,
            'to_department_id' => $destination['department']->id,
            'to_holder_user_id' => $destination['holder']->id,
        ])->assertCreated();

        $this->assertDatabaseHas('transfers', [
            'file_id' => $file->id,
            'status' => Transfer::STATUS_PENDING,
        ]);
    }

    public function test_registry_staff_can_create_transfer(): void
    {
        $file = $this->makeFile();
        $destination = $this->makeDestination();

        Sanctum::actingAs(User::factory()->registryStaff()->create());

        $this->postJson('/api/transfers', [
            'file_id' => $file->id,
            'to_department_id' => $destination['department']->id,
            'to_holder_user_id' => $destination['holder']->id,
        ])->assertCreated();
    }

    public function test_supervisor_can_create_transfer(): void
    {
        $file = $this->makeFile();
        $destination = $this->makeDestination();

        Sanctum::actingAs(User::factory()->supervisor()->create());

        $this->postJson('/api/transfers', [
            'file_id' => $file->id,
            'to_department_id' => $destination['department']->id,
            'to_holder_user_id' => $destination['holder']->id,
        ])->assertCreated();
    }

    public function test_department_staff_cannot_create_transfer(): void
    {
        $file = $this->makeFile();
        $destination = $this->makeDestination();

        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/transfers', [
            'file_id' => $file->id,
            'to_department_id' => $destination['department']->id,
            'to_holder_user_id' => $destination['holder']->id,
        ])->assertForbidden();

        $this->assertDatabaseCount('transfers', 0);
    }

    public function test_noop_transfer_is_rejected(): void
    {
        $file = $this->makeFile();
        $destination = [
            'department' => $file->confirmedDepartment,
            'holder' => $file->confirmedHolder,
        ];

        Sanctum::actingAs(User::factory()->registryStaff()->create());

        $this->postJson('/api/transfers', [
            'file_id' => $file->id,
            'to_department_id' => $destination['department']->id,
            'to_holder_user_id' => $destination['holder']->id,
        ])->assertStatus(422);

        $this->assertDatabaseCount('transfers', 0);
    }

    public function test_second_pending_transfer_creation_is_rejected(): void
    {
        $file = $this->makeFile();
        $destination = $this->makeDestination();
        $this->createTransfer($file, $destination);

        $secondDestination = $this->makeDestination();

        Sanctum::actingAs(User::factory()->registryStaff()->create());

        $this->postJson('/api/transfers', [
            'file_id' => $file->id,
            'to_department_id' => $secondDestination['department']->id,
            'to_holder_user_id' => $secondDestination['holder']->id,
        ])->assertStatus(422);

        $this->assertDatabaseCount('transfers', 1);
    }

    // ---------- E. Invalid state transitions ----------

    public function test_acknowledged_transfer_cannot_be_acknowledged_again(): void
    {
        $file = $this->makeFile();
        $destination = $this->makeDestination();
        $this->createTransfer($file, $destination);

        Sanctum::actingAs($destination['holder']);
        $this->postJson("/api/transfers/{$file->transfers()->first()->id}/acknowledge")->assertOk();

        $this->postJson("/api/transfers/{$file->transfers()->first()->id}/acknowledge")
            ->assertStatus(422);
    }

    public function test_rejected_transfer_cannot_be_acknowledged(): void
    {
        $file = $this->makeFile();
        $destination = $this->makeDestination();
        $this->createTransfer($file, $destination);

        Sanctum::actingAs($destination['holder']);
        $this->postJson("/api/transfers/{$file->transfers()->first()->id}/reject")->assertOk();

        $this->postJson("/api/transfers/{$file->transfers()->first()->id}/acknowledge")
            ->assertStatus(422);
    }

    public function test_acknowledged_transfer_cannot_be_rejected(): void
    {
        $file = $this->makeFile();
        $destination = $this->makeDestination();
        $this->createTransfer($file, $destination);

        Sanctum::actingAs($destination['holder']);
        $this->postJson("/api/transfers/{$file->transfers()->first()->id}/acknowledge")->assertOk();

        $this->postJson("/api/transfers/{$file->transfers()->first()->id}/reject")
            ->assertStatus(422);
    }

    public function test_rejected_transfer_cannot_be_rejected_again(): void
    {
        $file = $this->makeFile();
        $destination = $this->makeDestination();
        $this->createTransfer($file, $destination);

        Sanctum::actingAs($destination['holder']);
        $this->postJson("/api/transfers/{$file->transfers()->first()->id}/reject")->assertOk();

        $this->postJson("/api/transfers/{$file->transfers()->first()->id}/reject")
            ->assertStatus(422);
    }

    // ---------- F. Overdue ----------

    public function test_pending_transfer_with_past_due_at_is_overdue(): void
    {
        $file = $this->makeFile();
        $destination = $this->makeDestination();
        $this->createTransfer($file, $destination);

        $transfer = $file->transfers()->first();
        $transfer->update(['due_at' => now()->subDay()]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/transfers/{$transfer->id}")
            ->assertOk()
            ->assertJsonPath('data.is_overdue', true);
    }

    public function test_pending_transfer_with_future_due_at_is_not_overdue(): void
    {
        $file = $this->makeFile();
        $destination = $this->makeDestination();
        $this->createTransfer($file, $destination);

        $transfer = $file->transfers()->first();
        $transfer->update(['due_at' => now()->addDay()]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/transfers/{$transfer->id}")
            ->assertOk()
            ->assertJsonPath('data.is_overdue', false);
    }

    public function test_file_detail_exposes_pending_transfer_and_overdue_flag(): void
    {
        $file = $this->makeFile();
        $destination = $this->makeDestination();
        $this->createTransfer($file, $destination);

        $transfer = $file->transfers()->first();
        $transfer->update(['due_at' => now()->subDay()]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/files/{$file->id}")
            ->assertOk()
            ->assertJsonPath('data.confirmed_department.id', $file->confirmed_department_id)
            ->assertJsonPath('data.confirmed_holder.id', $file->confirmed_holder_user_id)
            ->assertJsonPath('data.pending_transfer.id', $transfer->id)
            ->assertJsonPath('data.pending_transfer.intended_department.id', $destination['department']->id)
            ->assertJsonPath('data.pending_transfer.intended_holder.id', $destination['holder']->id)
            ->assertJsonPath('data.pending_transfer.is_overdue', true);
    }

    // ---------- G. API validation ----------

    public function test_invalid_file_id_is_rejected(): void
    {
        $destination = $this->makeDestination();

        Sanctum::actingAs(User::factory()->registryStaff()->create());

        $this->postJson('/api/transfers', [
            'file_id' => 999999,
            'to_department_id' => $destination['department']->id,
            'to_holder_user_id' => $destination['holder']->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('file_id');
    }

    public function test_invalid_destination_department_is_rejected(): void
    {
        $file = $this->makeFile();
        $destination = $this->makeDestination();

        Sanctum::actingAs(User::factory()->registryStaff()->create());

        $this->postJson('/api/transfers', [
            'file_id' => $file->id,
            'to_department_id' => 999999,
            'to_holder_user_id' => $destination['holder']->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('to_department_id');
    }

    public function test_invalid_destination_user_is_rejected(): void
    {
        $file = $this->makeFile();
        $destination = $this->makeDestination();

        Sanctum::actingAs(User::factory()->registryStaff()->create());

        $this->postJson('/api/transfers', [
            'file_id' => $file->id,
            'to_department_id' => $destination['department']->id,
            'to_holder_user_id' => 999999,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('to_holder_user_id');
    }

    public function test_destination_user_must_belong_to_destination_department(): void
    {
        $file = $this->makeFile();
        $destination = $this->makeDestination();
        $otherDepartmentUser = User::factory()->create(['department_id' => Department::factory()->create()->id]);

        Sanctum::actingAs(User::factory()->registryStaff()->create());

        $this->postJson('/api/transfers', [
            'file_id' => $file->id,
            'to_department_id' => $destination['department']->id,
            'to_holder_user_id' => $otherDepartmentUser->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('to_holder_user_id');
    }

    public function test_missing_required_fields_are_rejected(): void
    {
        Sanctum::actingAs(User::factory()->registryStaff()->create());

        $this->postJson('/api/transfers', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file_id', 'to_department_id', 'to_holder_user_id']);
    }

    public function test_client_cannot_override_server_controlled_fields(): void
    {
        $file = $this->makeFile();
        $destination = $this->makeDestination();
        $otherUser = User::factory()->create();
        $otherDepartment = Department::factory()->create();

        Sanctum::actingAs(User::factory()->registryStaff()->create());

        $this->postJson('/api/transfers', [
            'file_id' => $file->id,
            'to_department_id' => $destination['department']->id,
            'to_holder_user_id' => $destination['holder']->id,
            'from_department_id' => $otherDepartment->id,
            'from_holder_user_id' => $otherUser->id,
            'requested_by_user_id' => $otherUser->id,
            'requested_at' => now()->subYear()->toISOString(),
            'status' => Transfer::STATUS_ACKNOWLEDGED,
            'acknowledged_by_user_id' => $otherUser->id,
            'acknowledged_at' => now()->toISOString(),
            'due_at' => now()->addYear()->toISOString(),
        ])->assertCreated();

        $transfer = Transfer::first();
        $this->assertEquals($file->confirmed_department_id, $transfer->from_department_id);
        $this->assertEquals($file->confirmed_holder_user_id, $transfer->from_holder_user_id);
        $this->assertEquals(Transfer::STATUS_PENDING, $transfer->status);
        $this->assertNull($transfer->acknowledged_by_user_id);
        $this->assertNull($transfer->acknowledged_at);
    }

    // ---------- H. Custody invariant ----------

    public function test_creating_a_transfer_never_changes_confirmed_custody(): void
    {
        $file = $this->makeFile();
        $destination = $this->makeDestination();

        $this->createTransfer($file, $destination);

        $this->assertDatabaseHas('files', [
            'id' => $file->id,
            'confirmed_department_id' => $file->confirmed_department_id,
            'confirmed_holder_user_id' => $file->confirmed_holder_user_id,
        ]);
    }

    public function test_file_without_confirmed_custodian_cannot_be_transferred(): void
    {
        $file = File::factory()->create([
            'confirmed_department_id' => null,
            'confirmed_holder_user_id' => null,
        ]);
        $destination = $this->makeDestination();

        Sanctum::actingAs(User::factory()->registryStaff()->create());

        $this->postJson('/api/transfers', [
            'file_id' => $file->id,
            'to_department_id' => $destination['department']->id,
            'to_holder_user_id' => $destination['holder']->id,
        ])->assertStatus(422);

        $this->assertDatabaseCount('transfers', 0);
    }

    public function test_file_with_existing_pending_transfer_cannot_get_another(): void
    {
        $file = $this->makeFile();
        $destination = $this->makeDestination();
        $this->createTransfer($file, $destination);

        $secondDestination = $this->makeDestination();

        Sanctum::actingAs(User::factory()->registryStaff()->create());

        $this->postJson('/api/transfers', [
            'file_id' => $file->id,
            'to_department_id' => $secondDestination['department']->id,
            'to_holder_user_id' => $secondDestination['holder']->id,
        ])->assertStatus(422);

        $this->assertDatabaseCount('transfers', 1);
    }

    // ---------- Listing / viewing ----------

    public function test_transfers_can_be_listed_for_a_file(): void
    {
        $file = $this->makeFile();
        $destination = $this->makeDestination();
        $this->createTransfer($file, $destination);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/files/{$file->id}/transfers")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', Transfer::STATUS_PENDING);
    }

    public function test_transfer_can_be_viewed(): void
    {
        $file = $this->makeFile();
        $destination = $this->makeDestination();
        $this->createTransfer($file, $destination);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/transfers/{$file->transfers()->first()->id}")
            ->assertOk()
            ->assertJsonPath('data.file_id', $file->id)
            ->assertJsonPath('data.to_department.id', $destination['department']->id)
            ->assertJsonPath('data.to_holder.id', $destination['holder']->id);
    }

    public function test_destination_department_cannot_be_deleted_while_referenced_by_transfer(): void
    {
        $file = $this->makeFile();
        $destination = $this->makeDestination();
        $this->createTransfer($file, $destination);

        $this->expectException(\Illuminate\Database\QueryException::class);

        $destination['department']->delete();
    }

    public function test_destination_holder_cannot_be_deleted_while_referenced_by_transfer(): void
    {
        $file = $this->makeFile();
        $destination = $this->makeDestination();
        $this->createTransfer($file, $destination);

        $this->expectException(\Illuminate\Database\QueryException::class);

        $destination['holder']->delete();
    }
}
