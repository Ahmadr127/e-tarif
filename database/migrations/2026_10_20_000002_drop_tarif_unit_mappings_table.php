<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menghapus sistem mapping tarif individual.
 *
 * Buku tarif unit kini dihitung dari prefix `services.code`
 * (lihat tabel tarif_unit_service_prefixes). Sebelum tabel lama dihapus,
 * seluruh isinya dibackup ke storage/app/backups dalam format JSON agar
 * data produksi (jika ada) tidak hilang tanpa jejak.
 *
 * Rollback hanya membuat ulang struktur tabel kosong; pemulihan data
 * dilakukan manual dari file backup yang dicetak saat migration berjalan.
 */
return new class extends Migration
{
    private const TABLE = 'tarif_unit_mappings';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        $path = $this->backup();

        Schema::drop(self::TABLE);

        if (app()->runningInConsole()) {
            echo '  Backup data '.self::TABLE.": {$path}\n";
        }
    }

    public function down(): void
    {
        if (Schema::hasTable(self::TABLE)) {
            return;
        }

        Schema::create(self::TABLE, function (Blueprint $table) {
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

    /**
     * Simpan snapshot data lama. Migration dibatalkan bila backup gagal,
     * supaya tabel tidak pernah terhapus tanpa cadangan.
     */
    private function backup(): string
    {
        $rows = DB::table(self::TABLE)->get();

        $directory = storage_path('app/backups');
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Gagal membuat direktori backup: '.$directory);
        }

        $path = $directory.'/'.self::TABLE.'-'.now()->format('Ymd-His').'.json';

        $payload = json_encode([
            'table' => self::TABLE,
            'exported_at' => now()->toIso8601String(),
            'row_count' => $rows->count(),
            'rows' => $rows,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($payload === false || file_put_contents($path, $payload) === false) {
            throw new RuntimeException('Gagal menulis backup '.self::TABLE.' ke '.$path);
        }

        return $path;
    }
};
