<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_campaigns', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_term')->nullable();
            $table->string('utm_content')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamps();

            $table->index(['workspace_id', 'status', 'created_at']);
        });

        Schema::create('crm_leads', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('sales_campaign_id')->nullable()->constrained('sales_campaigns')->nullOnDelete();
            $table->string('name', 120)->nullable();
            $table->string('email')->nullable();
            $table->string('status', 20)->default('new');
            $table->timestamps();

            $table->index(['workspace_id', 'status', 'created_at']);
            $table->index(['workspace_id', 'sales_campaign_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_leads');
        Schema::dropIfExists('sales_campaigns');
    }
};
