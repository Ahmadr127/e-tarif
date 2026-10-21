<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nilai variabel dipisahkan dari definisi variabel.
     *
     * Satu definisi dapat memiliki nilai berbeda menurut unit dan periode.
     * `scope_key` adalah kunci stabil yang dibentuk aplikasi dari scope
     * variabel, sehingga kombinasi nullable tetap dapat dibuat unik secara
     * portabel di PostgreSQL maupun SQLite.
     */
    public function up(): void
    {
        Schema::create('calculation_variable_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calculation_variable_id')
                ->constrained('calculation_variables')->cascadeOnDelete();
            $table->foreignId('organization_unit_id')->nullable()
                ->constrained('organization_units')->nullOnDelete();
            $table->unsignedSmallInteger('period_year')->nullable();
            $table->string('scope_key', 160)->unique();
            $table->decimal('value', 20, 6);
            $table->string('source_name')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['calculation_variable_id', 'period_year'], 'calc_values_variable_period_idx');
            $table->index(['organization_unit_id', 'period_year'], 'calc_values_unit_period_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calculation_variable_values');
    }
};
