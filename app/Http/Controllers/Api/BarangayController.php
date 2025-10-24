<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Barangay;

class BarangayController extends Controller
{
    /**
     * Display a listing of the barangays.
     */
    public function index()
    {
        // Select barangays (code + description)
        $barangays = Barangay::select('code', 'description')
            ->orderBy('description', 'asc')
            ->get();

        return response()->json($barangays);
    }
}
