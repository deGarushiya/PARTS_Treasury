<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ImportCsvData extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'migrate:import-csv {path?}';

    /**
     * The console command description.
     */
    protected $description = 'Import CSV data exported from SQL Server';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $path = $this->argument('path') ?: 'exports';
        
        $this->info('Starting CSV data import...');
        
        // Import taxpayers
        $this->importTaxpayers($path);
        
        // Import properties
        $this->importProperties($path);
        
        // Import assessments
        $this->importAssessments($path);
        
        // Import posting journal
        $this->importPostingJournal($path);
        
        // Import payments
        $this->importPayments($path);
        
        // Import reference data
        $this->importReferenceData($path);
        
        $this->info('CSV import completed!');
    }

    private function importTaxpayers($path)
    {
        $this->info('Importing taxpayers...');
        
        $csvPath = $path . '/TAXPAYER.csv';
        if (!file_exists($csvPath)) {
            $this->warn('TAXPAYER.csv not found, skipping...');
            return;
        }
        
        $handle = fopen($csvPath, 'r');
        $header = fgetcsv($handle);
        $count = 0;
        
        while (($data = fgetcsv($handle)) !== false) {
            $row = array_combine($header, $data);
            
            DB::table('taxpayer')->updateOrInsert(
                ['LOCAL_TIN' => $row['LOCAL_TIN']],
                [
                    'LOCAL_TIN' => $row['LOCAL_TIN'],
                    'NAME' => $row['OWNERNAME'] ?? $row['NAME'] ?? '',
                    'ADDRESS' => $row['ADDRESS'] ?? '',
                    'BARANGAY' => $row['BARANGAY'] ?? '',
                    'MUNICIPALITY' => $row['MUNICIPALITY'] ?? '',
                    'PROVINCE' => $row['PROVINCE'] ?? '',
                    'ZIPCODE' => $row['ZIPCODE'] ?? '',
                    'CONTACTNO' => $row['CONTACTNO'] ?? $row['CONTACT_NO'] ?? '',
                    'EMAIL' => $row['EMAIL'] ?? '',
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            );
            $count++;
        }
        
        fclose($handle);
        $this->info("✓ Imported {$count} taxpayers");
    }

    private function importProperties($path)
    {
        $this->info('Importing properties...');
        
        $csvPath = $path . '/PROPERTY.csv';
        if (!file_exists($csvPath)) {
            $this->warn('PROPERTY.csv not found, skipping...');
            return;
        }
        
        $handle = fopen($csvPath, 'r');
        $header = fgetcsv($handle);
        $count = 0;
        
        while (($data = fgetcsv($handle)) !== false) {
            $row = array_combine($header, $data);
            
            DB::table('property')->updateOrInsert(
                ['PROP_ID' => $row['PROP_ID']],
                [
                    'PROP_ID' => $row['PROP_ID'],
                    'PINNO' => $row['PINNO'] ?? '',
                    'CADASTRALLOTNO' => $row['CADASTRALLOTNO'] ?? '',
                    'CERTIFICATETITLENO' => $row['CERTIFICATETITLENO'] ?? '',
                    'BARANGAY_CT' => $row['BARANGAY_CT'] ?? '',
                    'PROPERTYKIND_CT' => $row['PROPERTYKIND_CT'] ?? '',
                    'USERID' => $row['USERID'] ?? '',
                    'TRANSDATE' => $row['TRANSDATE'] ?? now(),
                    'EXPIRED_BV' => $row['EXPIRED_BV'] ?? 0,
                    'PROPERTYREF_ID' => $row['PROPERTYREF_ID'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            );
            $count++;
        }
        
        fclose($handle);
        $this->info("✓ Imported {$count} properties");
    }

    private function importAssessments($path)
    {
        $this->info('Importing assessments...');
        
        $csvPath = $path . '/RPTASSESSMENT.csv';
        if (!file_exists($csvPath)) {
            $this->warn('RPTASSESSMENT.csv not found, skipping...');
            return;
        }
        
        $handle = fopen($csvPath, 'r');
        $header = fgetcsv($handle);
        $count = 0;
        
        while (($data = fgetcsv($handle)) !== false) {
            $row = array_combine($header, $data);
            
            DB::table('rptassessment')->updateOrInsert(
                ['TAXTRANS_ID' => $row['TAXTRANS_ID']],
                [
                    'TAXTRANS_ID' => $row['TAXTRANS_ID'],
                    'PROP_ID' => $row['PROP_ID'],
                    'TDNO' => $row['TDNO'] ?? '',
                    'ASSESSED_VALUE' => $row['ASSESSED_VALUE'] ?? 0,
                    'TAX_DUE' => $row['TAX_DUE'] ?? 0,
                    'CANCELLATIONDATE' => $row['CANCELLATIONDATE'] ?? null,
                    'ANNOTATION' => $row['ANNOTATION'] ?? null,
                    'MEMORANDA' => $row['MEMORANDA'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            );
            $count++;
        }
        
        fclose($handle);
        $this->info("✓ Imported {$count} assessments");
    }

    private function importPostingJournal($path)
    {
        $this->info('Importing posting journal...');
        
        $csvPath = $path . '/POSTINGJOURNAL.csv';
        if (!file_exists($csvPath)) {
            $this->warn('POSTINGJOURNAL.csv not found, skipping...');
            return;
        }
        
        $handle = fopen($csvPath, 'r');
        $header = fgetcsv($handle);
        $count = 0;
        
        while (($data = fgetcsv($handle)) !== false) {
            $row = array_combine($header, $data);
            
            DB::table('postingjournal')->updateOrInsert(
                [
                    'LOCAL_TIN' => $row['LOCAL_TIN'],
                    'TDNO' => $row['TDNO'],
                    'TAX_YEAR' => $row['TAX_YEAR']
                ],
                [
                    'LOCAL_TIN' => $row['LOCAL_TIN'],
                    'TDNO' => $row['TDNO'] ?? '',
                    'TAX_YEAR' => $row['TAX_YEAR'] ?? date('Y'),
                    'RPT_DUE' => $row['RPT_DUE'] ?? 0,
                    'SEF_DUE' => $row['SEF_DUE'] ?? 0,
                    'TOTAL_PAID' => $row['TOTAL_PAID'] ?? 0,
                    'PENALTY' => $row['PENALTY'] ?? 0,
                    'DISCOUNT' => $row['DISCOUNT'] ?? 0,
                    'STATUS' => $row['STATUS'] ?? 'ACTIVE',
                    'DUE_DATE' => $row['DUE_DATE'] ?? null,
                    'PAYMENT_DATE' => $row['PAYMENT_DATE'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            );
            $count++;
        }
        
        fclose($handle);
        $this->info("✓ Imported {$count} posting journal records");
    }

    private function importPayments($path)
    {
        $this->info('Importing payments...');
        
        $csvPath = $path . '/PAYMENT.csv';
        if (!file_exists($csvPath)) {
            $this->warn('PAYMENT.csv not found, skipping...');
            return;
        }
        
        $handle = fopen($csvPath, 'r');
        $header = fgetcsv($handle);
        $count = 0;
        
        while (($data = fgetcsv($handle)) !== false) {
            $row = array_combine($header, $data);
            
            DB::table('payment')->updateOrInsert(
                ['PAYMENT_ID' => $row['PAYMENT_ID']],
                [
                    'PAYMENT_ID' => $row['PAYMENT_ID'],
                    'LOCAL_TIN' => $row['LOCAL_TIN'],
                    'PAYMENTDATE' => $row['PAYMENTDATE'],
                    'AMOUNT' => $row['AMOUNT'] ?? 0,
                    'RECEIPTNO' => $row['RECEIPTNO'] ?? '',
                    'PAYMODE_CT' => $row['PAYMODE_CT'] ?? 'CASH',
                    'PAIDBY' => $row['PAIDBY'] ?? '',
                    'REMARK' => $row['REMARK'] ?? '',
                    'AFTYPE' => $row['AFTYPE'] ?? 'AF56',
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            );
            $count++;
        }
        
        fclose($handle);
        $this->info("✓ Imported {$count} payments");
    }

    private function importReferenceData($path)
    {
        $this->info('Importing reference data...');
        
        // Import barangays
        $barangayPath = $path . '/T_BARANGAY.csv';
        if (file_exists($barangayPath)) {
            $handle = fopen($barangayPath, 'r');
            $header = fgetcsv($handle);
            $count = 0;
            
            while (($data = fgetcsv($handle)) !== false) {
                $row = array_combine($header, $data);
                
                DB::table('t_barangay')->updateOrInsert(
                    ['code' => $row['CODE']],
                    [
                        'code' => $row['CODE'],
                        'description' => $row['DESCRIPTION'] ?? '',
                        'created_at' => now(),
                        'updated_at' => now()
                    ]
                );
                $count++;
            }
            
            fclose($handle);
            $this->info("✓ Imported {$count} barangays");
        }
        
        // Import property kinds
        $propertyKindPath = $path . '/T_PROPERTYKIND.csv';
        if (file_exists($propertyKindPath)) {
            $handle = fopen($propertyKindPath, 'r');
            $header = fgetcsv($handle);
            $count = 0;
            
            while (($data = fgetcsv($handle)) !== false) {
                $row = array_combine($header, $data);
                
                DB::table('t_propertykind')->updateOrInsert(
                    ['code' => $row['CODE']],
                    [
                        'code' => $row['CODE'],
                        'description' => $row['DESCRIPTION'] ?? '',
                        'created_at' => now(),
                        'updated_at' => now()
                    ]
                );
                $count++;
            }
            
            fclose($handle);
            $this->info("✓ Imported {$count} property kinds");
        }
    }
}
