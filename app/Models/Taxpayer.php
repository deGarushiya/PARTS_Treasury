<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Taxpayer extends Model
{
    protected $table = 'taxpayer';
    protected $primaryKey = 'LOCAL_TIN';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'LOCAL_TIN',
        'NAME',
        'ADDRESS',
        'BARANGAY',
        'MUNICIPALITY',
        'PROVINCE',
        'ZIPCODE',
        'CONTACTNO',
        'EMAIL'
    ];

    public function payments()
    {
        return $this->hasMany(Payment::class, 'LOCAL_TIN', 'LOCAL_TIN');
    }
}
