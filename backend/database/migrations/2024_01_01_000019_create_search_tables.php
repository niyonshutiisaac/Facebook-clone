<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('search_history', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('query', 255);
            $table->string('result_type', 30)->nullable();
            $table->uuid('result_id')->nullable();
            $table->unsignedInteger('hits')->default(1);
            $table->timestamp('last_searched_at')->useCurrent();

            $table->timestamps();

            $table->index(['user_id', 'last_searched_at'], 'search_history_recent_idx');
            $table->index(['user_id', 'query'], 'search_history_query_idx');
        });

        Schema::create('trending_topics', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('term', 255);
            $table->unsignedBigInteger('search_count')->default(0);
            $table->decimal('velocity', 10, 4)->default(0);
            $table->timestamp('trending_since')->nullable();
            $table->timestamp('last_computed_at')->nullable();

            $table->timestamps();

            $table->unique('term', 'trending_topics_term_unique');
            $table->index(['search_count', 'velocity'], 'trending_topics_rank_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trending_topics');
        Schema::dropIfExists('search_history');
    }
};
