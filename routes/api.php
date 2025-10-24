<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\BarangayController;
use App\Http\Controllers\Api\OwnersearchController;
use App\Http\Controllers\Api\PropertyController;
use App\Http\Controllers\Api\TaxDueController;
use App\Http\Controllers\Api\PenaltyPostingController;
use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;

// Public routes (no authentication required)
Route::post('/login', [AuthController::class, 'login']);

// TEMPORARILY DISABLED - Will use Assessor's authentication
// Route::middleware('auth:sanctum')->group(function () {
if (false) { // Authentication disabled for integration
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/register', [AuthController::class, 'register'])->middleware('role:admin');

    // Payment routes
    Route::get('/payments', [PaymentController::class, 'index']);
    Route::get('/payments/journal', [PaymentController::class, 'getJournal']);
    Route::get('/payments/{id}', [PaymentController::class, 'show']);
    Route::post('/payments', [PaymentController::class, 'store'])->middleware('role:admin,staff');

    // Barangay and search routes (read-only, all roles)
    Route::get('/barangays', [BarangayController::class, 'index']);
    Route::get('/ownersearch', [OwnersearchController::class, 'search']);
    Route::get('/properties', [PropertyController::class, 'index']);
    Route::get('/properties/search', [PropertyController::class, 'search']);

    // Tax due routes (read-only, all roles)
    Route::get('/tax-due/{localTin}', [TaxDueController::class, 'getTaxDue']);
    Route::get('/tax-due/{localTin}/{tdno}', [TaxDueController::class, 'getTaxDueByTdno']);
    Route::get('/get-tax-due/properties/{localTin}', [TaxDueController::class, 'getProperties']);
    Route::get('/tax-due/assessments/{localTin}', [TaxDueController::class, 'getAssessmentDetails']);
    
    // Tax due write operations (admin and staff only)
    Route::middleware('role:admin,staff')->group(function () {
        Route::post('/tax-due/initialize/{localTin}', [TaxDueController::class, 'initializeTaxpayerDebit']);
        Route::post('/tax-due/compute-pen/{localTin}', [TaxDueController::class, 'computePenaltyDiscount']);
        Route::delete('/tax-due/remove-pen/{localTin}', [TaxDueController::class, 'removePenaltyDiscount']);
        Route::post('/tax-due/add-credit', [TaxDueController::class, 'addTaxCredit']);
        Route::delete('/tax-due/remove-credits/{localTin}', [TaxDueController::class, 'removeCredits']);
    });
    
    // Penalty Posting routes (read for all, write for admin/staff)
    Route::get('/penalty', [PenaltyPostingController::class, 'getPenaltyRecords']);
    Route::post('/penalty/post', [PenaltyPostingController::class, 'postPenalties'])
        ->middleware('role:admin,staff')
        ->middleware('throttle:10,1'); // Rate limit: 10 requests per minute
}

// PUBLIC ROUTES (TEMPORARY - For Assessor Integration Testing)
// Remove middleware requirement until integrated with Assessor's auth
Route::get('/payments', [PaymentController::class, 'index']);
Route::get('/payments/journal', [PaymentController::class, 'getJournal']);
Route::get('/payments/{id}', [PaymentController::class, 'show']);
Route::post('/payments', [PaymentController::class, 'store']);

Route::get('/barangays', [BarangayController::class, 'index']);
Route::get('/ownersearch', [OwnersearchController::class, 'search']);
Route::get('/properties', [PropertyController::class, 'index']);
Route::get('/properties/search', [PropertyController::class, 'search']);

Route::get('/tax-due/{localTin}', [TaxDueController::class, 'getTaxDue']);
Route::get('/tax-due/{localTin}/{tdno}', [TaxDueController::class, 'getTaxDueByTdno']);
Route::get('/get-tax-due/properties/{localTin}', [TaxDueController::class, 'getProperties']);
Route::get('/tax-due/assessments/{localTin}', [TaxDueController::class, 'getAssessmentDetails']);

Route::post('/tax-due/initialize/{localTin}', [TaxDueController::class, 'initializeTaxpayerDebit']);
Route::post('/tax-due/compute-pen/{localTin}', [TaxDueController::class, 'computePenaltyDiscount']);
Route::delete('/tax-due/remove-pen/{localTin}', [TaxDueController::class, 'removePenaltyDiscount']);
Route::post('/tax-due/add-credit', [TaxDueController::class, 'addTaxCredit']);
Route::delete('/tax-due/remove-credits/{localTin}', [TaxDueController::class, 'removeCredits']);

Route::get('/penalty', [PenaltyPostingController::class, 'getPenaltyRecords']);
Route::post('/penalty/post', [PenaltyPostingController::class, 'postPenalties']);