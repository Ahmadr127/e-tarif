<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pemetaan tarif ke unit organisasi.
     *
     * Satu tarif bisa dipakai banyak unit, dan satu unit bisa memakai banyak
     * tarif — pasangan (tarif_id, organization_unit_id) harus unik agar tidak
     * ada mapping ganda.
     */
    public function up(): void
    {
        Schema::create('tarif_unit_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tarif_id')->constrained('tarifs')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('organization_unit_id')->constrained('organization_units')->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tarif_id', 'organization_unit_id'], 'tarif_unit_mappings_pair_unique');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarif_unit_mappings');
    }
};
