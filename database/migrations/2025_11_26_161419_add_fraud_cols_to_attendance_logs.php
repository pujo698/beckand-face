<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->integer('risk_score')->default(0)->after('status');
            $table->text('risk_note')->nullable()->after('risk_score');
            $table->string('device_info')->nullable()->after('risk_note'); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropColumn(['risk_score', 'risk_note', 'device_info']);
        });
    }
};
