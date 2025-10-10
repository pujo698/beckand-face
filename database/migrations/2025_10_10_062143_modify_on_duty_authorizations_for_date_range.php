<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('on_duty_authorizations', function (Blueprint $table) {
            // Mengubah nama kolom 'date' menjadi 'start_date'
            $table->renameColumn('date', 'start_date');
            // Menambahkan kolom baru 'end_date' setelah 'start_date'
            $table->date('end_date')->after('start_date');
        });
    }

    public function down(): void
    {
        Schema::table('on_duty_authorizations', function (Blueprint $table) {
            $table->dropColumn('end_date');
            $table->renameColumn('start_date', 'date');
        });
    }
};