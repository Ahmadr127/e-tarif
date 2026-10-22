<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unit_cost_parameters', function (Blueprint $table) {
            // Metode dan nilai awal yang dipakai saat parameter belum memiliki
            // entry pada periode/unit tertentu. Untuk metode formula, kolom
            // default_value menyimpan teks ekspresi.
            $table->string('default_input_type', 20)->default('value');
            $table->text('default_value')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('unit_cost_parameters', function (Blueprint $table) {
            $table->dropColumn(['default_input_type', 'default_value']);
        });
    }
};
