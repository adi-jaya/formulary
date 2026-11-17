<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proposal_items', function (Blueprint $table): void {
            $table->string('review_status', 32)->default('pending')->index();
            $table->text('review_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('reviewer_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('proposal_items', function (Blueprint $table): void {
            $table->dropIndex(['review_status']);
            $table->dropColumn(['review_status', 'review_notes', 'reviewed_at', 'reviewer_name']);
        });
    }
};
