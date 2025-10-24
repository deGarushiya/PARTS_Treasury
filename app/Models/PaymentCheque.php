<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentCheque extends Model
{
    protected $table = 'paymentcheque';
    protected $primaryKey = 'CHEQUE_ID';
    public $timestamps = false;

    protected $fillable = [
        'CHEQUE_ID',
        'PAYMENT_ID',
        'BANK',
        'CHEQUENO',
        'AMOUNT'
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'PAYMENT_ID', 'PAYMENT_ID');
    }
}
