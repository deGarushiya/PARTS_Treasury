<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\BarangayController;
use App\Http\Controllers\Api\OwnersearchController;
use App\Http\Controllers\Api\PropertyController;
use App\Http\Controllers\Api\TaxDueController;
use App\Http\Controllers\Api\PenaltyPostingController;

// Route::middleware(['check.dev'])->group(function () {
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
    
    // Assessment details for Manual Debit page
    Route::get('/tax-due/assessments/{localTin}', [TaxDueController::class, 'getAssessmentDetails']);
    
    // Initialize taxpayer debit (compute penalties/discounts)
    Route::post('/tax-due/initialize/{localTin}', [TaxDueController::class, 'initializeTaxpayerDebit']);
    
    // Button functionality endpoints
    Route::post('/tax-due/compute-pen/{localTin}', [TaxDueController::class, 'computePenaltyDiscount']);
    Route::delete('/tax-due/remove-pen/{localTin}', [TaxDueController::class, 'removePenaltyDiscount']);
    Route::post('/tax-due/add-credit', [TaxDueController::class, 'addTaxCredit']);
    Route::delete('/tax-due/remove-credits/{localTin}', [TaxDueController::class, 'removeCredits']);
    
    // Penalty Posting routes
    Route::get('/penalty', [PenaltyPostingController::class, 'getPenaltyRecords']);
    Route::post('/penalty/post', [PenaltyPostingController::class, 'postPenalties']);

// });



use Illuminate\Http\Request;

Route::middleware(['check.dev'])->get('/middleware-test', function (Request $request) {
    return response()->json(['message' => 'Middleware working']);
});