<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\ReactionType;
use PHPUnit\Framework\TestCase;

final class ReactionTypeTest extends TestCase
{
    public function test_every_reaction_has_an_emoji_and_a_label(): void
    {
        foreach (ReactionType::cases() as $type) {
            $this->assertNotEmpty($type->emoji(), $type->value.' must have an emoji.');
            $this->assertNotEmpty($type->label(), $type->value.' must have a label.');
        }
    }

    public function test_like_is_the_default(): void
    {
        $this->assertSame(ReactionType::Like, ReactionType::tryFrom('not-a-reaction') ?? ReactionType::Like);
        $this->assertSame('like', ReactionType::Like->value);
    }

    public function test_values_are_stable(): void
    {
        $this->assertSame(
            ['like', 'love', 'care', 'haha', 'wow', 'sad', 'angry'],
            ReactionType::values()
        );
    }
}
