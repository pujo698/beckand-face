<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('attendance_logs', 'latitude')) {
                $table->double('latitude')->nullable()->after('status');
            }
            if (!Schema::hasColumn('attendance_logs', 'longitude')) {
                $table->double('longitude')->nullable()->after('latitude');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            
            // PERBAIKAN: Hanya hapus kolom 'latitude' JIKA ADA
            if (Schema::hasColumn('attendance_logs', 'latitude')) {
                $table->dropColumn('latitude');
            }

            // PERBAIKAN: Hanya hapus kolom 'longitude' JIKA ADA
            if (Schema::hasColumn('attendance_logs', 'longitude')) {
                $table->dropColumn('longitude');
            }
        });
    }
};
