<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RpuController extends Controller
{
    // GET /api/rpus?barangay_code=XXX
    public function index(Request $request)
    {
        $barangayCode = $request->query('barangay_code');

        if (!$barangayCode) {
            return response()->json(['error' => 'barangay_code is required'], 400);
        }

        // Assuming you have a table "rpus" with column "barangay_code"
        $rpus = DB::table('rpus')
            ->where('barangay_code', $barangayCode)
            ->get();

        return response()->json($rpus);
    }
}
