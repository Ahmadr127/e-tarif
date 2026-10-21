<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel calculation engine unit cost.
     *
     * `unit_code` disimpan sebagai snapshot agar riwayat tetap terbaca
     * walaupun unit organisasi berubah atau dihapus. Relasi opsional ke
     * `organization_units` dipakai untuk navigasi/filter, bukan sumber
     * kebenaran riwayat.
     */
    public function up(): void
    {
        Schema::create('cost_components', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name');
            $table->string('category', 80)->index();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('calculation_variables', function (Blueprint $table) {
            $table->id();
            $table->string('code', 120)->unique();
            $table->string('name');
            $table->foreignId('component_id')->nullable()
                ->constrained('cost_components')->nullOnDelete();
            $table->string('value_type', 20)->default('decimal');
            $table->decimal('default_value', 20, 6)->nullable();
            $table->string('unit', 50)->nullable();
            $table->string('scope', 30)->default('global');
            $table->string('source_type', 30)->default('manual');
            $table->string('source_name')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['scope', 'source_type']);
            $table->index(['component_id', 'is_active']);
        });

        Schema::create('calculation_formulas', function (Blueprint $table) {
            $table->id();
            $table->string('code', 120);
            $table->string('name');
            $table->foreignId('component_id')->nullable()
                ->constrained('cost_components')->nullOnDelete();
            $table->text('expression');
            $table->string('result_unit', 50)->nullable();
            $table->string('result_scope', 30)->default('unit_period');
            $table->unsignedInteger('version')->default(1);
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->string('source_note')->nullable();
            $table->text('validation_notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Satu kode boleh punya beberapa versi; versi lama tetap tersimpan
            // untuk audit hasil run historis.
            $table->unique(['code', 'version'], 'calc_formula_code_version_unique');
            $table->index(['code', 'is_active'], 'calc_formula_code_active_idx');
            $table->index(['component_id', 'is_active']);
            $table->index(['effective_from', 'effective_until']);
        });

        Schema::create('calculation_runs', function (Blueprint $table) {
            $table->id();
            $table->string('run_code', 80)->unique();
            $table->foreignId('organization_unit_id')->nullable()
                ->constrained('organization_units')->nullOnDelete();
            $table->string('unit_code', 80)->index();
            $table->unsignedSmallInteger('period_year')->index();
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('formula_version')->default(1);
            $table->json('parameter_snapshot')->nullable();
            $table->decimal('total_unit_cost', 20, 2)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('calculated_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['unit_code', 'period_year', 'status'], 'calc_runs_unit_period_status_idx');
            $table->index(['organization_unit_id', 'period_year'], 'calc_runs_unit_org_period_idx');
        });

        Schema::create('calculation_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calculation_run_id')
                ->constrained('calculation_runs')->cascadeOnDelete();
            $table->foreignId('formula_id')->nullable()
                ->constrained('calculation_formulas')->nullOnDelete();
            $table->string('code', 120);
            $table->string('name');
            $table->string('detail_type', 20)->default('formula');
            $table->unsignedInteger('formula_version')->nullable();
            $table->json('input_snapshot')->nullable();
            $table->text('expression')->nullable();
            $table->decimal('result_value', 20, 6)->nullable();
            $table->string('result_unit', 50)->nullable();
            $table->string('source_name')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['calculation_run_id', 'code'], 'calc_details_run_code_idx');
            $table->index(['calculation_run_id', 'detail_type'], 'calc_details_run_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calculation_details');
        Schema::dropIfExists('calculation_runs');
        Schema::dropIfExists('calculation_formulas');
        Schema::dropIfExists('calculation_variables');
        Schema::dropIfExists('cost_components');
    }
};
