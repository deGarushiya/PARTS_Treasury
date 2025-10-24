<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class PenaltyDiscountService
{
    private $postingYear;
    private $postingMonth;
    private $currentDate;
    private $municipalId;

    public function __construct()
    {
        $this->currentDate = now();
        $this->postingYear = $this->currentDate->year;
        $this->postingMonth = $this->currentDate->month;
        $this->municipalId = 3; // Default municipal ID, should be configurable
    }

    /**
     * Delete existing PEN/DED entries from TPACCOUNT (same as VB.NET delete_pendisc)
     */
    public function deletePenaltyDiscount($localTin)
    {
        DB::table('tpaccount')
            ->where('LOCAL_TIN', $localTin)
            ->where('EARMARK_CT', 'OPN')
            ->whereIn('EVENTOBJECT_CT', ['PEN', 'DED'])
            ->delete();

        // Update JOURNALID where NULL (same as VB.NET logic)
        DB::statement("
            UPDATE tpaccount 
            SET JOURNALID = (
                SELECT TOP(1) POSTINGJOURNAL_ID 
                FROM postingjournal 
                WHERE PROP_ID = tpaccount.PROP_ID 
                AND TAXYEAR = tpaccount.TAXYEAR
            ) 
            WHERE JOURNALID IS NULL 
            AND LOCAL_TIN = ?
        ", [$localTin]);
    }

    /**
     * Compute penalty/discount and insert into TPACCOUNT (same as VB.NET compute_pendisc)
     */
    public function computePenaltyDiscount($localTin)
    {
        // Get base dues from postingjournal
        $baseDues = DB::table('postingjournal')
            ->where('LOCAL_TIN', $localTin)
            ->select('TAXYEAR', 'TAXPERIOD_CT', 'PROP_ID', 'JOURNALID', 'RPTTAXDUE', 'SEFTAXDUE')
            ->get();

        foreach ($baseDues as $due) {
            $eventObject = $this->eventObject($due->TAXPERIOD_CT, $due->TAXYEAR);
            
            if ($eventObject === 'PEN' || $eventObject === 'DED') {
                $penaltyDiscount = $this->dedPenValue(
                    $due->TAXPERIOD_CT, 
                    $due->TAXYEAR, 
                    $eventObject, 
                    $due->RPTTAXDUE + $due->SEFTAXDUE
                );

                // Insert into TPACCOUNT
                DB::table('tpaccount')->insert([
                    'LOCAL_TIN' => $localTin,
                    'TAXYEAR' => $due->TAXYEAR,
                    'TAXPERIOD_CT' => $due->TAXPERIOD_CT,
                    'PROP_ID' => $due->PROP_ID,
                    'JOURNALID' => $due->JOURNALID,
                    'EVENTOBJECT_CT' => $eventObject,
                    'CASETYPE_CT' => $eventObject,
                    'DEBITAMOUNT' => round($penaltyDiscount, 2),
                    'VALUEDATE' => $this->currentDate,
                    'CREDITAMOUNT' => 0,
                    'EARMARK_CT' => 'OPN',
                    'USERID' => 'system', // Should be current user
                    'TRANSDATE' => $this->currentDate,
                    'CANCELLED_BV' => false,
                    'MUNICIPAL_ID' => $this->municipalId
                ]);
            }
        }
    }

    /**
     * Determine event object (PEN or DED) based on tax period and year (same as VB.NET event_object)
     */
    private function eventObject($taxPeriod, $taxYear)
    {
        if ($this->postingYear > $taxYear) {
            return 'PEN'; // Penalty for late payment
        } elseif ($this->postingYear < $taxYear) {
            return 'DED'; // Discount for advance payment
        } else {
            // Same year - check month
            if ($this->postingMonth <= 3) {
                return 'DED'; // Early payment discount
            } else {
                return 'PEN'; // Late payment penalty
            }
        }
    }

    /**
     * Calculate penalty/discount value (same as VB.NET ded_penvalue)
     */
    private function dedPenValue($taxPeriod, $taxYear, $eventCt, $taxable)
    {
        if ($eventCt === 'DED') {
            return $this->calculateDiscount($taxYear, $taxPeriod, $taxable);
        } elseif ($eventCt === 'PEN') {
            return $this->calculatePenalty($taxYear, $taxPeriod, $taxable);
        }
        
        return 0.00;
    }

    /**
     * Calculate discount amount
     */
    private function calculateDiscount($taxYear, $taxPeriod, $taxable)
    {
        // Get discount rate based on year and period
        $discountRate = DB::table('t_discount')
            ->where('YEARFROM', '<=', $taxYear)
            ->where('YEARTO', '>=', $taxYear)
            ->where('DISCOUNTMONTH', '>=', 0)
            ->orderBy('DISCOUNTMONTH', 'asc')
            ->value('INTERESTRATE');

        if ($discountRate) {
            return -($taxable * $discountRate); // Negative for discount
        }
        
        return 0.00;
    }

    /**
     * Calculate penalty amount
     */
    private function calculatePenalty($taxYear, $taxPeriod, $taxable)
    {
        // Calculate months difference
        $monthsDiff = (($this->postingYear - $taxYear) * 12) + ($this->postingMonth - 1);
        
        if ($monthsDiff > 0) {
            // Get penalty rate
            $penaltyRate = DB::table('t_penaltyinterestparam')
                ->where('TAG', 'penalty')
                ->where('USEDBY', 'RPT')
                ->value('RATE');

            if ($penaltyRate) {
                $penaltyMonths = min($monthsDiff, 36); // Cap at 36 months
                return $taxable * $penaltyRate * $penaltyMonths;
            }
        }
        
        return 0.00;
    }

    /**
     * Get credits for a specific due
     */
    public function getCreditsForDue($localTin, $taxYear, $taxPeriod, $propId, $journalId)
    {
        $credits = DB::table('tpaccount')
            ->where('LOCAL_TIN', $localTin)
            ->where('TAXYEAR', $taxYear)
            ->where('TAXPERIOD_CT', $taxPeriod)
            ->where('PROP_ID', $propId)
            ->where('JOURNALID', $journalId)
            ->whereIn('EVENTOBJECT_CT', ['TCR', 'TDF']) // Tax Credit, Tax Debit
            ->where('EARMARK_CT', 'OPN') // Open earmark
            ->sum(DB::raw('DEBITAMOUNT / 2')); // VB.NET divides by 2

        return $credits ?: 0.00;
    }

    /**
     * Add Tax Credit (Button1 functionality)
     */
    public function addTaxCredit($localTin, $taxYear, $taxPeriod, $propId, $journalId, $amount)
    {
        DB::table('tpaccount')->insert([
            'LOCAL_TIN' => $localTin,
            'TAXYEAR' => $taxYear,
            'TAXPERIOD_CT' => $taxPeriod,
            'PROP_ID' => $propId,
            'JOURNALID' => $journalId,
            'EVENTOBJECT_CT' => 'TCR',
            'CASETYPE_CT' => 'TCR',
            'DEBITAMOUNT' => $amount * 2, // VB.NET multiplies by 2
            'VALUEDATE' => $this->currentDate,
            'CREDITAMOUNT' => 0,
            'EARMARK_CT' => 'OPN',
            'USERID' => 'system',
            'TRANSDATE' => $this->currentDate,
            'CANCELLED_BV' => false,
            'MUNICIPAL_ID' => $this->municipalId
        ]);
    }

    /**
     * Remove Tax Credit (Button4 functionality)
     */
    public function removeTaxCredit($localTin, $taxYear, $taxPeriod, $propId, $journalId)
    {
        DB::table('tpaccount')
            ->where('LOCAL_TIN', $localTin)
            ->where('TAXYEAR', $taxYear)
            ->where('TAXPERIOD_CT', $taxPeriod)
            ->where('PROP_ID', $propId)
            ->where('JOURNALID', $journalId)
            ->where('EVENTOBJECT_CT', 'TCR')
            ->where('EARMARK_CT', 'OPN')
            ->delete();
    }

    /**
     * Remove Penalty (Button5 functionality)
     */
    public function removePenalty($localTin, $taxYear, $taxPeriod, $propId, $journalId)
    {
        DB::table('tpaccount')
            ->where('LOCAL_TIN', $localTin)
            ->where('TAXYEAR', $taxYear)
            ->where('TAXPERIOD_CT', $taxPeriod)
            ->where('PROP_ID', $propId)
            ->where('JOURNALID', $journalId)
            ->where('EVENTOBJECT_CT', 'PEN')
            ->where('EARMARK_CT', 'OPN')
            ->delete();
    }
}
