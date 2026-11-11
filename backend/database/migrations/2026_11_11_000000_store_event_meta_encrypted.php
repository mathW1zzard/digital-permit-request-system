<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Week 14: permit_events.meta now holds encrypted text, which is not valid JSON for a json column.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permit_events', function (Blueprint $table) {
            $table->text('meta')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('permit_events', function (Blueprint $table) {
            $table->json('meta')->nullable()->change();
        });
    }
};
