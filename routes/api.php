<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProjectController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SubmissionController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\SimilarityReportController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\RepositoryItemController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\AcademicSessionController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/departments', [DepartmentController::class, 'index']);
Route::get('/academic-sessions/active', [AcademicSessionController::class, 'active']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::get('/projects', [ProjectController::class, 'index']);
    Route::get('/projects/{project}', [ProjectController::class, 'show']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::put('/projects/{project}', [ProjectController::class, 'update']);

    Route::get('/submissions', [SubmissionController::class, 'index']);
    Route::get('/submissions/{submission}', [SubmissionController::class, 'show']);
    Route::post('/submissions', [SubmissionController::class, 'store']);

    Route::get('/feedback', [FeedbackController::class, 'index']);
    Route::get('/feedback/{feedback}', [FeedbackController::class, 'show']);
    Route::post('/feedback', [FeedbackController::class, 'store']);

    Route::get('/similarity-reports', [SimilarityReportController::class, 'index']);
    Route::get('/similarity-reports/{report}', [SimilarityReportController::class, 'show']);
    Route::delete('/similarity-reports/{report}', [SimilarityReportController::class, 'destroy']);

    Route::get('/approvals', [ApprovalController::class, 'index']);
    Route::get('/approvals/{approval}', [ApprovalController::class, 'show']);
    Route::post('/approvals', [ApprovalController::class, 'store']);
    Route::put('/approvals/{approval}', [ApprovalController::class, 'update']);

    Route::get('/activity-logs', [ActivityLogController::class, 'index']);

    Route::get('/repository-items', [RepositoryItemController::class, 'index']);
    Route::get('/repository-items/{item}', [RepositoryItemController::class, 'show']);
    Route::delete('/repository-items/{item}', [RepositoryItemController::class, 'destroy']);
});