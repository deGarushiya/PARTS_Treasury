<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\PenaltyDiscountService;

class TaxDueController extends Controller
{
    // TODO: Temporarily disabled service injection for debugging
    // protected $penaltyDiscountService;

    // public function __construct(PenaltyDiscountService $penaltyDiscountService)
    // {
    //     $this->penaltyDiscountService = $penaltyDiscountService;
    // }
    // 🔹 Fetch list of properties by LOCAL_TIN
public function getProperties($localTin)
{
    try {
        $properties = DB::table('propertyowner as po')
            ->join('rptassessment as ra', 'po.PROP_ID', '=', 'ra.PROP_ID')
            ->join('property as p', 'ra.PROP_ID', '=', 'p.PROP_ID')
            ->leftJoin('t_barangay as b', 'p.BARANGAY_CT', '=', 'b.CODE')
            ->leftJoin('t_propertykind as pk', 'p.PROPERTYKIND_CT', '=', 'pk.CODE')
            ->select(
                'ra.TDNO',
                'p.PINNO as PIN',
                'b.DESCRIPTION as BarangayName',
                'pk.DESCRIPTION as PropertyKind',
                'ra.ended_bv as expired'
            )
            ->where('po.LOCAL_TIN', $localTin)
            ->groupBy(
                'ra.TDNO',
                'p.PINNO',
                'b.DESCRIPTION',
                'pk.DESCRIPTION',
                'ra.ended_bv',
                'ra.PROP_ID',
                'ra.TAXTRANS_ID'
            )
            // expired first (ended_bv = 1), then by TD number
            ->orderByDesc('ra.ended_bv')
            ->orderBy('ra.TDNO')
            ->get();

        return response()->json($properties);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
}






    // 🔹 Fetch tax due summary by LOCAL_TIN
    public function getTaxDue($localTin)
    {
        try {
            $data = DB::table('postingjournal as pj')
                ->join('tpaccount as tp', 'pj.LOCAL_TIN', '=', 'tp.LOCAL_TIN')
                ->select(
                    'pj.TAX_YEAR',
                    DB::raw('SUM(pj.RPT_DUE) as rpt_due'),
                    DB::raw('SUM(pj.SEF_DUE) as sef_due'),
                    DB::raw('SUM(pj.TOTAL_PAID) as total_paid'),
                    DB::raw('(SUM(pj.RPT_DUE + pj.SEF_DUE) - SUM(pj.TOTAL_PAID)) as balance')
                )
                ->where('pj.LOCAL_TIN', $localTin)
                ->groupBy('pj.TAX_YEAR')
                ->orderBy('pj.TAX_YEAR', 'desc')
                ->get();

            if ($data->isEmpty()) {
                return response()->json([]);
            }

            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // 🔹 Initialize taxpayer debit (compute penalties/discounts)
    public function initializeTaxpayerDebit($localTin)
    {
        try {
            // Step 1: Delete existing penalty/discount records (like VB delete_pendisc)
            DB::table('tpaccount')
                ->where('LOCAL_TIN', $localTin)
                ->where('EARMARK_CT', 'OPN')
                ->whereIn('EVENTOBJECT_CT', ['PEN', 'DED'])
                ->delete();

            // Step 2: Compute and insert penalty/discount records (like VB compute_pendisc)
            // Get all postingjournal entries for this taxpayer
            $postingEntries = DB::table('postingjournal as pj')
                ->join('rptassessment as ra', 'pj.TDNO', '=', 'ra.TDNO')
                ->select(
                    'pj.LOCAL_TIN',
                    'pj.TAXYEAR',
                    'pj.TDNO',
                    'pj.PROP_ID',
                    'pj.POSTINGJOURNAL_ID',
                    'pj.RPTTAXDUE',
                    'pj.SEFTAXDUE',
                    DB::raw('99 as TAXPERIOD_CT') // Annual for now
                )
                ->where('pj.LOCAL_TIN', $localTin)
                ->where('pj.CANCELLED_BV', 0)
                ->whereRaw('pj.TAXYEAR >= ra.STARTYEAR') // Only from start year
                ->get();

            // Get max POSTING_ID to generate new IDs
            $maxPostingId = DB::table('tpaccount')->max('POSTING_ID') ?? 0;
            $currentPostingId = $maxPostingId + 1;

            // For now, insert ASS (Assessment) records without penalty/discount calculation
            // We'll implement full penalty/discount logic later
            foreach ($postingEntries as $entry) {
                // Check if ASS record already exists to avoid duplicates
                $exists = DB::table('tpaccount')
                    ->where('LOCAL_TIN', $entry->LOCAL_TIN)
                    ->where('TAXYEAR', $entry->TAXYEAR)
                    ->where('PROP_ID', $entry->PROP_ID)
                    ->where('JOURNALID', $entry->POSTINGJOURNAL_ID)
                    ->where('EVENTOBJECT_CT', 'ASS')
                    ->where('EARMARK_CT', 'OPN')
                    ->exists();

                if (!$exists) {
                    // Insert ASS record
                    DB::table('tpaccount')->insert([
                        'POSTING_ID' => $currentPostingId++,
                        'LOCAL_TIN' => $entry->LOCAL_TIN,
                        'TAXYEAR' => $entry->TAXYEAR,
                        'TAXPERIOD_CT' => $entry->TAXPERIOD_CT,
                        'PROP_ID' => $entry->PROP_ID,
                        'JOURNALID' => $entry->POSTINGJOURNAL_ID,
                        'EVENTOBJECT_CT' => 'ASS', // Assessment
                        'CASETYPE_CT' => 'ASS',
                        'DEBITAMOUNT' => $entry->RPTTAXDUE + $entry->SEFTAXDUE,
                        'CREDITAMOUNT' => 0,
                        'EARMARK_CT' => 'OPN',
                        'USERID' => 'system',
                        'TRANSDATE' => now(),
                        'CANCELLED_BV' => 0,
                        'VALUEDATE' => now(),
                        'MUNICIPAL_ID' => 3, // Default for Pangasinan municipalities
                    ]);
                }

                // TODO: Calculate and insert PEN/DED records based on posting date
            }

            return response()->json(['message' => 'Taxpayer debit initialized successfully', 'records_processed' => count($postingEntries)]);
        } catch (\Exception $e) {
            \Log::error('Error initializing taxpayer debit', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // 🔹 Fetch tax due per TD (per property) by LOCAL_TIN and TDNO
    public function getTaxDueByTdno($localTin, $tdno)
    {
        try {
            \Log::info("🔍 getTaxDueByTdno called", ['LOCAL_TIN' => $localTin, 'TDNO' => $tdno]);
            
            // Get dues from TPACCOUNT (like VB app - only show if TPACCOUNT records exist)
            // This ensures only initialized taxpayer debits are shown
            $baseDues = DB::table('tpaccount as tp')
                ->join('postingjournal as pj', function($join) {
                    $join->on('tp.PROP_ID', '=', 'pj.PROP_ID')
                         ->on('tp.TAXYEAR', '=', 'pj.TAXYEAR')
                         ->on('tp.JOURNALID', '=', 'pj.POSTINGJOURNAL_ID');
                })
                ->select(
                    'tp.TAXYEAR',
                    'pj.TDNO',
                    'tp.PROP_ID',
                    'tp.JOURNALID as POSTINGJOURNAL_ID',
                    DB::raw('SUM(CASE WHEN tp.EVENTOBJECT_CT = "ASS" THEN tp.DEBITAMOUNT ELSE 0 END) as amount_due'),
                    DB::raw('SUM(CASE WHEN tp.EVENTOBJECT_CT IN ("PEN", "DED") THEN tp.DEBITAMOUNT ELSE 0 END) as penalty_discount'),
                    DB::raw('SUM(CASE WHEN tp.EVENTOBJECT_CT IN ("TCR", "TDF") THEN tp.DEBITAMOUNT / 2 ELSE 0 END) as credits')
                )
                ->where('tp.LOCAL_TIN', $localTin)
                ->where('pj.TDNO', $tdno)
                ->where('tp.EARMARK_CT', 'OPN')
                ->groupBy('tp.TAXYEAR', 'pj.TDNO', 'tp.PROP_ID', 'tp.JOURNALID')
                ->orderBy('tp.TAXYEAR')
                ->get();
            
            \Log::info("📊 Query returned rows", ['count' => $baseDues->count()]);

            $result = [];

            foreach ($baseDues as $due) {
                $totalTaxDue = $due->amount_due + $due->penalty_discount - $due->credits;

                $result[] = [
                    'TAX_YEAR' => $due->TAXYEAR,
                    'TDNO' => $due->TDNO,
                    'amount_due' => round($due->amount_due, 2),
                    'penalty_discount' => round($due->penalty_discount, 2),
                    'credits' => round($due->credits, 2),
                    'total_tax_due' => round($totalTaxDue, 2),
                    'period' => 'YEARLY',
                    'amount' => round($totalTaxDue, 2),
                    'booking_reference' => null
                ];
            }

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get penalty/discount amount for a specific due from TPACCOUNT
     */
    private function getPenaltyDiscountForDue($localTin, $taxYear, $taxPeriod, $propId, $journalId)
    {
        $penaltyDiscount = DB::table('tpaccount')
            ->where('LOCAL_TIN', $localTin)
            ->where('TAXYEAR', $taxYear)
            ->where('TAXPERIOD_CT', $taxPeriod)
            ->where('PROP_ID', $propId)
            ->where('JOURNALID', $journalId)
            ->whereIn('EVENTOBJECT_CT', ['PEN', 'DED'])
            ->where('EARMARK_CT', 'OPN')
            ->sum('DEBITAMOUNT');

        return $penaltyDiscount ?: 0.00;
    }

    /**
     * Get period description from tax period code
     */
    private function getPeriodDescription($taxPeriod)
    {
        $periods = [
            '99' => 'YEARLY',
            '21' => '1ST BI-ANNUAL',
            '22' => '2ND BI-ANNUAL',
            '41' => '1ST QUARTER',
            '42' => '2ND QUARTER',
            '43' => '3RD QUARTER',
            '44' => '4TH QUARTER'
        ];

        return $periods[$taxPeriod] ?? 'YEARLY';
    }

    /**
     * 🔹 Fetch assessment details by LOCAL_TIN for Manual Debit page
     * Shows TD, Year, PIN, Land/Building values, Basic Tax, SEF, and Source
     * with color indicators for Open/Installment/Paid accounts
     */
    public function getAssessmentDetails($localTin)
    {
        try {
            \Log::info("🔍 Fetching assessment details for Manual Debit", ['LOCAL_TIN' => $localTin]);
            
            $assessments = DB::table('tpaccount as tp')
                ->join('postingjournal as pj', function($join) {
                    $join->on('tp.PROP_ID', '=', 'pj.PROP_ID')
                         ->on('tp.TAXYEAR', '=', 'pj.TAXYEAR')
                         ->on('tp.JOURNALID', '=', 'pj.POSTINGJOURNAL_ID');
                })
                ->join('rptassessment as ra', 'pj.TDNO', '=', 'ra.TDNO')
                ->join('property as p', 'ra.PROP_ID', '=', 'p.PROP_ID')
                ->select(
                    'pj.TDNO',
                    'tp.TAXYEAR as Year',
                    'p.PINNO as PIN',
                    'ra.LANDASSESSEDVALUE as Land',
                    'ra.BLDGASSESSEDVALUE as Improvements',
                    DB::raw('(ra.LANDASSESSEDVALUE + ra.BLDGASSESSEDVALUE) as Total'),
                    'pj.RPTTAXDUE as Basic',
                    'pj.SEFTAXDUE as SEF',
                    'tp.EVENTOBJECT_CT as Source',
                    'tp.EARMARK_CT as Status'
                )
                ->where('tp.LOCAL_TIN', $localTin)
                ->where('tp.EVENTOBJECT_CT', 'ASS')  // Only Assessment records
                ->groupBy(
                    'pj.TDNO', 
                    'tp.TAXYEAR', 
                    'p.PINNO', 
                    'ra.LANDASSESSEDVALUE', 
                    'ra.BLDGASSESSEDVALUE',
                    'pj.RPTTAXDUE',
                    'pj.SEFTAXDUE',
                    'tp.EVENTOBJECT_CT',
                    'tp.EARMARK_CT',
                    'ra.PROP_ID',
                    'pj.POSTINGJOURNAL_ID'
                )
                ->orderBy('pj.TDNO')
                ->orderByDesc('tp.TAXYEAR')
                ->get();

            \Log::info("📊 Assessment query returned rows", ['count' => $assessments->count()]);

            // Format results with color status
            $result = [];
            foreach ($assessments as $assessment) {
                // Determine color status based on EARMARK_CT
                $colorStatus = 'open';  // default white
                if ($assessment->Status === 'INS' || $assessment->Status === 'DBP') {
                    $colorStatus = 'installment';  // teal/green
                } elseif ($assessment->Status === 'PSD') {
                    $colorStatus = 'paid';  // gray
                }

                $result[] = [
                    'tdNo' => $assessment->TDNO,
                    'year' => $assessment->Year,
                    'pin' => $assessment->PIN,
                    'land' => round($assessment->Land, 2),
                    'improvements' => round($assessment->Improvements, 2),
                    'total' => round($assessment->Total, 2),
                    'basic' => round($assessment->Basic, 2),
                    'sef' => round($assessment->SEF, 2),
                    'source' => $assessment->Source,
                    'status' => $colorStatus
                ];
            }

            return response()->json($result);
        } catch (\Exception $e) {
            \Log::error('Error fetching assessment details', [
                'LOCAL_TIN' => $localTin,
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // 🔹 Compute PEN - Calculate penalties/discounts
    public function computePenaltyDiscount(Request $request, $localTin)
    {
        try {
            // Get posting date from request (default to current date)
            $postingDate = $request->input('posting_date', now());
            $postingYear = date('Y', strtotime($postingDate));
            $postingMonth = date('n', strtotime($postingDate));

            \Log::info('🧮 Computing PEN/DED', [
                'LOCAL_TIN' => $localTin,
                'posting_date' => $postingDate,
                'posting_year' => $postingYear,
                'posting_month' => $postingMonth
            ]);

            // Step 1: Delete existing PEN/DED records
            $deleted = DB::table('tpaccount')
                ->where('LOCAL_TIN', $localTin)
                ->where('EARMARK_CT', 'OPN')
                ->whereIn('EVENTOBJECT_CT', ['PEN', 'DED'])
                ->delete();

            \Log::info('🗑️ Deleted old PEN/DED records', ['count' => $deleted]);

            // Step 2: Get all ASS records for this taxpayer
            $assRecords = DB::table('tpaccount')
                ->where('LOCAL_TIN', $localTin)
                ->where('EVENTOBJECT_CT', 'ASS')
                ->where('EARMARK_CT', 'OPN')
                ->get();

            \Log::info('📋 Found ASS records', ['count' => $assRecords->count()]);

            $inserted = 0;
            $maxPostingId = DB::table('tpaccount')->max('POSTING_ID') ?? 0;
            $currentPostingId = $maxPostingId + 1;

            foreach ($assRecords as $ass) {
                // Determine if PEN or DED
                $eventObject = $this->determineEventObject(
                    $ass->TAXPERIOD_CT,
                    $ass->TAXYEAR,
                    $postingYear,
                    $postingMonth
                );

                \Log::info('📋 Processing ASS record', [
                    'TAXYEAR' => $ass->TAXYEAR,
                    'TAXPERIOD_CT' => $ass->TAXPERIOD_CT,
                    'DEBITAMOUNT' => $ass->DEBITAMOUNT,
                    'eventObject' => $eventObject
                ]);

                if ($eventObject === 'INV') {
                    \Log::info('⚠️ Skipping invalid event object');
                    continue; // Skip invalid records
                }

                // Calculate penalty/discount amount
                $amount = $this->calculatePenaltyDiscountAmount(
                    $ass->TAXPERIOD_CT,
                    $ass->TAXYEAR,
                    $eventObject,
                    $ass->DEBITAMOUNT,
                    $postingYear,
                    $postingMonth
                );

                \Log::info('💰 Calculated amount', [
                    'amount' => $amount,
                    'eventObject' => $eventObject
                ]);

                if ($amount == 0) {
                    \Log::info('⚠️ Skipping zero amount');
                    continue; // Skip if no penalty/discount
                }

                // Insert new PEN or DED record
                DB::table('tpaccount')->insert([
                    'POSTING_ID' => $currentPostingId++,
                    'REFPOSTINGID' => $ass->POSTING_ID,
                    'LOCAL_TIN' => $localTin,
                    'PROP_ID' => $ass->PROP_ID,
                    'TAXYEAR' => $ass->TAXYEAR,
                    'ITAXTYPE_CT' => $ass->ITAXTYPE_CT ?? null,
                    'MUNICIPAL_ID' => $ass->MUNICIPAL_ID,
                    'EVENTOBJECT_CT' => $eventObject,
                    'CASETYPE_CT' => $eventObject,
                    'DEBITAMOUNT' => round($amount, 2),
                    'VALUEDATE' => $postingDate,
                    'CREDITAMOUNT' => 0,
                    'EARMARK_CT' => 'OPN',
                    'TAXPERIOD_CT' => $ass->TAXPERIOD_CT,
                    'USERID' => 'system',
                    'TRANSDATE' => now(),
                    'CANCELLED_BV' => 0,
                    'JOURNALID' => $ass->JOURNALID,
                    'TAXTRANS_ID' => $ass->TAXTRANS_ID ?? null
                ]);

                $inserted++;
            }

            \Log::info('✅ Compute PEN complete', [
                'deleted' => $deleted,
                'inserted' => $inserted
            ]);

            return response()->json([
                'message' => 'Penalty/Discount computed successfully',
                'deleted' => $deleted,
                'inserted' => $inserted
            ]);

        } catch (\Exception $e) {
            \Log::error('❌ Error computing PEN/DED', [
                'LOCAL_TIN' => $localTin,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // Helper: Determine if PEN (penalty) or DED (discount)
    private function determineEventObject($taxPeriod, $taxYear, $postingYear, $postingMonth)
    {
        // If posting year is after tax year → PENALTY
        if ($postingYear > $taxYear) {
            return 'PEN';
        }

        // If posting year is before tax year → DISCOUNT
        if ($postingYear < $taxYear) {
            return 'DED';
        }

        // Same year - depends on period and month
        if ($taxPeriod == 99) { // Annual
            if ($postingMonth >= 4) {
                return 'PEN'; // After March = Penalty
            } else {
                return 'DED'; // Jan-Mar = Discount
            }
        } elseif ($taxPeriod == 21) { // First Semi-Annual
            if ($postingMonth >= 7) {
                return 'PEN'; // After June = Penalty
            } else {
                return 'DED'; // Before July = Discount
            }
        } elseif ($taxPeriod == 22) { // Second Semi-Annual
            return 'DED'; // Always discount
        } elseif ($taxPeriod == 41) { // First Quarter
            if ($postingMonth >= 4) {
                return 'PEN';
            } else {
                return 'DED';
            }
        } elseif ($taxPeriod == 42) { // Second Quarter
            if ($postingMonth >= 7) {
                return 'PEN';
            } else {
                return 'DED';
            }
        } elseif ($taxPeriod == 43) { // Third Quarter
            if ($postingMonth >= 10) {
                return 'PEN';
            } else {
                return 'DED';
            }
        } elseif ($taxPeriod == 44) { // Fourth Quarter
            return 'DED'; // Always discount
        }

        return 'INV'; // Invalid
    }

    // Helper: Calculate penalty/discount amount
    private function calculatePenaltyDiscountAmount($taxPeriod, $taxYear, $eventObject, $baseAmount, $postingYear, $postingMonth)
    {
        // Get penalty/discount rates from database
        if ($eventObject === 'PEN') {
            // Calculate penalty (monthly interest)
            // Note: t_penaltyinterestparam doesn't have YEARFROM/YEARTO, just get the first row
            $penaltyRate = DB::table('t_penaltyinterestparam')
                ->value('RATE');

            if (!$penaltyRate) {
                $penaltyRate = 0.02; // Default 2% per month
            }

            // Calculate months late
            $monthsLate = (($postingYear - $taxYear) * 12) + ($postingMonth - 3);
            if ($monthsLate < 1) $monthsLate = 1;

            return $baseAmount * $penaltyRate * $monthsLate;

        } elseif ($eventObject === 'DED') {
            // Calculate discount
            $discountRate = DB::table('t_discount')
                ->where('YEARFROM', '<=', $postingYear)
                ->where('YEARTO', '>=', $postingYear)
                ->where('DISCOUNTMONTH', $postingMonth)
                ->value('INTERESTRATE');

            if (!$discountRate) {
                $discountRate = 0; // No discount
            }

            return $baseAmount * $discountRate;
        }

        return 0;
    }

    // 🔹 Remove PEN - Delete penalty/discount records
    public function removePenaltyDiscount($localTin)
    {
        try {
            $deleted = DB::table('tpaccount')
                ->where('LOCAL_TIN', $localTin)
                ->where('EARMARK_CT', 'OPN')
                ->whereIn('EVENTOBJECT_CT', ['PEN', 'DED'])
                ->delete();

            \Log::info('🗑️ Removed PEN/DED records', [
                'LOCAL_TIN' => $localTin,
                'deleted' => $deleted
            ]);

            return response()->json([
                'message' => 'Penalty/Discount removed successfully',
                'deleted' => $deleted
            ]);

        } catch (\Exception $e) {
            \Log::error('❌ Error removing PEN/DED', [
                'LOCAL_TIN' => $localTin,
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // 🔹 Add Tax Credit - Add TCR record
    public function addTaxCredit(Request $request)
    {
        try {
            $localTin = $request->input('local_tin');
            $propId = $request->input('prop_id');
            $taxYear = $request->input('tax_year');
            $taxPeriod = $request->input('tax_period');
            $journalId = $request->input('journal_id');
            $creditAmount = $request->input('credit_amount');

            if (!$localTin || !$propId || !$taxYear || !$taxPeriod || !$journalId || !$creditAmount) {
                return response()->json(['error' => 'Missing required fields'], 400);
            }

            $maxPostingId = DB::table('tpaccount')->max('POSTING_ID') ?? 0;
            $currentPostingId = $maxPostingId + 1;

            DB::table('tpaccount')->insert([
                'POSTING_ID' => $currentPostingId,
                'LOCAL_TIN' => $localTin,
                'PROP_ID' => $propId,
                'TAXYEAR' => $taxYear,
                'TAXPERIOD_CT' => $taxPeriod,
                'JOURNALID' => $journalId,
                'EVENTOBJECT_CT' => 'TCR',
                'CASETYPE_CT' => 'TCR',
                'DEBITAMOUNT' => $creditAmount * 2, // Credits are doubled in TPACCOUNT
                'CREDITAMOUNT' => 0,
                'EARMARK_CT' => 'OPN',
                'USERID' => 'system',
                'TRANSDATE' => now(),
                'CANCELLED_BV' => 0,
                'VALUEDATE' => now(),
                'MUNICIPAL_ID' => 3
            ]);

            \Log::info('✅ Tax Credit added', [
                'LOCAL_TIN' => $localTin,
                'credit_amount' => $creditAmount
            ]);

            return response()->json([
                'message' => 'Tax credit added successfully',
                'credit_amount' => $creditAmount
            ]);

        } catch (\Exception $e) {
            \Log::error('❌ Error adding tax credit', [
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // 🔹 Remove Credits - Delete all TCR/TDF records
    public function removeCredits($localTin)
    {
        try {
            $deleted = DB::table('tpaccount')
                ->where('LOCAL_TIN', $localTin)
                ->where('EARMARK_CT', 'OPN')
                ->whereIn('EVENTOBJECT_CT', ['TCR', 'TDF'])
                ->delete();

            \Log::info('🗑️ Removed TCR/TDF records', [
                'LOCAL_TIN' => $localTin,
                'deleted' => $deleted
            ]);

            return response()->json([
                'message' => 'Tax credits removed successfully',
                'deleted' => $deleted
            ]);

        } catch (\Exception $e) {
            \Log::error('❌ Error removing credits', [
                'LOCAL_TIN' => $localTin,
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
