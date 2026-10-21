<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Setiap parameter memiliki variable global yang dipakai di formula.
     *
     * `code` tetap menjadi kode internal parameter, sedangkan `variable`
     * adalah nama yang direferensikan sebagai {variable} pada formula.
     */
    public function up(): void
    {
        Schema::table('unit_cost_parameters', function (Blueprint $table) {
            $table->string('variable', 120)->nullable()->after('code');
        });

        DB::table('unit_cost_parameters')->update([
            'variable' => DB::raw('code'),
        ]);

        Schema::table('unit_cost_parameters', function (Blueprint $table) {
            $table->unique('variable', 'uc_parameters_variable_unique');
        });
    }

    public function down(): void
    {
        Schema::table('unit_cost_parameters', function (Blueprint $table) {
            $table->dropUnique('uc_parameters_variable_unique');
            $table->dropColumn('variable');
        });
    }
};
