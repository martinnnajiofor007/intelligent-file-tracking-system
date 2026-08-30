<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ResetUserPasswordRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function __construct(private AuditLogService $audit)
    {
    }

    public function index(): JsonResponse
    {
        $users = User::with('department')
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => $this->serializeUser($user));

        return response()->json(['data' => $users]);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'password' => Hash::make($request->input('password')),
            'role' => $request->input('role'),
            'department_id' => $request->input('department_id'),
            'is_active' => true,
        ]);

        $this->audit->record(
            $request->user(),
            'user_created',
            User::class,
            $user->id,
            null,
            [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'department_id' => $user->department_id,
            ]
        );

        return response()->json([
            'data' => $this->serializeUser($user->load('department')),
        ], 201);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $before = [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'department_id' => $user->department_id,
            'is_active' => $user->is_active,
        ];

        $user->update([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'role' => $request->input('role'),
            'department_id' => $request->input('department_id'),
            'is_active' => $request->boolean('is_active', $user->is_active),
        ]);

        $this->audit->record(
            $request->user(),
            'user_updated',
            User::class,
            $user->id,
            $before,
            [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'department_id' => $user->department_id,
                'is_active' => $user->is_active,
            ]
        );

        return response()->json([
            'data' => $this->serializeUser($user->load('department')),
        ]);
    }

    public function resetPassword(ResetUserPasswordRequest $request, User $user): JsonResponse
    {
        $user->update([
            'password' => Hash::make($request->input('password')),
        ]);

        // Revoke the target user's existing sessions so they must sign in again.
        $user->tokens()->delete();

        $this->audit->record(
            $request->user(),
            'user_password_reset',
            User::class,
            $user->id,
            null,
            [
                'name' => $user->name,
                'email' => $user->email,
            ]
        );

        return response()->json([
            'message' => 'Password reset successfully.',
        ]);
    }

    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'is_active' => $user->is_active,
            'department' => $user->department ? [
                'id' => $user->department->id,
                'name' => $user->department->name,
            ] : null,
        ];
    }
}
