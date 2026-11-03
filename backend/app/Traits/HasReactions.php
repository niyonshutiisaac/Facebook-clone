<?php

declare(strict_types=1);

namespace App\Traits;

use App\Enums\ReactionType;
use App\Models\Reaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;

/**
 * Gives any model (post, comment, message, story) the ability to be reacted to.
 *
 * Reactions live in a single polymorphic table, so the same trait powers
 * every surface without duplicating schema or query logic.
 *
 * @mixin Model
 */
trait HasReactions
{
    public function reactions(): MorphMany
    {
        return $this->morphMany(Reaction::class, 'reactable');
    }

    /**
     * Set or replace a user's reaction. Reacting with the type the user already
     * used removes it (Facebook's toggle behaviour).
     */
    public function toggleReaction(User $user, ReactionType $type): ?Reaction
    {
        return DB::transaction(function () use ($user, $type): ?Reaction {
            $existing = $this->reactions()->where('user_id', $user->id)->first();

            if ($existing !== null) {
                if ($existing->type === $type->value) {
                    $existing->delete();
                    $this->recountReactions();

                    return null;
                }

                $existing->forceFill(['type' => $type->value])->save();
                $this->recountReactions();

                return $existing->fresh();
            }

            $reaction = $this->reactions()->create([
                'user_id' => $user->id,
                'type' => $type->value,
            ]);

            $this->recountReactions();

            return $reaction;
        });
    }

    public function reactionBy(?User $user): ?Reaction
    {
        if ($user === null) {
            return null;
        }

        return $this->reactions()->where('user_id', $user->id)->first();
    }

    /**
     * Reaction totals grouped by type, e.g. ['like' => 12, 'love' => 3].
     *
     * @return array<string, int>
     */
    public function reactionSummary(): array
    {
        return $this->reactions()
            ->select('type', DB::raw('count(*) as total'))
            ->groupBy('type')
            ->pluck('total', 'type')
            ->all();
    }

    /**
     * The three reaction types displayed as icons in the reaction bar,
     * ordered by the canonical picker order rather than by raw count.
     *
     * @return list<array{type: string, total: int}>
     */
    public function topReactions(int $limit = 3): array
    {
        $summary = $this->reactionSummary();

        $ordered = collect(ReactionType::cases())
            ->filter(fn (ReactionType $type): bool => isset($summary[$type->value]))
            ->map(fn (ReactionType $type): array => [
                'type' => $type->value,
                'total' => (int) $summary[$type->value],
            ])
            ->sortByDesc('total')
            ->values()
            ->take($limit)
            ->all();

        return $ordered;
    }

    /**
     * @return Collection<int, Reaction>
     */
    public function reactionsOfType(ReactionType $type, int $limit = 25): Collection
    {
        return $this->reactions()
            ->with('user')
            ->where('type', $type->value)
            ->latest()
            ->limit($limit)
            ->get();
    }

    protected function recountReactions(): void
    {
        if (! $this->hasReactionCounterColumn()) {
            return;
        }

        $this->forceFill([
            $this->getReactionCounterColumn() => $this->reactions()->count(),
        ])->saveQuietly();
    }

    private function hasReactionCounterColumn(): bool
    {
        return in_array($this->getReactionCounterColumn(), $this->getFillable(), true)
            || array_key_exists($this->getReactionCounterColumn(), $this->getAttributes());
    }

    private function getReactionCounterColumn(): string
    {
        return 'reactions_count';
    }
}
