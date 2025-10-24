<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\TaxComputationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    protected $taxComputationService;

    public function __construct(TaxComputationService $taxComputationService)
    {
        $this->taxComputationService = $taxComputationService;
    }
    /**
     * Display a listing of the payments with details, cheque, and taxpayer.
     */
    public function index()
    {
        $payments = Payment::with(['details', 'cheque', 'taxpayer'])->get();
        return response()->json($payments);
    }

    /**
     * Display a specific payment by ID.
     */
    public function show($id)
    {
        $payment = Payment::with(['details', 'cheque', 'taxpayer'])->findOrFail($id);
        return response()->json($payment);
    }

    /**
     * Store a newly created payment with optional details and cheque.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'LOCAL_TIN' => 'required|string',
            'PAYMENTDATE' => 'required|date',
            'AMOUNT' => 'required|numeric',
            'RECEIPTNO' => 'required|string',
            'PAYMODE_CT' => 'nullable|string',
            'PAIDBY' => 'nullable|string',
            'REMARK' => 'nullable|string',
            'AFTYPE' => 'nullable|string',
            'details' => 'nullable|array',
            'cheque' => 'nullable|array'
        ]);

        try {
            // Generate receipt number if not provided
            if (empty($validated['RECEIPTNO'])) {
                $validated['RECEIPTNO'] = $this->taxComputationService->generateReceiptNumber();
            }

            // Process payment using the service
            $paymentId = $this->taxComputationService->processPayment($validated);

            return response()->json([
                'message' => 'Payment created successfully',
                'payment_id' => $paymentId,
                'receipt_no' => $validated['RECEIPTNO']
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    /**
     * Get payment journal for display
     */
    public function getJournal(Request $request)
    {
        $payments = Payment::with(['details', 'cheque', 'taxpayer'])
            ->orderBy('PAYMENTDATE', 'desc')
            ->orderBy('PAYMENT_ID', 'desc')
            ->get();

        return response()->json($payments);
    }
}
