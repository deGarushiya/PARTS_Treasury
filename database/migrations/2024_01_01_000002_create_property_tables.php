<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePropertyTables extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        // Property table
        Schema::create('property', function (Blueprint $table) {
            $table->id('PROP_ID');
            $table->string('PINNO', 50)->unique();
            $table->string('CADASTRALLOTNO', 50);
            $table->string('CERTIFICATETITLENO', 100);
            $table->string('BARANGAY_CT', 10);
            $table->string('PROPERTYKIND_CT', 10);
            $table->string('USERID', 50);
            $table->date('TRANSDATE');
            $table->integer('EXPIRED_BV')->default(0);
            $table->string('PROPERTYREF_ID', 50)->nullable();
            $table->timestamps();
            
            $table->index('PINNO');
            $table->index('BARANGAY_CT');
        });

        // RPT Assessment table
        Schema::create('rptassessment', function (Blueprint $table) {
            $table->id('TAXTRANS_ID');
            $table->unsignedBigInteger('PROP_ID');
            $table->string('TDNO', 50);
            $table->decimal('ASSESSED_VALUE', 15, 2);
            $table->decimal('TAX_DUE', 15, 2);
            $table->date('CANCELLATIONDATE')->nullable();
            $table->text('ANNOTATION')->nullable();
            $table->text('MEMORANDA')->nullable();
            $table->timestamps();
            
            $table->foreign('PROP_ID')->references('PROP_ID')->on('property');
            $table->index('TDNO');
        });

        // Property Owner table
        Schema::create('propertyowner', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('PROP_ID');
            $table->string('LOCAL_TIN', 20);
            $table->timestamps();
            
            $table->foreign('PROP_ID')->references('PROP_ID')->on('property');
            $table->foreign('LOCAL_TIN')->references('LOCAL_TIN')->on('taxpayer');
        });

        // TP Account table
        Schema::create('tpaccount', function (Blueprint $table) {
            $table->id();
            $table->string('LOCAL_TIN', 20);
            $table->string('ACCOUNT_TYPE', 20)->default('RPT');
            $table->string('STATUS', 20)->default('ACTIVE');
            $table->timestamps();
            
            $table->foreign('LOCAL_TIN')->references('LOCAL_TIN')->on('taxpayer');
        });

        // Reference tables
        Schema::create('t_propertykind', function (Blueprint $table) {
            $table->string('CODE', 10)->primary();
            $table->string('DESCRIPTION', 100);
            $table->timestamps();
        });

        // RPT Cancelled table
        Schema::create('rptcancelled', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('TAXTRANS_ID');
            $table->date('CANCELLATION_DATE');
            $table->string('REASON', 255);
            $table->timestamps();
            
            $table->foreign('TAXTRANS_ID')->references('TAXTRANS_ID')->on('rptassessment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('rptcancelled');
        Schema::dropIfExists('t_propertykind');
        Schema::dropIfExists('tpaccount');
        Schema::dropIfExists('propertyowner');
        Schema::dropIfExists('rptassessment');
        Schema::dropIfExists('property');
    }
}

