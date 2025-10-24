<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function getProperties()
    {
        $properties = DB::table('property')
            ->join('propertyowner', 'property.PROP_ID', '=', 'propertyowner.PROP_ID')
            ->join('taxpayer', 'propertyowner.LOCAL_TIN', '=', 'taxpayer.LOCAL_TIN')
            ->join('rptassessment', 'property.old_PROP_ID', '=', 'rptassessment.old_PROP_ID')
            ->join('t_barangay', 'property.BARANGAY_CT', '=', 't_barangay.CODE')
            ->select(
                'taxpayer.LOCAL_TIN',
                'taxpayer.OWNERNAME',
                'rptassessment.TDNO',
                'property.PINNO',
                'property.CADASTRALLOTNO',
                'property.CERTIFICATETITLENO',
                't_barangay.DESCRIPTION as barangay',
                'property.USERID',
                'property.TRANSDATE',
                'rptassessment.ANNOTATION',
                'rptassessment.MEMORANDA'
            )
            ->get();

        return response()->json($properties);
    }
}
