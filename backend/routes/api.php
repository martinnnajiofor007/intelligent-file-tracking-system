<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\FileCategoryController;
use App\Http\Controllers\Api\FileController;
use App\Http\Controllers\Api\FileIssueController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\TransferController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::get('/health', function (): JsonResponse {
    return response()->json([
        'status' => 'ok',
        'service' => config('app.name'),
        'environment' => app()->environment(),
    ]);
});

Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::patch('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::post('/auth/change-password', [AuthController::class, 'changePassword']);

    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::patch('/users/{user}', [UserController::class, 'update']);
    Route::patch('/users/{user}/password', [UserController::class, 'resetPassword']);

    Route::get('/departments', [DepartmentController::class, 'index']);
    Route::post('/departments', [DepartmentController::class, 'store']);
    Route::patch('/departments/{department}', [DepartmentController::class, 'update']);

    Route::get('/file-categories', [FileCategoryController::class, 'index']);
    Route::post('/file-categories', [FileCategoryController::class, 'store']);

    Route::get('/files', [FileController::class, 'index']);
    Route::post('/files', [FileController::class, 'store']);
    Route::get('/files/{file}', [FileController::class, 'show']);

    Route::get('/files/{file}/transfers', [TransferController::class, 'index']);
    Route::get('/transfers', [TransferController::class, 'indexAll']);
    Route::post('/transfers', [TransferController::class, 'store']);
    Route::get('/transfers/overdue', [TransferController::class, 'overdue']);
    Route::get('/transfers/{transfer}', [TransferController::class, 'show']);
    Route::post('/transfers/{transfer}/acknowledge', [TransferController::class, 'acknowledge']);
    Route::post('/transfers/{transfer}/reject', [TransferController::class, 'reject']);

    Route::get('/files/{file}/issues', [FileIssueController::class, 'index']);
    Route::post('/files/{file}/issues', [FileIssueController::class, 'store']);
    Route::get('/issues', [FileIssueController::class, 'indexAll']);
    Route::get('/issues/{issue}', [FileIssueController::class, 'show']);
    Route::patch('/issues/{issue}', [FileIssueController::class, 'update']);

    Route::get('/audit-logs', [AuditLogController::class, 'index']);
    Route::get('/files/{file}/audit-logs', [AuditLogController::class, 'fileAuditLogs']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::get('/notifications/{notification}', [NotificationController::class, 'show']);
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
});
