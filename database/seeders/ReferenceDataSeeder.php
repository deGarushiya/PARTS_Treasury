<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReferenceDataSeeder extends Seeder
{
    /**
     * Run the database seeders.
     */
    public function run()
    {
        // Seed barangays
        $this->seedBarangays();
        
        // Seed property kinds
        $this->seedPropertyKinds();
        
        // Seed sample taxpayers (for testing)
        $this->seedSampleTaxpayers();
    }

    private function seedBarangays()
    {
        $barangays = [
            ['code' => '001', 'description' => 'Poblacion'],
            ['code' => '002', 'description' => 'San Jose'],
            ['code' => '003', 'description' => 'San Pedro'],
            ['code' => '004', 'description' => 'San Juan'],
            ['code' => '005', 'description' => 'San Miguel'],
            // Add more barangays as needed
        ];

        foreach ($barangays as $barangay) {
            DB::table('t_barangay')->updateOrInsert(
                ['code' => $barangay['code']],
                $barangay
            );
        }
    }

    private function seedPropertyKinds()
    {
        $propertyKinds = [
            ['code' => 'RES', 'description' => 'Residential'],
            ['code' => 'COM', 'description' => 'Commercial'],
            ['code' => 'IND', 'description' => 'Industrial'],
            ['code' => 'AGR', 'description' => 'Agricultural'],
            ['code' => 'SPE', 'description' => 'Special'],
        ];

        foreach ($propertyKinds as $kind) {
            DB::table('t_propertykind')->updateOrInsert(
                ['code' => $kind['code']],
                $kind
            );
        }
    }

    private function seedSampleTaxpayers()
    {
        $taxpayers = [
            [
                'LOCAL_TIN' => 'TIN001',
                'NAME' => 'Juan Dela Cruz',
                'ADDRESS' => '123 Main Street',
                'BARANGAY' => 'Poblacion',
                'MUNICIPALITY' => 'Your Municipality',
                'PROVINCE' => 'Your Province',
                'ZIPCODE' => '1234',
                'CONTACTNO' => '09123456789',
                'EMAIL' => 'juan@example.com'
            ],
            [
                'LOCAL_TIN' => 'TIN002',
                'NAME' => 'Maria Santos',
                'ADDRESS' => '456 Oak Avenue',
                'BARANGAY' => 'San Jose',
                'MUNICIPALITY' => 'Your Municipality',
                'PROVINCE' => 'Your Province',
                'ZIPCODE' => '1234',
                'CONTACTNO' => '09123456790',
                'EMAIL' => 'maria@example.com'
            ],
        ];

        foreach ($taxpayers as $taxpayer) {
            DB::table('taxpayer')->updateOrInsert(
                ['LOCAL_TIN' => $taxpayer['LOCAL_TIN']],
                $taxpayer
            );
        }
    }
}

