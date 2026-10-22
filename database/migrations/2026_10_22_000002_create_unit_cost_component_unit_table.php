<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Assign komponen unit cost ke unit organisasi (global, berlaku untuk
     * semua periode). Assignment diisi manual lewat halaman Unit Cost.
     */
    public function up(): void
    {
        Schema::create('unit_cost_component_unit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_cost_component_id')
                ->constrained('unit_cost_components')
                ->cascadeOnDelete();
            $table->foreignId('organization_unit_id')
                ->constrained('organization_units')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['unit_cost_component_id', 'organization_unit_id'],
                'unit_cost_component_unit_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_cost_component_unit');
    }
};
