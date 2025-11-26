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
        Schema::table('on_duty_authorizations', function (Blueprint $table) {
            $table->string('status')->default('approved')->after('reason'); 
            $table->text('description')->nullable()->after('reason');
        });
    }

    public function down()
    {
        Schema::table('on_duty_authorizations', function (Blueprint $table) {
            $table->dropColumn(['status', 'description']);
        });
    }
};
