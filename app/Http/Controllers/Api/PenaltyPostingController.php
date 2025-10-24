<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PenaltyPostingController extends Controller
{
    /**
     * Get unpaid tax records for penalty posting
     * Can filter by barangay or tax year
     */
    public function getPenaltyRecords(Request $request)
    {
        // Validate input
        $validated = $request->validate([
            'barangay' => 'nullable|string|max:100',
            'taxyear' => 'nullable|integer|min:1900|max:2100',
            'tdno' => 'nullable|string|max:50',
        ]);

        try {
            $barangay = $validated['barangay'] ?? null;
            $taxYear = $validated['taxyear'] ?? null;
            $tdno = $validated['tdno'] ?? null;

            Log::info('📋 Fetching penalty records', [
                'barangay' => $barangay,
                'taxyear' => $taxYear,
                'tdno' => $tdno
            ]);

            // FAST QUERY: Get records from postingjournal + tpaccount
            // VB app's subquery approach is too slow, using optimized version instead
            $query = DB::table('postingjournal as pj')
                ->join('tpaccount as tp', function($join) {
                    $join->on('tp.TAXTRANS_ID', '=', 'pj.TAXTRANS_ID')
                         ->on('tp.TAXYEAR', '=', 'pj.TAXYEAR');
                })
                ->where('tp.EARMARK_CT', 'OPN')
                ->whereNotIn('tp.EVENTOBJECT_CT', ['TDF', 'TCR', 'PEN', 'DED']);

            // Apply barangay filter
            if ($barangay) {
                $query->join('property as p', 'pj.PROP_ID', '=', 'p.PROP_ID')
                      ->join('t_barangay as b', 'p.BARANGAY_CT', '=', 'b.CODE')
                      ->where('b.DESCRIPTION', $barangay);
            }

            // Apply TDNO filter
            if ($tdno) {
                $query->where('pj.TDNO', $tdno);
            }

            // Apply tax year filter
            if ($taxYear) {
                $query->where('tp.TAXYEAR', $taxYear);
            }

            $query->select(
                'pj.TDNO',
                'tp.TAXYEAR',
                DB::raw('MAX(tp.LOCAL_TIN) as LOCAL_TIN'),
                DB::raw('MAX(tp.TAXTRANS_ID) as TAXTRANS_ID'),
                DB::raw('MAX(pj.PROP_ID) as PROP_ID'),
                DB::raw('\'\' as OWNERNAME'),
                DB::raw('\'\' as barangay'),
                DB::raw('\'OPEN\' as STATUS')
            )
            ->groupBy('pj.TDNO', 'tp.TAXYEAR');

            $startTime = microtime(true);
            
            // Log the SQL query for debugging
            $sql = $query->toSql();
            $bindings = $query->getBindings();
            Log::info('📝 Fast Query', ['sql' => $sql, 'bindings' => $bindings]);
            
            $results = $query->orderBy('pj.TDNO')->orderBy('tp.TAXYEAR')->get();
            $queryTime = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('✅ Found penalty records', [
                'count' => $results->count(),
                'query_time_ms' => $queryTime
            ]);

            return response()->json($results);

        } catch (\Exception $e) {
            Log::error('❌ Error fetching penalty records: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Post penalties for selected records
     * Processes each tax record and computes penalties/discounts
     */
    public function postPenalties(Request $request)
    {
        // Validate input
        $validated = $request->validate([
            'asOfDate' => 'required|string',
            'records' => 'required|array|min:1',
            'records.*.TAXTRANS_ID' => 'required',
            'records.*.LOCAL_TIN' => 'required|string|max:20',
            'records.*.PROP_ID' => 'required',
            'records.*.TAXYEAR' => 'required|integer|min:1900|max:2100',
        ]);

        try {
            $records = $validated['records'];
            $asOfDate = $validated['asOfDate'];

            Log::info('🚀 Starting penalty posting', [
                'record_count' => count($records),
                'as_of_date' => $asOfDate
            ]);

            $processedCount = 0;
            $errors = [];

            // Load penalty and discount rates once (like loaddiscpen_rates in VB)
            $penaltyRate = $this->getPenaltyRate();
            $discountRates = $this->getDiscountRates($asOfDate);

            // SUPER OPTIMIZATION: Process entire batch at once!
            try {
                $this->processBatchPenalties($records, $asOfDate, $penaltyRate, $discountRates);
                $processedCount += count($records);
            } catch (\Exception $e) {
                Log::error("❌ Error processing batch: " . $e->getMessage());
                // Fallback to individual processing if batch fails
                foreach ($records as $index => $record) {
                    try {
                        $taxtransId = $record['TAXTRANS_ID'];
                        $taxYear = $record['TAXYEAR'];
                        $localTin = $record['LOCAL_TIN'];
                        $propId = $record['PROP_ID'];

                        // Delete old PEN/DED
                        DB::table('tpaccount')
                            ->where('EARMARK_CT', 'OPN')
                            ->whereIn('EVENTOBJECT_CT', ['PEN', 'DED'])
                            ->where('TAXTRANS_ID', $taxtransId)
                            ->where('TAXYEAR', $taxYear)
                            ->delete();

                        // Compute and insert new PEN/DED records
                        $this->computePenaltyDiscount($taxtransId, $taxYear, $localTin, $propId, $asOfDate, $penaltyRate, $discountRates);

                        $processedCount++;

                    } catch (\Exception $e2) {
                        $errors[] = [
                            'record' => $record,
                            'error' => $e2->getMessage()
                        ];
                        Log::error("❌ Error processing record: " . $e2->getMessage(), ['record' => $record]);
                    }
                }
            }

            Log::info('✅ Penalty posting completed', [
                'processed' => $processedCount,
                'errors' => count($errors)
            ]);

            return response()->json([
                'success' => true,
                'processed' => $processedCount,
                'total' => count($records),
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Fatal error in penalty posting: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * SUPER OPTIMIZED: Process entire batch of records at once
     * Minimizes database queries by fetching all data upfront
     */
    private function processBatchPenalties($records, $asOfDate, $penaltyRate, $discountRates)
    {
        if (empty($records)) {
            return;
        }

        // Step 1: Bulk delete all PEN/DED records for this batch
        $taxtransIds = array_column($records, 'TAXTRANS_ID');
        DB::table('tpaccount')
            ->where('EARMARK_CT', 'OPN')
            ->whereIn('EVENTOBJECT_CT', ['PEN', 'DED'])
            ->whereIn('TAXTRANS_ID', $taxtransIds)
            ->delete();

        // Step 2: Fetch ALL tpaccount records for this batch at once
        $allTpRecords = DB::table('tpaccount')
            ->whereIn('TAXTRANS_ID', $taxtransIds)
            ->where('EARMARK_CT', 'OPN')
            ->whereNotIn('EVENTOBJECT_CT', ['TDF', 'TCR', 'PEN', 'DED'])
            ->get()
            ->groupBy(function($item) {
                return $item->TAXTRANS_ID . '-' . $item->TAXYEAR;
            });

        // Step 3: Fetch ALL credits for these properties at once
        $propIds = array_unique(array_column($records, 'PROP_ID'));
        $taxYears = array_unique(array_column($records, 'TAXYEAR'));
        
        $allCredits = DB::table('tpaccount')
            ->whereIn('EVENTOBJECT_CT', ['TCR', 'TDF'])
            ->where('EARMARK_CT', 'OPN')
            ->whereIn('TAXYEAR', $taxYears)
            ->whereIn('PROP_ID', $propIds)
            ->select('PROP_ID', 'TAXYEAR', 'TAXPERIOD_CT', DB::raw('SUM(DEBITAMOUNT) / 2 as credits'))
            ->groupBy('PROP_ID', 'TAXYEAR', 'TAXPERIOD_CT')
            ->get()
            ->keyBy(function($item) {
                return $item->PROP_ID . '-' . $item->TAXYEAR . '-' . $item->TAXPERIOD_CT;
            });

        // Step 4: Get max POSTING_ID once
        $maxPostingId = DB::table('tpaccount')->max('POSTING_ID') ?? 0;
        $currentPostingId = $maxPostingId + 1;

        // Step 5: Prepare bulk insert array
        $recordsToInsert = [];
        $now = now();

        foreach ($records as $record) {
            $key = $record['TAXTRANS_ID'] . '-' . $record['TAXYEAR'];
            $tpRecords = $allTpRecords->get($key, collect());

            foreach ($tpRecords as $tpRecord) {
                // Get pre-fetched credits
                $creditKey = $record['PROP_ID'] . '-' . $record['TAXYEAR'] . '-' . $tpRecord->TAXPERIOD_CT;
                $credits = $allCredits->get($creditKey)->credits ?? 0;

                // Determine event object (PEN or DED)
                $eventObject = $this->determineEventObject($asOfDate, $record['TAXYEAR'], $tpRecord->TAXPERIOD_CT);
                
                if (!$eventObject) {
                    continue;
                }

                // Calculate penalty/discount amount
                $amount = $this->calculatePenaltyDiscountAmount(
                    $asOfDate,
                    $record['TAXYEAR'],
                    $tpRecord->TAXPERIOD_CT,
                    $eventObject,
                    $tpRecord->DEBITAMOUNT + $credits,
                    $penaltyRate,
                    $discountRates
                );

                // Add to bulk insert array
                if ($amount > 0) {
                    $recordsToInsert[] = [
                        'POSTING_ID' => $currentPostingId++,
                        'TAXTRANS_ID' => $record['TAXTRANS_ID'],
                        'REFPOSTINGID' => $tpRecord->POSTING_ID,
                        'LOCAL_TIN' => $record['LOCAL_TIN'],
                        'PROP_ID' => $record['PROP_ID'],
                        'TAXYEAR' => $record['TAXYEAR'],
                        'ITAXTYPE_CT' => $tpRecord->ITAXTYPE_CT,
                        'MUNICIPAL_ID' => 1,
                        'EVENTOBJECT_CT' => $eventObject,
                        'CASETYPE_CT' => $eventObject,
                        'DEBITAMOUNT' => round($amount, 2),
                        'VALUEDATE' => $now,
                        'CREDITAMOUNT' => 0,
                        'EARMARK_CT' => 'OPN',
                        'TAXPERIOD_CT' => $tpRecord->TAXPERIOD_CT,
                        'USERID' => 'SYSTEM',
                        'TRANSDATE' => $now,
                        'CANCELLED_BV' => 0,
                    ];
                }
            }
        }

        // Step 6: ONE massive bulk insert for all records!
        if (!empty($recordsToInsert)) {
            // Insert in chunks of 500 to avoid MySQL packet size limits
            foreach (array_chunk($recordsToInsert, 500) as $chunk) {
                DB::table('tpaccount')->insert($chunk);
            }
        }
    }

    /**
     * Delete existing PEN/DED records for a tax transaction
     * Equivalent to delete_pendisc in VB
     */
    private function deletePenaltyDiscount($taxtransId, $taxYear)
    {
        DB::table('tpaccount')
            ->where('EARMARK_CT', 'OPN')
            ->whereIn('EVENTOBJECT_CT', ['PEN', 'DED'])
            ->where('TAXTRANS_ID', $taxtransId)
            ->where('TAXYEAR', $taxYear)
            ->delete();
    }

    /**
     * Get penalty rate from t_penaltyinterestparam table
     * Gets first row like VB app: Rows(0).Item("RATE")
     */
    private function getPenaltyRate()
    {
        $rate = DB::table('t_penaltyinterestparam')
            ->orderBy(DB::raw('1')) // Just get first row
            ->value('RATE');
        
        return $rate ?? 0.02; // Default to 2% if not found
    }

    /**
     * Get discount rates from t_discount table for the given year
     */
    private function getDiscountRates($asOfDate)
    {
        // Parse year from YYYY-MM format
        $year = intval(substr($asOfDate, 0, 4));

        $rates = [
            'disc_annual_advance' => 0,
            'disc_annual1' => 0,
            'disc_annual2' => 0,
            'disc_annual3' => 0,
            'disc_qtr_promt' => 0,
            'disc_qtr_advance' => 0,
        ];

        // Load discount rates (matching VB logic)
        $discounts = DB::table('t_discount')
            ->where('YEARFROM', '<=', $year)
            ->where('YEARTO', '>=', $year)
            ->get();

        foreach ($discounts as $disc) {
            switch ($disc->DISCOUNTMONTH) {
                case 0: $rates['disc_annual_advance'] = $disc->INTERESTRATE; break;
                case 1: $rates['disc_annual1'] = $disc->INTERESTRATE; break;
                case 2: $rates['disc_annual2'] = $disc->INTERESTRATE; break;
                case 3: $rates['disc_annual3'] = $disc->INTERESTRATE; break;
                case 40: $rates['disc_qtr_promt'] = $disc->INTERESTRATE; break;
                case 41: $rates['disc_qtr_advance'] = $disc->INTERESTRATE; break;
            }
        }

        return $rates;
    }

    /**
     * Compute and insert penalty/discount records
     * Equivalent to compute_pendisc in VB
     * OPTIMIZED: Batch inserts for speed
     */
    private function computePenaltyDiscount($taxtransId, $taxYear, $localTin, $propId, $asOfDate, $penaltyRate, $discountRates)
    {
        // Get max POSTING_ID to generate new IDs (like initializeTaxpayerDebit)
        $maxPostingId = DB::table('tpaccount')->max('POSTING_ID') ?? 0;
        $currentPostingId = $maxPostingId + 1;

        // Get all records for this tax transaction (excluding TDF, TCR, PEN, DED like VB app)
        $tpaccountRecords = DB::table('tpaccount')
            ->where('TAXTRANS_ID', $taxtransId)
            ->where('TAXYEAR', $taxYear)
            ->where('EARMARK_CT', 'OPN')
            ->whereNotIn('EVENTOBJECT_CT', ['TDF', 'TCR', 'PEN', 'DED'])
            ->get();

        if ($tpaccountRecords->isEmpty()) {
            return;
        }

        // Pre-fetch ALL credits for this property/year (bulk query)
        $allCredits = DB::table('tpaccount')
            ->whereIn('EVENTOBJECT_CT', ['TCR', 'TDF'])
            ->where('EARMARK_CT', 'OPN')
            ->where('TAXYEAR', $taxYear)
            ->where('PROP_ID', $propId)
            ->select('TAXPERIOD_CT', DB::raw('SUM(DEBITAMOUNT) / 2 as credits'))
            ->groupBy('TAXPERIOD_CT')
            ->pluck('credits', 'TAXPERIOD_CT');

        // Prepare bulk insert array
        $recordsToInsert = [];
        $now = now();

        foreach ($tpaccountRecords as $record) {
            // Get pre-calculated credits
            $credits = $allCredits[$record->TAXPERIOD_CT] ?? 0;

            // Determine event object (PEN or DED) based on date
            $eventObject = $this->determineEventObject($asOfDate, $taxYear, $record->TAXPERIOD_CT);
            
            if (!$eventObject) {
                continue;
            }

            // Calculate penalty/discount amount
            $amount = $this->calculatePenaltyDiscountAmount(
                $asOfDate,
                $taxYear,
                $record->TAXPERIOD_CT,
                $eventObject,
                $record->DEBITAMOUNT + $credits,
                $penaltyRate,
                $discountRates
            );

            // Add to bulk insert array
            if ($amount > 0) {
                $recordsToInsert[] = [
                    'POSTING_ID' => $currentPostingId++,
                    'TAXTRANS_ID' => $taxtransId,
                    'REFPOSTINGID' => $record->POSTING_ID,
                    'LOCAL_TIN' => $localTin,
                    'PROP_ID' => $propId,
                    'TAXYEAR' => $taxYear,
                    'ITAXTYPE_CT' => $record->ITAXTYPE_CT,
                    'MUNICIPAL_ID' => 1,
                    'EVENTOBJECT_CT' => $eventObject,
                    'CASETYPE_CT' => $eventObject,
                    'DEBITAMOUNT' => round($amount, 2),
                    'VALUEDATE' => $now,
                    'CREDITAMOUNT' => 0,
                    'EARMARK_CT' => 'OPN',
                    'TAXPERIOD_CT' => $record->TAXPERIOD_CT,
                    'USERID' => 'SYSTEM',
                    'TRANSDATE' => $now,
                    'CANCELLED_BV' => 0,
                ];
            }
        }

        // Bulk insert all records at once!
        if (!empty($recordsToInsert)) {
            DB::table('tpaccount')->insert($recordsToInsert);
        }
    }

    /**
     * Determine if record should have PEN (penalty) or DED (discount)
     * Based on the "As of" date compared to tax year
     */
    private function determineEventObject($asOfDate, $taxYear, $taxPeriod)
    {
        $year = intval(substr($asOfDate, 0, 4));
        $month = intval(substr($asOfDate, 5, 2));

        // If current year > tax year, apply penalty
        if ($year > $taxYear) {
            return 'PEN';
        }

        // If current year < tax year, apply discount (advance payment)
        if ($year < $taxYear) {
            if ($taxPeriod == 41) { // Quarterly advance
                return 'DED';
            }
        }

        // Same year - check month and period for discounts
        if ($year == $taxYear) {
            // Annual payment logic (taxperiod 99)
            if ($taxPeriod == 99) {
                if ($month < 4) {
                    return 'DED'; // Discount for early payment
                }
                // No penalty/discount if paid in April or later
                return null;
            }

            // Quarterly payment logic
            // This would need more complex logic based on quarters
            // For now, simplified version
        }

        return null;
    }

    /**
     * Calculate the actual penalty or discount amount
     */
    private function calculatePenaltyDiscountAmount($asOfDate, $taxYear, $taxPeriod, $eventObject, $baseAmount, $penaltyRate, $discountRates)
    {
        $year = intval(substr($asOfDate, 0, 4));
        $month = intval(substr($asOfDate, 5, 2));

        if ($eventObject == 'PEN') {
            // Calculate penalty based on months overdue
            $monthsOverdue = ($year - $taxYear) * 12;
            if ($monthsOverdue > 0) {
                return $baseAmount * $penaltyRate * $monthsOverdue;
            }
        } elseif ($eventObject == 'DED') {
            // Apply discount based on payment timing
            if ($taxPeriod == 99) { // Annual
                if ($year < $taxYear) {
                    return $baseAmount * $discountRates['disc_annual_advance'];
                } elseif ($month == 1) {
                    return $baseAmount * $discountRates['disc_annual1'];
                } elseif ($month == 2) {
                    return $baseAmount * $discountRates['disc_annual2'];
                } elseif ($month == 3) {
                    return $baseAmount * $discountRates['disc_annual3'];
                }
            } elseif ($taxPeriod == 41) { // Quarterly advance
                return $baseAmount * $discountRates['disc_qtr_advance'];
            }
        }

        return 0;
    }
}

