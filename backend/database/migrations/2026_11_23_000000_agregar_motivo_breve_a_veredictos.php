<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('veredictos', function (Blueprint $table) {
            $table->text('motivo_breve')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('veredictos', function (Blueprint $table) {
            $table->dropColumn('motivo_breve');
        });
    }
};
