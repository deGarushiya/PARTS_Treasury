<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OwnersearchController extends Controller
{
    public function search(Request $request)
    {
        $query = DB::table('taxpayer');

        // 🔍 Log search parameters
        \Log::info('Owner Search Parameters:', [
            'lastName' => $request->lastName,
            'firstName' => $request->firstName,
            'localTin' => $request->localTin,
            'birTin' => $request->birTin,
            'fullname' => $request->fullname,
            'address' => $request->address,
        ]);

        // Helper closure to handle both normal and "beginning" searches
        $applySearch = function ($column, $value) use ($query) {
            if ($value === null || $value === '') return;

            // Check if frontend passed a trailing % (means "search from beginning")
            if (str_ends_with($value, '%')) {
                $cleanValue = rtrim($value, '%');
                $query->where($column, 'like', $cleanValue . '%');
                \Log::info("Applied search: $column LIKE '$cleanValue%'");
            } else {
                $query->where($column, 'like', '%' . $value . '%');
                \Log::info("Applied search: $column LIKE '%$value%'");
            }
        };

        // Apply to each searchable field
        $applySearch('LASTNAME', $request->lastName);
        $applySearch('FIRSTNAME', $request->firstName);
        $applySearch('LOCAL_TIN', $request->localTin);
        $applySearch('TINNO', $request->birTin);
        $applySearch('OWNERNAME', $request->fullname);
        $applySearch('OWNERADDRESS', $request->address);

        // 🔍 Log the actual SQL query
        $sql = $query->toSql();
        $bindings = $query->getBindings();
        \Log::info('Generated SQL:', ['sql' => $sql, 'bindings' => $bindings]);

        $results = $query
            ->select(
                'LOCAL_TIN',
                'LASTNAME',
                'FIRSTNAME',
                'MI',
                'OWNERNAME',
                'OWNERADDRESS',
                'TINNO'
            )
            // ->limit(100)
            ->get();

        \Log::info('Search Results Count:', ['count' => $results->count()]);

        return response()->json($results);
    }
}
