<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Skema Unit Cost yang dibangun ulang.
     *
     * Alur utama: periode -> komponen -> parameter. Master komponen dan
     * parameter tidak diduplikasi; nilai/rumus disimpan per periode dan
     * konteks unit pada tabel entry.
     */
    public function up(): void
    {
        Schema::create('unit_cost_components', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name');
            $table->string('category', 80)->nullable()->index();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('unit_cost_parameters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('component_id')->nullable()
                ->constrained('unit_cost_components')->nullOnDelete();
            $table->string('code', 120)->unique();
            $table->string('name');
            $table->string('value_type', 20)->default('decimal');
            $table->string('unit', 50)->nullable();
            $table->string('scope', 30)->default('global');
            $table->string('source_type', 30)->default('manual');
            $table->string('source_name')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['component_id', 'is_active'], 'uc_parameters_component_active_idx');
            $table->index(['scope', 'source_type'], 'uc_parameters_scope_source_idx');
        });

        Schema::create('unit_cost_periods', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name');
            $table->unsignedSmallInteger('period_year')->index();
            $table->string('status', 20)->default('draft')->index();
            $table->foreignId('created_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['period_year', 'name'], 'uc_period_year_name_unique');
            $table->index(['period_year', 'status'], 'uc_period_year_status_idx');
        });

        Schema::create('unit_cost_formula_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parameter_id')
                ->constrained('unit_cost_parameters')->cascadeOnDelete();
            $table->foreignId('period_id')
                ->constrained('unit_cost_periods')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->text('expression');
            $table->foreignId('created_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['parameter_id', 'period_id', 'version'], 'uc_formula_param_period_version_unique');
            $table->index(['period_id', 'parameter_id'], 'uc_formula_period_param_idx');
        });

        Schema::create('unit_cost_parameter_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')
                ->constrained('unit_cost_periods')->cascadeOnDelete();
            $table->foreignId('parameter_id')
                ->constrained('unit_cost_parameters')->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()
                ->constrained('organization_units')->nullOnDelete();

            // scope_key menggantikan unique constraint yang melibatkan NULL.
            $table->string('scope_key', 160)->unique();

            $table->string('input_type', 20)->default('value');
            $table->decimal('input_value', 20, 6)->nullable();
            $table->foreignId('formula_version_id')->nullable()
                ->constrained('unit_cost_formula_versions')->nullOnDelete();
            $table->text('formula_text')->nullable();
            $table->decimal('result_value', 20, 6)->nullable();
            $table->string('validation_status', 20)->default('incomplete')->index();
            $table->text('validation_error')->nullable();
            $table->foreignId('updated_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['period_id', 'parameter_id'], 'uc_entries_period_param_idx');
            $table->index(['period_id', 'unit_id'], 'uc_entries_period_unit_idx');
            $table->index(['parameter_id', 'period_id'], 'uc_entries_param_period_idx');
        });

        Schema::create('unit_cost_result_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')
                ->constrained('unit_cost_periods')->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()
                ->constrained('organization_units')->nullOnDelete();
            $table->string('status', 20)->default('approved')->index();
            $table->json('payload');
            $table->foreignId('created_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['period_id', 'unit_id'], 'uc_snapshots_period_unit_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_cost_result_snapshots');
        Schema::dropIfExists('unit_cost_parameter_entries');
        Schema::dropIfExists('unit_cost_formula_versions');
        Schema::dropIfExists('unit_cost_periods');
        Schema::dropIfExists('unit_cost_parameters');
        Schema::dropIfExists('unit_cost_components');
    }
};
