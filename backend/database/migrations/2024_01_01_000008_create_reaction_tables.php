<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reactions are polymorphic: a single row can belong to a post, comment,
     * message or story.
     */
    public function up(): void
    {
        Schema::create('reaction_types', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('key', 20)->unique()->comment('like|love|haha|wow|sad|angry');
            $table->string('label', 40);
            $table->string('emoji', 16);
            $table->string('color', 9);
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });

        Schema::create('reactions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->uuidMorphs('reactable');

            $table->string('type', 20)->default('like');

            $table->timestamps();

            // One reaction per user per target — switching type updates in place.
            $table->unique(['user_id', 'reactable_type', 'reactable_id'], 'reactions_target_unique');
            $table->index(['reactable_type', 'reactable_id', 'type'], 'reactions_target_type_idx');
            $table->index(['user_id', 'type'], 'reactions_user_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reactions');
        Schema::dropIfExists('reaction_types');
    }
};
