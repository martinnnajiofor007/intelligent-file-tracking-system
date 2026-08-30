<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\File;
use App\Models\FileCategory;
use App\Models\FileIssue;
use App\Models\Transfer;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\FileIssueService;
use App\Services\NotificationService;
use App\Services\TransferService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $departments = collect([
            'Registry',
            'Finance',
            'Human Resources',
            'Procurement',
            'Legal',
        ])->mapWithKeys(fn (string $name) => [
            $name => Department::updateOrCreate(['name' => $name], ['parent_id' => null]),
        ]);

        $admin = User::updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@example.com')],
            [
                'name' => env('ADMIN_NAME', 'System Administrator'),
                'password' => Hash::make(env('ADMIN_PASSWORD', 'password')),
                'role' => 'admin',
                'department_id' => null,
                'is_active' => true,
            ]
        );

        $users = collect([
            [
                'name' => 'Amina Yusuf',
                'email' => 'registry@example.com',
                'role' => 'registry_staff',
                'department' => 'Registry',
            ],
            [
                'name' => 'David Okafor',
                'email' => 'finance@example.com',
                'role' => 'department_staff',
                'department' => 'Finance',
            ],
            [
                'name' => 'Grace Mensah',
                'email' => 'hr@example.com',
                'role' => 'department_staff',
                'department' => 'Human Resources',
            ],
            [
                'name' => 'Samuel Adeyemi',
                'email' => 'procurement@example.com',
                'role' => 'department_staff',
                'department' => 'Procurement',
            ],
            [
                'name' => 'Nkechi Bello',
                'email' => 'legal@example.com',
                'role' => 'department_staff',
                'department' => 'Legal',
            ],
            [
                'name' => 'Fatima Musa',
                'email' => 'supervisor@example.com',
                'role' => 'supervisor',
                'department' => 'Registry',
            ],
        ])->mapWithKeys(function (array $user) use ($departments) {
            return [
                $user['email'] => User::updateOrCreate(
                    ['email' => $user['email']],
                    [
                        'name' => $user['name'],
                        'password' => Hash::make('password'),
                        'role' => $user['role'],
                        'department_id' => $departments[$user['department']]->id,
                        'is_active' => true,
                    ]
                ),
            ];
        });

        $categories = collect([
            ['name' => 'Finance', 'default_due_days' => 7],
            ['name' => 'HR', 'default_due_days' => 10],
            ['name' => 'Procurement', 'default_due_days' => 14],
            ['name' => 'Legal', 'default_due_days' => 14],
            ['name' => 'General Registry', 'default_due_days' => 5],
        ])->mapWithKeys(fn (array $category) => [
            $category['name'] => FileCategory::updateOrCreate(
                ['name' => $category['name']],
                ['default_due_days' => $category['default_due_days']]
            ),
        ]);

        $registryUser = $users['registry@example.com'];

        collect([
            ['REG/2026/001', 'General Administrative Correspondence', 'General Registry', 'Registry', 'registry@example.com'],
            ['FIN/2026/002', 'Q1 Budget Release Request', 'Finance', 'Finance', 'finance@example.com'],
            ['HR/2026/003', 'Staff Promotion Review File', 'HR', 'Human Resources', 'hr@example.com'],
            ['PROC/2026/004', 'Office Equipment Procurement File', 'Procurement', 'Procurement', 'procurement@example.com'],
            ['LEG/2026/005', 'Contract Review for Vendor Agreement', 'Legal', 'Legal', 'legal@example.com'],
            ['REG/2026/006', 'Board Meeting Registry File', 'General Registry', 'Registry', 'supervisor@example.com'],
            ['FIN/2026/007', 'Vendor Payment Authorization', 'Finance', 'Finance', 'finance@example.com'],
            ['HR/2026/008', 'Training Nomination Records', 'HR', 'Human Resources', 'hr@example.com'],
            ['PROC/2026/009', 'Vehicle Maintenance Procurement', 'Procurement', 'Procurement', 'procurement@example.com'],
            ['LEG/2026/010', 'Policy Compliance Legal Opinion', 'Legal', 'Legal', 'legal@example.com'],
        ])->each(function (array $file) use ($categories, $departments, $users, $registryUser) {
            [$fileNumber, $title, $category, $department, $holderEmail] = $file;

            // Only create the file if it does not already exist. This preserves
            // confirmed custody changes made by acknowledged transfers when the
            // seeder is re-run.
            if (File::where('file_number', $fileNumber)->exists()) {
                return;
            }

            File::create([
                'file_number' => $fileNumber,
                'title' => $title,
                'description' => "Initial registered physical file for {$title}.",
                'category_id' => $categories[$category]->id,
                'confirmed_department_id' => $departments[$department]->id,
                'confirmed_holder_user_id' => $users[$holderEmail]->id,
                'status' => File::STATUS_ACTIVE,
                'registered_by_user_id' => $registryUser->id,
                'registered_at' => now(),
            ]);
        });

        $this->seedTransfers($users, $departments);
        $this->seedIssues($users, $admin);
        $this->seedFileAuditLogs($registryUser);

        $admin->tokens()->delete();
    }

    /**
     * Seed transfers in different states, including one overdue transfer.
     * Uses the TransferService so audit logs and notifications are generated
     * consistently with the application's business rules.
     */
    private function seedTransfers($users, $departments): void
    {
        $transfers = app(TransferService::class);
        $notifications = app(NotificationService::class);

        $fileByNumber = fn (string $number) => File::where('file_number', $number)->first();

        // 1. Pending transfer (not overdue).
        $pendingFile = $fileByNumber('REG/2026/001');
        if ($pendingFile && ! Transfer::where('file_id', $pendingFile->id)
            ->where('to_holder_user_id', $users['finance@example.com']->id)
            ->exists()) {
            $transfers->create($users['registry@example.com'], $pendingFile, [
                'to_department_id' => $departments['Finance']->id,
                'to_holder_user_id' => $users['finance@example.com']->id,
            ]);
        }

        // 2. Overdue transfer (due date in the past).
        $overdueFile = $fileByNumber('FIN/2026/002');
        if ($overdueFile && ! Transfer::where('file_id', $overdueFile->id)
            ->where('to_holder_user_id', $users['procurement@example.com']->id)
            ->exists()) {
            $transfer = $transfers->create($users['supervisor@example.com'], $overdueFile, [
                'to_department_id' => $departments['Procurement']->id,
                'to_holder_user_id' => $users['procurement@example.com']->id,
            ]);
            $transfer->update(['due_at' => now()->subDays(3)]);
            $notifications->notifyTransferOverdue($transfer->fresh());
        }

        // 3. Acknowledged transfer (moves confirmed custody to the destination).
        $ackFile = $fileByNumber('HR/2026/003');
        if ($ackFile && ! Transfer::where('file_id', $ackFile->id)
            ->where('to_holder_user_id', $users['legal@example.com']->id)
            ->exists()) {
            $transfer = $transfers->create($users['registry@example.com'], $ackFile, [
                'to_department_id' => $departments['Legal']->id,
                'to_holder_user_id' => $users['legal@example.com']->id,
            ]);
            $transfers->acknowledge($users['legal@example.com'], $transfer);
        }

        // 4. Rejected transfer (custody is never modified).
        $rejectedFile = $fileByNumber('PROC/2026/004');
        if ($rejectedFile && ! Transfer::where('file_id', $rejectedFile->id)
            ->where('to_holder_user_id', $users['finance@example.com']->id)
            ->exists()) {
            $transfer = $transfers->create($users['registry@example.com'], $rejectedFile, [
                'to_department_id' => $departments['Finance']->id,
                'to_holder_user_id' => $users['finance@example.com']->id,
            ]);
            $transfers->reject($users['finance@example.com'], $transfer);
        }
    }

    /**
     * Seed file issues in different states. Uses the FileIssueService so audit
     * logs and notifications are generated consistently.
     */
    private function seedIssues($users, $admin): void
    {
        $issues = app(FileIssueService::class);

        $fileByNumber = fn (string $number) => File::where('file_number', $number)->first();

        // 1. Open issue.
        $openFile = $fileByNumber('LEG/2026/005');
        if ($openFile && ! FileIssue::where('file_id', $openFile->id)
            ->where('issue_type', 'damage')
            ->exists()) {
            $issues->create($users['legal@example.com'], $openFile, [
                'issue_type' => 'damage',
                'description' => 'The physical file cover is torn and several pages show water damage.',
            ]);
        }

        // 2. In-progress issue.
        $inProgressFile = $fileByNumber('FIN/2026/007');
        if ($inProgressFile && ! FileIssue::where('file_id', $inProgressFile->id)
            ->where('issue_type', 'missing_document')
            ->exists()) {
            $issue = $issues->create($users['finance@example.com'], $inProgressFile, [
                'issue_type' => 'missing_document',
                'description' => 'One supporting document referenced in the file index is missing.',
            ]);
            $issues->updateStatus($users['supervisor@example.com'], $issue, FileIssue::STATUS_IN_PROGRESS);
        }

        // 3. Resolved issue.
        $resolvedFile = $fileByNumber('HR/2026/008');
        if ($resolvedFile && ! FileIssue::where('file_id', $resolvedFile->id)
            ->where('issue_type', 'misplaced')
            ->exists()) {
            $issue = $issues->create($users['hr@example.com'], $resolvedFile, [
                'issue_type' => 'misplaced',
                'description' => 'The file was temporarily misplaced during a desk move.',
            ]);
            $issues->updateStatus($users['supervisor@example.com'], $issue, FileIssue::STATUS_RESOLVED);
        }

        // 4. Dismissed issue.
        $dismissedFile = $fileByNumber('PROC/2026/009');
        if ($dismissedFile && ! FileIssue::where('file_id', $dismissedFile->id)
            ->where('issue_type', 'duplicate')
            ->exists()) {
            $issue = $issues->create($users['procurement@example.com'], $dismissedFile, [
                'issue_type' => 'duplicate',
                'description' => 'Reported as a duplicate record; verified as a distinct file.',
            ]);
            $issues->updateStatus($admin, $issue, FileIssue::STATUS_DISMISSED);
        }
    }

    /**
     * Record file_created audit events for the seeded files so the audit log
     * has meaningful history beyond transfer/issue activity.
     */
    private function seedFileAuditLogs($registryUser): void
    {
        $audit = app(AuditLogService::class);

        File::all()->each(function (File $file) use ($audit, $registryUser) {
            $exists = AuditLog::where('entity_type', File::class)
                ->where('entity_id', $file->id)
                ->where('action', 'file_created')
                ->exists();

            if (! $exists) {
                $audit->record($registryUser, 'file_created', File::class, $file->id, null, $file);
            }
        });
    }
}
