<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\PostType;
use PHPUnit\Framework\TestCase;

final class PostTypeTest extends TestCase
{
    public function test_weights_are_bounded_between_zero_and_one(): void
    {
        foreach (PostType::cases() as $type) {
            $this->assertGreaterThanOrEqual(0.0, $type->weight());
            $this->assertLessThanOrEqual(1.0, $type->weight());
        }
    }

    public function test_original_content_outranks_rehashed_content(): void
    {
        $this->assertGreaterThan(PostType::Text->weight(), PostType::Photo->weight());
        $this->assertGreaterThan(PostType::Photo->weight(), PostType::Video->weight());
    }
}
