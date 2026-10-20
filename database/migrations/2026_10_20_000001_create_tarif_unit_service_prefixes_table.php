<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Konfigurasi prefix kode service per unit organisasi.
     *
     * Buku tarif unit tidak lagi menyimpan assignment tarif satu per satu:
     * cakupan tarif dihitung dari kecocokan awal `services.code` dengan prefix
     * aktif milik unit (lihat Tarif::scopeForServicePrefixes()).
     *
     * - Prefix dinormalisasi ke huruf besar oleh aplikasi, sehingga unique
     *   (organization_unit_id, prefix) sudah case-insensitive.
     * - Menghapus unit akan menghapus konfigurasi prefix-nya (cascade);
     *   menghapus prefix TIDAK menyentuh unit.
     */
    public function up(): void
    {
        Schema::create('tarif_unit_service_prefixes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_unit_id')
                ->constrained('organization_units')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->string('prefix', 50);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['organization_unit_id', 'prefix'], 'tarif_unit_prefixes_unit_prefix_unique');
            $table->index(['organization_unit_id', 'is_active'], 'tarif_unit_prefixes_unit_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarif_unit_service_prefixes');
    }
};
