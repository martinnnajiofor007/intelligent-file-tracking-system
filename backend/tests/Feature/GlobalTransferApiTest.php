<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\File;
use App\Models\FileCategory;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GlobalTransferApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeFile(array $overrides = []): File
    {
        $fromDepartment = Department::factory()->create();
        $fromHolder = User::factory()->create(['department_id' => $fromDepartment->id]);
        $category = FileCategory::factory()->create(['name' => 'General Registry ' . uniqid(), 'default_due_days' => 5]);

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

    // ---------- Authentication ----------

    public function test_unauthenticated_user_cannot_list_transfers(): void
    {
        $this->getJson('/api/transfers')->assertUnauthorized();
    }

    // ---------- Listing & serialization ----------

    public function test_any_authenticated_user_can_list_transfers(): void
    {
        $file = $this->makeFile();
        $destination = $this->makeDestination();
        $this->createTransfer($file, $destination);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/transfers')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_transfer_list_serializes_expected_shape(): void
    {
        $file = $this->makeFile();
        $destination = $this->makeDestination();
        $this->createTransfer($file, $destination);

        $transfer = Transfer::first();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/transfers')
            ->assertOk()
            ->assertJsonPath('data.0.id', $transfer->id)
            ->assertJsonPath('data.0.file_id', $file->id)
            ->assertJsonPath('data.0.status', Transfer::STATUS_PENDING)
            ->assertJsonPath('data.0.from_department.id', $file->confirmed_department_id)
            ->assertJsonPath('data.0.from_holder.id', $file->confirmed_holder_user_id)
            ->assertJsonPath('data.0.to_department.id', $destination['department']->id)
            ->assertJsonPath('data.0.to_holder.id', $destination['holder']->id)
            ->assertJsonPath('data.0.requested_by.id', $transfer->requested_by_user_id)
            ->assertJsonPath('data.0.is_overdue', false);
    }

    public function test_transfer_list_eager_loads_file_relationship(): void
    {
        $file = $this->makeFile();
        $destination = $this->makeDestination();
        $this->createTransfer($file, $destination);

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/transfers')
            ->assertOk()
            ->assertJsonPath('data.0.file_id', $file->id);
    }

    public function test_transfer_list_orders_newest_requested_first(): void
    {
        $fileA = $this->makeFile();
        $fileB = $this->makeFile();
        $destA = $this->makeDestination();
        $destB = $this->makeDestination();

        $this->createTransfer($fileA, $destA);
        $older = Transfer::first();
        $older->update(['requested_at' => now()->subDays(2)]);

        $this->createTransfer($fileB, $destB);
        $newer = Transfer::orderByDesc('id')->first();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/transfers')
            ->assertOk()
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id);
    }

    // ---------- Pagination ----------

    public function test_transfer_list_paginates_with_default_per_page(): void
    {
        foreach (range(1, 3) as $i) {
            $this->createTransfer($this->makeFile(), $this->makeDestination());
        }

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/transfers')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.total', 3);
    }

    public function test_transfer_list_respects_per_page(): void
    {
        foreach (range(1, 5) as $i) {
            $this->createTransfer($this->makeFile(), $this->makeDestination());
        }

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/transfers?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.last_page', 3);
    }

    public function test_transfer_list_caps_per_page_at_100(): void
    {
        $this->createTransfer($this->makeFile(), $this->makeDestination());

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/transfers?per_page=500')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 100);
    }

    // ---------- Filters ----------

    public function test_transfer_list_filters_by_status(): void
    {
        $this->createTransfer($this->makeFile(), $this->makeDestination());
        $transfer = Transfer::first();
        $transfer->update(['status' => Transfer::STATUS_ACKNOWLEDGED]);

        $this->createTransfer($this->makeFile(), $this->makeDestination());

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/transfers?status=acknowledged')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', Transfer::STATUS_ACKNOWLEDGED);
    }

    public function test_transfer_list_filters_by_overdue(): void
    {
        $this->createTransfer($this->makeFile(), $this->makeDestination());
        $overdue = Transfer::first();
        $overdue->update(['due_at' => now()->subDay()]);

        $this->createTransfer($this->makeFile(), $this->makeDestination());

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/transfers?overdue=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $overdue->id)
            ->assertJsonPath('data.0.is_overdue', true);
    }

    public function test_transfer_list_filters_by_file_number_search(): void
    {
        $file = $this->makeFile(['file_number' => 'REG/2026/SPECIAL']);
        $this->createTransfer($file, $this->makeDestination());

        $this->createTransfer($this->makeFile(), $this->makeDestination());

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/transfers?search=SPECIAL')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.file_id', $file->id);
    }

    // ---------- Empty results ----------

    public function test_transfer_list_returns_empty_when_no_transfers(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/transfers')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }

    // ---------- Invalid parameters ----------

    public function test_transfer_list_rejects_invalid_status(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/transfers?status=bogus')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_transfer_list_rejects_invalid_per_page(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/transfers?per_page=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }

    public function test_transfer_list_rejects_invalid_overdue_value(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/transfers?overdue=notabool')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('overdue');
    }
}
