<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forms', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('tenant_key')->nullable()->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('status')->default('draft')->index();
            $table->string('access_mode')->default('authenticated');
            $table->string('identity_type')->nullable();
            $table->json('settings')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->timestamps();
        });

        Schema::create('form_fields', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('form_id')->constrained('forms')->cascadeOnDelete();
            $table->string('field_key');
            $table->string('label');
            $table->string('type');
            $table->text('description')->nullable();
            $table->boolean('required')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->json('options')->nullable();
            $table->json('validation')->nullable();
            $table->json('visibility')->nullable();
            $table->json('mapping')->nullable();
            $table->boolean('is_sensitive')->default(false);
            $table->timestamps();
            $table->unique(['form_id', 'field_key']);
            $table->index(['form_id', 'position']);
        });

        Schema::create('form_responses', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('form_id')->constrained('forms')->cascadeOnDelete();
            $table->string('tenant_key')->nullable()->index();
            $table->string('respondent_user_id')->nullable()->index();
            $table->text('respondent_email')->nullable();
            $table->text('respondent_identifier')->nullable();
            $table->char('respondent_email_hash', 64)->nullable()->index();
            $table->char('respondent_identifier_hash', 64)->nullable()->index();
            $table->string('status')->default('submitted')->index();
            $table->text('review_notes')->nullable();
            $table->unsignedInteger('latest_revision')->default(1);
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('applied_by')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
            $table->index(['form_id', 'status']);
            $table->index(['form_id', 'respondent_user_id']);
        });

        Schema::create('form_response_revisions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('form_response_id')->constrained('form_responses')->cascadeOnDelete();
            $table->unsignedInteger('revision');
            $table->longText('answer_payload');
            $table->longText('field_snapshot');
            $table->string('created_by')->nullable();
            $table->timestamp('created_at');
            $table->unique(['form_response_id', 'revision']);
        });

        Schema::create('form_response_links', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('form_response_id')->constrained('form_responses')->cascadeOnDelete();
            $table->string('model_key');
            $table->string('model_type')->nullable();
            $table->string('model_id')->nullable();
            $table->string('match_method')->nullable();
            $table->string('status')->default('pending')->index();
            $table->unsignedBigInteger('applied_by')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->unique(['form_response_id', 'model_key']);
        });

        Schema::create('form_audit_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('form_id')->constrained('forms')->cascadeOnDelete();
            $table->foreignUlid('form_response_id')->nullable()->constrained('form_responses')->nullOnDelete();
            $table->string('actor_id')->nullable()->index();
            $table->string('action')->index();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_audit_events');
        Schema::dropIfExists('form_response_links');
        Schema::dropIfExists('form_response_revisions');
        Schema::dropIfExists('form_responses');
        Schema::dropIfExists('form_fields');
        Schema::dropIfExists('forms');
    }
};
