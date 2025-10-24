<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePostingJournalTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('postingjournal', function (Blueprint $table) {
            $table->id('ID');
            $table->string('LOCAL_TIN', 20);
            $table->string('TDNO', 50);
            $table->integer('TAX_YEAR');
            $table->decimal('RPT_DUE', 15, 2)->default(0);
            $table->decimal('SEF_DUE', 15, 2)->default(0);
            $table->decimal('TOTAL_PAID', 15, 2)->default(0);
            $table->decimal('PENALTY', 15, 2)->default(0);
            $table->decimal('DISCOUNT', 15, 2)->default(0);
            $table->string('STATUS', 20)->default('ACTIVE');
            $table->date('DUE_DATE')->nullable();
            $table->date('PAYMENT_DATE')->nullable();
            $table->timestamps();
            
            $table->index(['LOCAL_TIN', 'TAX_YEAR']);
            $table->index('TDNO');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('postingjournal');
    }
}

