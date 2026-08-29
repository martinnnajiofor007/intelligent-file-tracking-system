<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\File;
use App\Models\FileCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_phase_one_relationships_resolve_correctly(): void
    {
        $department = Department::factory()->create(['name' => 'Registry']);
        $category = FileCategory::factory()->create(['name' => 'General Registry']);
        $holder = User::factory()->create(['department_id' => $department->id]);
        $registeredBy = User::factory()->registryStaff()->create();

        $file = File::factory()->create([
            'category_id' => $category->id,
            'confirmed_department_id' => $department->id,
            'confirmed_holder_user_id' => $holder->id,
            'registered_by_user_id' => $registeredBy->id,
        ]);

        $this->assertTrue($file->category->is($category));
        $this->assertTrue($file->confirmedDepartment->is($department));
        $this->assertTrue($file->confirmedHolder->is($holder));
        $this->assertTrue($file->registeredBy->is($registeredBy));
        $this->assertTrue($holder->department->is($department));
    }
}
