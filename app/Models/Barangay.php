<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Barangay extends Model
{
    protected $table = 't_barangay'; // your table name

    public $timestamps = false; // disable timestamps if not present

    protected $fillable = ['code', 'description'];
}
