<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class TaxComputationService
{
    /**
     * Calculate tax due for a specific taxpayer and tax year
     */
    public function calculateTaxDue($localTin, $taxYear = null)
    {
        $query = DB::table('postingjournal as pj')
            ->join('tpaccount as tp', 'pj.LOCAL_TIN', '=', 'tp.LOCAL_TIN')
            ->select(
                'pj.TAX_YEAR',
                'pj.TDNO',
                DB::raw('SUM(pj.RPT_DUE) as rpt_due'),
                DB::raw('SUM(pj.SEF_DUE) as sef_due'),
                DB::raw('SUM(pj.TOTAL_PAID) as total_paid'),
                DB::raw('SUM(pj.PENALTY) as penalty'),
                DB::raw('SUM(pj.DISCOUNT) as discount'),
                DB::raw('(SUM(pj.RPT_DUE + pj.SEF_DUE + pj.PENALTY - pj.DISCOUNT) - SUM(pj.TOTAL_PAID)) as balance')
            )
            ->where('pj.LOCAL_TIN', $localTin)
            ->where('tp.STATUS', 'ACTIVE');

        if ($taxYear) {
            $query->where('pj.TAX_YEAR', $taxYear);
        }

        return $query->groupBy('pj.TAX_YEAR', 'pj.TDNO')
            ->orderBy('pj.TAX_YEAR', 'desc')
            ->get();
    }

    /**
     * Calculate penalty for overdue payments
     */
    public function calculatePenalty($localTin, $taxYear, $dueDate = null)
    {
        $dueDate = $dueDate ?: $this->getTaxDueDate($taxYear);
        $daysOverdue = max(0, now()->diffInDays($dueDate, false));
        
        // Standard penalty rate: 2% per month or fraction thereof
        $penaltyRate = 0.02;
        $monthsOverdue = ceil($daysOverdue / 30);
        
        $taxDue = $this->calculateTaxDue($localTin, $taxYear);
        $baseAmount = $taxDue->sum('balance');
        
        return $baseAmount * $penaltyRate * $monthsOverdue;
    }

    /**
     * Get tax due date for a specific year
     */
    private function getTaxDueDate($taxYear)
    {
        // Typically, RPT is due on or before January 31 of the following year
        return "{$taxYear}-01-31";
    }

    /**
     * Process payment and update posting journal
     */
    public function processPayment($paymentData)
    {
        DB::beginTransaction();
        
        try {
            $paymentId = $this->getNextPaymentId();
            
            // Create payment record
            $payment = DB::table('payment')->insertGetId([
                'PAYMENT_ID' => $paymentId,
                'LOCAL_TIN' => $paymentData['LOCAL_TIN'],
                'PAYMENTDATE' => $paymentData['PAYMENTDATE'],
                'AMOUNT' => $paymentData['AMOUNT'],
                'RECEIPTNO' => $paymentData['RECEIPTNO'],
                'PAYMODE_CT' => $paymentData['PAYMODE_CT'] ?? 'CASH',
                'PAIDBY' => $paymentData['PAIDBY'],
                'REMARK' => $paymentData['REMARK'],
                'AFTYPE' => $paymentData['AFTYPE'] ?? 'AF56',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Process payment details
            foreach ($paymentData['details'] as $detail) {
                // Create payment detail
                DB::table('paymentdetail')->insert([
                    'PAYMENT_ID' => $paymentId,
                    'TDNO' => $detail['TDNO'],
                    'AF_ID' => $detail['AF_ID'] ?? null,
                    'DESCRIPTION' => $detail['DESCRIPTION'],
                    'QTY' => $detail['QTY'] ?? 1,
                    'UNITPRICE' => $detail['UNITPRICE'],
                    'AMOUNT' => $detail['AMOUNT'],
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                // Update posting journal
                DB::table('postingjournal')
                    ->where('LOCAL_TIN', $paymentData['LOCAL_TIN'])
                    ->where('TDNO', $detail['TDNO'])
                    ->where('TAX_YEAR', $detail['TAX_YEAR'] ?? date('Y'))
                    ->increment('TOTAL_PAID', $detail['AMOUNT']);
            }

            // Process cheque if provided
            if (!empty($paymentData['cheque'])) {
                DB::table('paymentcheque')->insert([
                    'PAYMENT_ID' => $paymentId,
                    'BANK' => $paymentData['cheque']['BANK'],
                    'CHEQUENO' => $paymentData['cheque']['CHEQUENO'],
                    'AMOUNT' => $paymentData['cheque']['AMOUNT'],
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            DB::commit();
            return $paymentId;

        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    /**
     * Get next payment ID
     */
    private function getNextPaymentId()
    {
        $lastPayment = DB::table('payment')->orderBy('PAYMENT_ID', 'desc')->first();
        return $lastPayment ? $lastPayment->PAYMENT_ID + 1 : 1;
    }

    /**
     * Generate receipt number
     */
    public function generateReceiptNumber($prefix = 'OR')
    {
        $year = date('Y');
        $lastReceipt = DB::table('payment')
            ->where('RECEIPTNO', 'like', "{$prefix}-{$year}-%")
            ->orderBy('RECEIPTNO', 'desc')
            ->first();

        if ($lastReceipt) {
            $lastNumber = (int) substr($lastReceipt->RECEIPTNO, -6);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return sprintf('%s-%s-%06d', $prefix, $year, $nextNumber);
    }

    /**
     * Get payment summary for a taxpayer
     */
    public function getPaymentSummary($localTin, $startDate = null, $endDate = null)
    {
        $query = DB::table('payment as p')
            ->join('taxpayer as t', 'p.LOCAL_TIN', '=', 't.LOCAL_TIN')
            ->select(
                'p.PAYMENT_ID',
                'p.RECEIPTNO',
                'p.PAYMENTDATE',
                'p.AMOUNT',
                'p.PAYMODE_CT',
                'p.PAIDBY',
                't.NAME as taxpayer_name'
            )
            ->where('p.LOCAL_TIN', $localTin);

        if ($startDate) {
            $query->where('p.PAYMENTDATE', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('p.PAYMENTDATE', '<=', $endDate);
        }

        return $query->orderBy('p.PAYMENTDATE', 'desc')->get();
    }
}

