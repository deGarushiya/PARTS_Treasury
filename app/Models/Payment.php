<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $table = 'payment';
    protected $primaryKey = 'PAYMENT_ID';
    public $timestamps = false;

    protected $fillable = [
        'PAYMENT_ID',
        'LOCAL_TIN',
        'PAYMENTDATE',
        'AMOUNT',
        'RECEIPTNO',
        'PAYMODE_CT',
        'PAIDBY',
        'REMARK',
        'AFTYPE'
    ];

    public function details()
    {
        return $this->hasMany(PaymentDetail::class, 'PAYMENT_ID', 'PAYMENT_ID');
    }

    public function cheque()
    {
        return $this->hasOne(PaymentCheque::class, 'PAYMENT_ID', 'PAYMENT_ID');
    }

    public function taxpayer()
    {
        return $this->belongsTo(Taxpayer::class, 'LOCAL_TIN', 'LOCAL_TIN');
    }
}
