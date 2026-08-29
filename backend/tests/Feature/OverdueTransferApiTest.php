<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OverdueTransferApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeTransfer(array $overrides = []): Transfer
    {
        return Transfer::factory()->create(array_merge([
            'status' => Transfer::STATUS_PENDING,
            'due_at' => now()->addDay(),
        ], $overrides));
    }

    // ---------- Overdue definition ----------

    public function test_pending_transfer_with_future_due_at_is_not_overdue(): void
    {
        $transfer = $this->makeTransfer(['due_at' => now()->addDay()]);

        $this->assertFalse($transfer->isOverdue());
    }

    public function test_pending_transfer_with_past_due_at_is_overdue(): void
    {
        $transfer = $this->makeTransfer(['due_at' => now()->subDay()]);

        $this->assertTrue($transfer->isOverdue());
    }

    public function test_acknowledged_transfer_with_past_due_at_is_not_overdue(): void
    {
        $transfer = $this->makeTransfer([
            'status' => Transfer::STATUS_ACKNOWLEDGED,
            'due_at' => now()->subDay(),
        ]);

        $this->assertFalse($transfer->isOverdue());
    }

    public function test_rejected_transfer_with_past_due_at_is_not_overdue(): void
    {
        $transfer = $this->makeTransfer([
            'status' => Transfer::STATUS_REJECTED,
            'due_at' => now()->subDay(),
        ]);

        $this->assertFalse($transfer->isOverdue());
    }

    public function test_null_due_at_is_not_overdue(): void
    {
        $transfer = $this->makeTransfer(['due_at' => null]);

        $this->assertFalse($transfer->isOverdue());
    }

    // ---------- Endpoint ----------

    public function test_overdue_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/transfers/overdue')->assertUnauthorized();
    }

    public function test_overdue_endpoint_returns_only_overdue_pending_transfers(): void
    {
        $file = File::factory()->create();
        $overdue = $this->makeTransfer(['file_id' => $file->id, 'due_at' => now()->subDay()]);
        $this->makeTransfer(['file_id' => $file->id, 'due_at' => now()->addDay()]);
        $this->makeTransfer(['file_id' => $file->id, 'status' => Transfer::STATUS_ACKNOWLEDGED, 'due_at' => now()->subDay()]);
        $this->makeTransfer(['file_id' => $file->id, 'status' => Transfer::STATUS_REJECTED, 'due_at' => now()->subDay()]);
        $this->makeTransfer(['file_id' => $file->id, 'due_at' => null]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/transfers/overdue')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $overdue->id)
            ->assertJsonPath('data.0.status', Transfer::STATUS_PENDING)
            ->assertJsonPath('data.0.is_overdue', true)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_overdue_endpoint_includes_related_information(): void
    {
        $file = File::factory()->create();
        $transfer = $this->makeTransfer(['file_id' => $file->id, 'due_at' => now()->subDay()]);

        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/transfers/overdue')
            ->assertOk()
            ->assertJsonPath('data.0.file_id', $transfer->file_id)
            ->assertJsonPath('data.0.from_department.id', $transfer->from_department_id)
            ->assertJsonPath('data.0.to_department.id', $transfer->to_department_id)
            ->assertJsonPath('data.0.requested_by.id', $transfer->requested_by_user_id);

        $this->assertNotNull($response->json('data.0.due_at'));
    }

    public function test_overdue_endpoint_paginates(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->makeTransfer(['due_at' => now()->subDay()]);
        }

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/transfers/overdue?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.last_page', 3);
    }

    public function test_overdue_endpoint_is_not_confused_with_file_transfers_route(): void
    {
        $this->makeTransfer(['due_at' => now()->subDay()]);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/transfers/overdue')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    // ---------- Command ----------

    public function test_command_detects_overdue_transfers(): void
    {
        $this->makeTransfer(['due_at' => now()->subDay()]);
        $this->makeTransfer(['due_at' => now()->addDay()]);

        $this->artisan('transfers:detect-overdue')
            ->expectsOutputToContain('1')
            ->assertExitCode(0);
    }

    public function test_running_command_repeatedly_is_safe(): void
    {
        $this->makeTransfer(['due_at' => now()->subDay()]);

        $this->artisan('transfers:detect-overdue')->assertExitCode(0);
        $this->artisan('transfers:detect-overdue')->assertExitCode(0);

        $this->assertDatabaseCount('transfers', 1);
        $this->assertDatabaseHas('transfers', [
            'status' => Transfer::STATUS_PENDING,
        ]);
    }

    // ---------- Custody invariant ----------

    public function test_detecting_overdue_transfers_does_not_change_confirmed_custody(): void
    {
        $file = File::factory()->create();
        $transfer = $this->makeTransfer([
            'file_id' => $file->id,
            'due_at' => now()->subDay(),
        ]);

        $this->artisan('transfers:detect-overdue')->assertExitCode(0);

        $this->assertDatabaseHas('files', [
            'id' => $file->id,
            'confirmed_department_id' => $file->confirmed_department_id,
            'confirmed_holder_user_id' => $file->confirmed_holder_user_id,
        ]);

        $this->assertDatabaseHas('transfers', [
            'id' => $transfer->id,
            'status' => Transfer::STATUS_PENDING,
        ]);
    }
}
