<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_templates', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('tenant_key')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('model_key')->nullable();
            $table->json('definition');
            $table->timestamps();
            $table->index(['tenant_key', 'name']);
        });

        Schema::create('form_invitations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('form_id')->constrained('forms')->cascadeOnDelete();
            $table->string('model_key');
            $table->string('model_type')->nullable();
            $table->string('model_id');
            $table->char('token_hash', 64)->unique();
            $table->text('recipient_email');
            $table->string('status')->default('pending')->index();
            $table->timestamp('expires_at')->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignUlid('response_id')->nullable()->constrained('form_responses')->nullOnDelete();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->index(['form_id', 'model_key', 'model_id']);
            $table->index(['form_id', 'status']);
        });

        Schema::table('form_fields', function (Blueprint $table): void {
            $table->string('section')->nullable()->after('description');
            $table->json('presentation')->nullable()->after('validation');
            $table->json('behavior')->nullable()->after('presentation');
        });

        Schema::table('forms', function (Blueprint $table): void {
            $table->foreignUlid('template_id')->nullable()->after('created_by')->constrained('form_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('form_fields', function (Blueprint $table): void {
            $table->dropColumn(['section', 'presentation', 'behavior']);
        });

        Schema::table('forms', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('template_id');
        });

        Schema::dropIfExists('form_invitations');
        Schema::dropIfExists('form_templates');
    }
};
