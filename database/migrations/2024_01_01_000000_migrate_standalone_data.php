<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class MigrateStandaloneData extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        // This migration will help you import data from your standalone system
        // You'll need to modify the table names and column mappings based on your actual standalone database
        
        echo "Starting data migration from standalone system...\n";
        
        // Example migration for taxpayer data
        // Modify this based on your actual standalone database structure
        $this->migrateTaxpayers();
        
        // Example migration for property data
        $this->migrateProperties();
        
        // Example migration for posting journal
        $this->migratePostingJournal();
        
        echo "Data migration completed!\n";
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
    }

    private function migrateTaxpayers()
    {
        echo "Migrating taxpayers from SQL Server...\n";
        
        try {
            // Connect to SQL Server and get taxpayers
            $standaloneTaxpayers = DB::connection('sqlserver')->table('TAXPAYER')->get();
            
            $migratedCount = 0;
            foreach ($standaloneTaxpayers as $taxpayer) {
                // Map SQL Server columns to MySQL columns
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
            echo "Please check your SQL Server connection settings\n";
        }
    }

    private function migrateProperties()
    {
        echo "Migrating properties from SQL Server...\n";
        
        try {
            // Migrate PROPERTY table
            $properties = DB::connection('sqlserver')->table('PROPERTY')->get();
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
            $assessments = DB::connection('sqlserver')->table('RPTASSESSMENT')->get();
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
        echo "Migrating posting journal from SQL Server...\n";
        
        try {
            $postingJournal = DB::connection('sqlserver')->table('POSTINGJOURNAL')->get();
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
}

