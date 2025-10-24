<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentDetail extends Model
{
    protected $table = 'paymentdetail';
    protected $primaryKey = 'DETAIL_ID';
    public $timestamps = false;

    protected $fillable = [
        'DETAIL_ID',
        'PAYMENT_ID',
        'TDNO',
        'AF_ID',
        'DESCRIPTION',
        'QTY',
        'UNITPRICE',
        'AMOUNT'
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'PAYMENT_ID', 'PAYMENT_ID');
    }
}
