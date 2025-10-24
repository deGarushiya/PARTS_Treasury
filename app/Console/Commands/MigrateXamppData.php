<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateXamppData extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'migrate:xampp {--test : Test connection only}';

    /**
     * The console command description.
     */
    protected $description = 'Migrate data from XAMPP MySQL database to Laravel system';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Starting XAMPP Data Migration...');
        
        // Test connection first
        if (!$this->testXamppConnection()) {
            $this->error('❌ Cannot connect to XAMPP database. Please check your configuration.');
            return 1;
        }
        
        if ($this->option('test')) {
            $this->info('✅ XAMPP connection successful!');
            return 0;
        }
        
        // Confirm migration
        if (!$this->confirm('This will migrate data from XAMPP to Laravel. Continue?')) {
            $this->info('Migration cancelled.');
            return 0;
        }
        
        // Run migration
        $this->migrateData();
        
        $this->info('🎉 XAMPP migration completed successfully!');
        return 0;
    }

    private function testXamppConnection()
    {
        try {
            $this->info('Testing XAMPP database connection...');
            DB::connection('xampp')->getPdo();
            return true;
        } catch (\Exception $e) {
            $this->error('Connection failed: ' . $e->getMessage());
            return false;
        }
    }

    private function migrateData()
    {
        $this->info('📊 Migrating data...');
        
        // Get table counts from XAMPP
        $xamppCounts = $this->getXamppTableCounts();
        
        // Migrate each table
        $this->migrateTaxpayers();
        $this->migrateProperties();
        $this->migratePostingJournal();
        $this->migratePayments();
        $this->migrateReferenceData();
        
        // Verify migration
        $this->verifyMigration($xamppCounts);
    }

    private function getXamppTableCounts()
    {
        $tables = ['TAXPAYER', 'PROPERTY', 'RPTASSESSMENT', 'POSTINGJOURNAL', 'PAYMENT'];
        $counts = [];
        
        foreach ($tables as $table) {
            try {
                $counts[$table] = DB::connection('xampp')->table($table)->count();
            } catch (\Exception $e) {
                $counts[$table] = 0;
            }
        }
        
        return $counts;
    }

    private function migrateTaxpayers()
    {
        $this->info('👥 Migrating taxpayers...');
        
        try {
            $xamppTaxpayers = DB::connection('xampp')->table('TAXPAYER')->get();
            $migratedCount = 0;
            
            $progressBar = $this->output->createProgressBar($xamppTaxpayers->count());
            $progressBar->start();
            
            foreach ($xamppTaxpayers as $taxpayer) {
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
                $progressBar->advance();
            }
            
            $progressBar->finish();
            $this->newLine();
            $this->info("✅ Migrated {$migratedCount} taxpayers");
            
        } catch (\Exception $e) {
            $this->error("❌ Error migrating taxpayers: " . $e->getMessage());
        }
    }

    private function migrateProperties()
    {
        $this->info('🏠 Migrating properties...');
        
        try {
            // Migrate properties
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
            
            $this->info("✅ Migrated {$migratedCount} properties");
            
            // Migrate assessments
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
            
            $this->info("✅ Migrated {$assessmentCount} assessments");
            
        } catch (\Exception $e) {
            $this->error("❌ Error migrating properties: " . $e->getMessage());
        }
    }

    private function migratePostingJournal()
    {
        $this->info('📋 Migrating posting journal...');
        
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
            
            $this->info("✅ Migrated {$migratedCount} posting journal records");
            
        } catch (\Exception $e) {
            $this->error("❌ Error migrating posting journal: " . $e->getMessage());
        }
    }

    private function migratePayments()
    {
        $this->info('💰 Migrating payments...');
        
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
            
            $this->info("✅ Migrated {$migratedCount} payments");
            
        } catch (\Exception $e) {
            $this->error("❌ Error migrating payments: " . $e->getMessage());
        }
    }

    private function migrateReferenceData()
    {
        $this->info('📚 Migrating reference data...');
        
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
            
            $this->info("✅ Migrated {$barangayCount} barangays");
            
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
            
            $this->info("✅ Migrated {$kindCount} property kinds");
            
        } catch (\Exception $e) {
            $this->error("❌ Error migrating reference data: " . $e->getMessage());
        }
    }

    private function verifyMigration($xamppCounts)
    {
        $this->info('🔍 Verifying migration...');
        
        $laravelCounts = [
            'TAXPAYER' => DB::table('taxpayer')->count(),
            'PROPERTY' => DB::table('property')->count(),
            'RPTASSESSMENT' => DB::table('rptassessment')->count(),
            'POSTINGJOURNAL' => DB::table('postingjournal')->count(),
            'PAYMENT' => DB::table('payment')->count(),
        ];
        
        $this->table(
            ['Table', 'XAMPP Count', 'Laravel Count', 'Status'],
            collect($xamppCounts)->map(function ($count, $table) use ($laravelCounts) {
                $laravelCount = $laravelCounts[$table] ?? 0;
                $status = $count == $laravelCount ? '✅ OK' : '⚠️ Mismatch';
                return [$table, $count, $laravelCount, $status];
            })->toArray()
        );
    }
}
