<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_plans', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 20)->default('meta');
            $table->string('name', 120);
            $table->string('objective', 30);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->decimal('planned_budget', 12, 2)->nullable();
            $table->char('planned_currency', 3)->nullable();
            $table->string('status', 30)->default('draft');
            $table->timestamps();

            $table->index(['workspace_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_plans');
    }
};
