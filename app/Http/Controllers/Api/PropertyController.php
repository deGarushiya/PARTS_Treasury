<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller; 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PropertyController extends Controller
{
    public function index()
    {
        $sql = "SELECT 
                    TAXPAYER.LOCAL_TIN, 
                    TAXPAYER.OWNERNAME, 
                    RPTASSESSMENT.TDNO, 
                    PROPERTY.PINNO, 
                    PROPERTY.CADASTRALLOTNO, 
                    PROPERTY.CERTIFICATETITLENO, 
                    T_BARANGAY.DESCRIPTION AS barangay, 
                    PROPERTY.USERID, 
                    PROPERTY.TRANSDATE, 
                    RPTASSESSMENT.ANNOTATION, 
                    RPTASSESSMENT.MEMORANDA, 
                    PROPERTY.PROP_ID,
                    RPTASSESSMENT.TAXTRANS_ID,
                    PROPERTY.PROPERTYREF_ID
                FROM PROPERTY
                INNER JOIN RPTASSESSMENT ON PROPERTY.PROP_ID = RPTASSESSMENT.PROP_ID
                INNER JOIN PROPERTYOWNER ON PROPERTY.PROP_ID = PROPERTYOWNER.PROP_ID
                INNER JOIN TAXPAYER ON PROPERTYOWNER.LOCAL_TIN = TAXPAYER.LOCAL_TIN
                LEFT JOIN T_BARANGAY ON PROPERTY.BARANGAY_CT = T_BARANGAY.CODE
                LEFT JOIN RPTCANCELLED ON RPTASSESSMENT.TAXTRANS_ID = RPTCANCELLED.TAXTRANS_ID
                WHERE PROPERTY.EXPIRED_BV = 0 OR PROPERTY.EXPIRED_BV IS NULL";

        $results = DB::select($sql);
        return response()->json($results);
    }

    public function search(Request $request)
    {
        $searchBy   = $request->query('searchBy');
        $value      = $request->query('value');
        $startWith  = filter_var($request->query('startWith'), FILTER_VALIDATE_BOOLEAN);
        $cancelled  = filter_var($request->query('cancelled'), FILTER_VALIDATE_BOOLEAN);

        $allowedColumns = [
            'CADASTRALLOTNO'     => 'PROPERTY.CADASTRALLOTNO',
            'LOCAL_TIN'          => 'TAXPAYER.LOCAL_TIN',
            'CERTIFICATETITLENO' => 'PROPERTY.CERTIFICATETITLENO',
            'PINNO'              => 'PROPERTY.PINNO',
            'TDNO'               => 'RPTASSESSMENT.TDNO',
            'PREVTDNO'           => 'RPTCANCELLED.CANCELLEDTDNO'
        ];

        if (!array_key_exists($searchBy, $allowedColumns)) {
            return response()->json([], 400);
        }

        $column   = $allowedColumns[$searchBy];
        $operator = $startWith ? "$value%" : "%$value%";

        // Base SQL
        $sql = "SELECT 
                    TAXPAYER.LOCAL_TIN, 
                    TAXPAYER.OWNERNAME, 
                    RPTASSESSMENT.TDNO, 
                    PROPERTY.PINNO, 
                    PROPERTY.CADASTRALLOTNO, 
                    PROPERTY.CERTIFICATETITLENO, 
                    T_BARANGAY.DESCRIPTION AS barangay, 
                    PROPERTY.USERID, 
                    PROPERTY.TRANSDATE, 
                    RPTASSESSMENT.ANNOTATION, 
                    RPTASSESSMENT.MEMORANDA, 
                    PROPERTY.PROP_ID,
                    RPTASSESSMENT.TAXTRANS_ID,
                    PROPERTY.PROPERTYREF_ID
                FROM PROPERTY
                INNER JOIN RPTASSESSMENT ON PROPERTY.PROP_ID = RPTASSESSMENT.PROP_ID
                INNER JOIN PROPERTYOWNER ON PROPERTY.PROP_ID = PROPERTYOWNER.PROP_ID
                INNER JOIN TAXPAYER ON PROPERTYOWNER.LOCAL_TIN = TAXPAYER.LOCAL_TIN
                LEFT JOIN T_BARANGAY ON PROPERTY.BARANGAY_CT = T_BARANGAY.CODE";

        $where = "WHERE (PROPERTY.EXPIRED_BV = 0 OR PROPERTY.EXPIRED_BV IS NULL)";

        // Handle PREVTDNO separately
        if ($searchBy === 'PREVTDNO') {
            $sql .= " INNER JOIN RPTCANCELLED 
                        ON RPTASSESSMENT.TAXTRANS_ID = RPTCANCELLED.TAXTRANS_ID";
            $where .= " AND $column LIKE ?";
        } else {
            // Join RPTCANCELLED for filtering if needed
            $sql .= " LEFT JOIN RPTCANCELLED 
                        ON RPTASSESSMENT.TAXTRANS_ID = RPTCANCELLED.TAXTRANS_ID";
            $where .= " AND $column LIKE ?";

            if ($cancelled) {
                // When checkbox is checked, only show non-cancelled
                $where .= " AND RPTCANCELLED.CANCELLEDTDNO IS NULL";
            }
            // If unchecked, show all (cancelled + active)
        }

        $finalSql = "$sql $where";
        $results = DB::select($finalSql, [$operator]);

        return response()->json($results);
    }

    public function getByLocalTin($localTin)
    {
        $properties = DB::table('property')
            ->where('LOCAL_TIN', $localTin)
            ->select('TDNO as taxDec', 'PINNO as pin', 'BARANGAY', 'KIND as propertyKind', 'EXPIRED')
            ->get();

        return response()->json($properties);
    }
}
