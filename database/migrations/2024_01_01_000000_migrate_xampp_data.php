<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class MigrateXamppData extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        echo "Starting data migration from XAMPP MySQL database...\n";
        
        // This migration will help you import data from your XAMPP MySQL database
        // You'll need to modify the table names and column mappings based on your actual XAMPP database structure
        
        $this->migrateTaxpayers();
        $this->migrateProperties();
        $this->migratePostingJournal();
        $this->migratePayments();
        $this->migrateReferenceData();
        
        echo "XAMPP data migration completed!\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        // Clear imported data if needed
        DB::table('payment')->truncate();
        DB::table('paymentdetail')->truncate();
        DB::table('paymentcheque')->truncate();
        DB::table('postingjournal')->truncate();
        DB::table('rptassessment')->truncate();
        DB::table('property')->truncate();
        DB::table('taxpayer')->truncate();
    }

    private function migrateTaxpayers()
    {
        echo "Migrating taxpayers from XAMPP...\n";
        
        try {
            // Connect to your XAMPP database and get taxpayers
            // Modify the connection name and table name based on your XAMPP setup
            $xamppTaxpayers = DB::connection('xampp')->table('TAXPAYER')->get();
            
            $migratedCount = 0;
            foreach ($xamppTaxpayers as $taxpayer) {
                // Map XAMPP columns to Laravel columns
                DB::table('taxpayer')->updateOrInsert(
                    ['LOCAL_TIN' => $taxpayer->LOCAL_TIN],
                    [
                        'LOCAL_TIN' => $taxpayer->LOCAL_TIN,
                        'NAME' => $taxpayer->OWNERNAME ?? $taxpayer->NAME ?? '',
                        'ADDRESS' => $taxpayer->ADDRESS ?? '',
                        'BARANGAY' => $taxpayer->BARANGAY ?? '',
                        'MUNICIPALITY' => $taxpayer->MUNICIPALITY ?? '',
                        'PROVINCE' => $taxpayer->PROVINCE ?? '',
                        'ZIPCODE' => $taxpayer->ZIPCODE ?? '',
                        'CONTACTNO' => $taxpayer->CONTACTNO ?? $taxpayer->CONTACT_NO ?? '',
                        'EMAIL' => $taxpayer->EMAIL ?? '',
                        'created_at' => now(),
                        'updated_at' => now()
                    ]
                );
                $migratedCount++;
            }
            
            echo "✓ Migrated {$migratedCount} taxpayers\n";
            
        } catch (\Exception $e) {
            echo "✗ Error migrating taxpayers: " . $e->getMessage() . "\n";
            echo "Please check your XAMPP database connection settings\n";
        }
    }

    private function migrateProperties()
    {
        echo "Migrating properties from XAMPP...\n";
        
        try {
            // Migrate PROPERTY table
            $properties = DB::connection('xampp')->table('PROPERTY')->get();
            $migratedCount = 0;
            
            foreach ($properties as $property) {
                DB::table('property')->updateOrInsert(
                    ['PROP_ID' => $property->PROP_ID],
                    [
                        'PROP_ID' => $property->PROP_ID,
                        'PINNO' => $property->PINNO ?? '',
                        'CADASTRALLOTNO' => $property->CADASTRALLOTNO ?? '',
                        'CERTIFICATETITLENO' => $property->CERTIFICATETITLENO ?? '',
                        'BARANGAY_CT' => $property->BARANGAY_CT ?? '',
                        'PROPERTYKIND_CT' => $property->PROPERTYKIND_CT ?? '',
                        'USERID' => $property->USERID ?? '',
                        'TRANSDATE' => $property->TRANSDATE ?? now(),
                        'EXPIRED_BV' => $property->EXPIRED_BV ?? 0,
                        'PROPERTYREF_ID' => $property->PROPERTYREF_ID ?? null,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]
                );
                $migratedCount++;
            }
            
            echo "✓ Migrated {$migratedCount} properties\n";
            
            // Migrate RPTASSESSMENT table
            $assessments = DB::connection('xampp')->table('RPTASSESSMENT')->get();
            $assessmentCount = 0;
            
            foreach ($assessments as $assessment) {
                DB::table('rptassessment')->updateOrInsert(
                    ['TAXTRANS_ID' => $assessment->TAXTRANS_ID],
                    [
                        'TAXTRANS_ID' => $assessment->TAXTRANS_ID,
                        'PROP_ID' => $assessment->PROP_ID,
                        'TDNO' => $assessment->TDNO ?? '',
                        'ASSESSED_VALUE' => $assessment->ASSESSED_VALUE ?? 0,
                        'TAX_DUE' => $assessment->TAX_DUE ?? 0,
                        'CANCELLATIONDATE' => $assessment->CANCELLATIONDATE ?? null,
                        'ANNOTATION' => $assessment->ANNOTATION ?? null,
                        'MEMORANDA' => $assessment->MEMORANDA ?? null,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]
                );
                $assessmentCount++;
            }
            
            echo "✓ Migrated {$assessmentCount} assessments\n";
            
        } catch (\Exception $e) {
            echo "✗ Error migrating properties: " . $e->getMessage() . "\n";
        }
    }

    private function migratePostingJournal()
    {
        echo "Migrating posting journal from XAMPP...\n";
        
        try {
            $postingJournal = DB::connection('xampp')->table('POSTINGJOURNAL')->get();
            $migratedCount = 0;
            
            foreach ($postingJournal as $journal) {
                DB::table('postingjournal')->updateOrInsert(
                    [
                        'LOCAL_TIN' => $journal->LOCAL_TIN,
                        'TDNO' => $journal->TDNO,
                        'TAX_YEAR' => $journal->TAX_YEAR
                    ],
                    [
                        'LOCAL_TIN' => $journal->LOCAL_TIN,
                        'TDNO' => $journal->TDNO ?? '',
                        'TAX_YEAR' => $journal->TAX_YEAR ?? date('Y'),
                        'RPT_DUE' => $journal->RPT_DUE ?? 0,
                        'SEF_DUE' => $journal->SEF_DUE ?? 0,
                        'TOTAL_PAID' => $journal->TOTAL_PAID ?? 0,
                        'PENALTY' => $journal->PENALTY ?? 0,
                        'DISCOUNT' => $journal->DISCOUNT ?? 0,
                        'STATUS' => $journal->STATUS ?? 'ACTIVE',
                        'DUE_DATE' => $journal->DUE_DATE ?? null,
                        'PAYMENT_DATE' => $journal->PAYMENT_DATE ?? null,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]
                );
                $migratedCount++;
            }
            
            echo "✓ Migrated {$migratedCount} posting journal records\n";
            
        } catch (\Exception $e) {
            echo "✗ Error migrating posting journal: " . $e->getMessage() . "\n";
        }
    }

    private function migratePayments()
    {
        echo "Migrating payments from XAMPP...\n";
        
        try {
            $payments = DB::connection('xampp')->table('PAYMENT')->get();
            $migratedCount = 0;
            
            foreach ($payments as $payment) {
                DB::table('payment')->updateOrInsert(
                    ['PAYMENT_ID' => $payment->PAYMENT_ID],
                    [
                        'PAYMENT_ID' => $payment->PAYMENT_ID,
                        'LOCAL_TIN' => $payment->LOCAL_TIN,
                        'PAYMENTDATE' => $payment->PAYMENTDATE,
                        'AMOUNT' => $payment->AMOUNT ?? 0,
                        'RECEIPTNO' => $payment->RECEIPTNO ?? '',
                        'PAYMODE_CT' => $payment->PAYMODE_CT ?? 'CASH',
                        'PAIDBY' => $payment->PAIDBY ?? '',
                        'REMARK' => $payment->REMARK ?? '',
                        'AFTYPE' => $payment->AFTYPE ?? 'AF56',
                        'created_at' => now(),
                        'updated_at' => now()
                    ]
                );
                $migratedCount++;
            }
            
            echo "✓ Migrated {$migratedCount} payments\n";
            
            // Migrate payment details
            $paymentDetails = DB::connection('xampp')->table('PAYMENTDETAIL')->get();
            $detailCount = 0;
            
            foreach ($paymentDetails as $detail) {
                DB::table('paymentdetail')->updateOrInsert(
                    ['DETAIL_ID' => $detail->DETAIL_ID],
                    [
                        'DETAIL_ID' => $detail->DETAIL_ID,
                        'PAYMENT_ID' => $detail->PAYMENT_ID,
                        'TDNO' => $detail->TDNO ?? '',
                        'AF_ID' => $detail->AF_ID ?? null,
                        'DESCRIPTION' => $detail->DESCRIPTION ?? '',
                        'QTY' => $detail->QTY ?? 1,
                        'UNITPRICE' => $detail->UNITPRICE ?? 0,
                        'AMOUNT' => $detail->AMOUNT ?? 0,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]
                );
                $detailCount++;
            }
            
            echo "✓ Migrated {$detailCount} payment details\n";
            
        } catch (\Exception $e) {
            echo "✗ Error migrating payments: " . $e->getMessage() . "\n";
        }
    }

    private function migrateReferenceData()
    {
        echo "Migrating reference data from XAMPP...\n";
        
        try {
            // Migrate barangays
            $barangays = DB::connection('xampp')->table('T_BARANGAY')->get();
            $barangayCount = 0;
            
            foreach ($barangays as $barangay) {
                DB::table('t_barangay')->updateOrInsert(
                    ['code' => $barangay->CODE],
                    [
                        'code' => $barangay->CODE,
                        'description' => $barangay->DESCRIPTION ?? '',
                        'created_at' => now(),
                        'updated_at' => now()
                    ]
                );
                $barangayCount++;
            }
            
            echo "✓ Migrated {$barangayCount} barangays\n";
            
            // Migrate property kinds
            $propertyKinds = DB::connection('xampp')->table('T_PROPERTYKIND')->get();
            $kindCount = 0;
            
            foreach ($propertyKinds as $kind) {
                DB::table('t_propertykind')->updateOrInsert(
                    ['code' => $kind->CODE],
                    [
                        'code' => $kind->CODE,
                        'description' => $kind->DESCRIPTION ?? '',
                        'created_at' => now(),
                        'updated_at' => now()
                    ]
                );
                $kindCount++;
            }
            
            echo "✓ Migrated {$kindCount} property kinds\n";
            
        } catch (\Exception $e) {
            echo "✗ Error migrating reference data: " . $e->getMessage() . "\n";
        }
    }
}
