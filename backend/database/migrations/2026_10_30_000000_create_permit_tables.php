<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permit_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 80);
            $table->unsignedSmallInteger('validity_days');
            $table->unsignedSmallInteger('reminder_days');
            $table->boolean('requires_senior_approval')->default(false);
            $table->unsignedSmallInteger('review_sla_hours');
            $table->unsignedSmallInteger('approval_sla_hours');
            $table->timestamps();
        });

        Schema::create('permits', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->foreignId('requester_id')->constrained('users');
            $table->foreignId('permit_type_id')->constrained('permit_types');
            $table->foreignId('reviewer_id')->nullable()->constrained('users');
            $table->foreignId('approver_id')->nullable()->constrained('users');
            $table->string('purpose', 255);
            $table->string('location', 120);
            $table->text('details')->nullable();
            $table->date('requested_start');
            $table->string('status', 30)->index();
            $table->unsignedSmallInteger('revision_count')->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('stage_entered_at')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable()->index();
            $table->boolean('needs_manual_action')->default(false)->index();
            $table->string('manual_action_reason', 40)->nullable();
            $table->timestamps();
        });

        Schema::create('permit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users');
            $table->string('type', 40);
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->json('meta')->nullable();
            $table->string('correlation_id', 64)->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('outbox_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained('users');
            $table->string('kind', 40);
            $table->string('dedupe_key', 120)->unique(); // idempotency: one row per notification
            $table->string('status', 20)->default('pending'); // pending | sent | manual_action
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->string('last_error_class', 30)->nullable();
            $table->string('last_error_detail', 60)->nullable();
            $table->string('correlation_id', 64)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'next_attempt_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_notifications');
        Schema::dropIfExists('permit_events');
        Schema::dropIfExists('permits');
        Schema::dropIfExists('permit_types');
    }
};
