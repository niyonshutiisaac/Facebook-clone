<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Cursor;
use PHPUnit\Framework\TestCase;

final class CursorTest extends TestCase
{
    public function test_it_round_trips_a_payload(): void
    {
        $payload = ['i' => '0192a3b4-5c6d-7e8f-9a0b-1c2d3e4f5a6b', 'o' => 15];

        $this->assertSame($payload, Cursor::decode(Cursor::encode($payload)));
    }

    public function test_the_cursor_is_url_safe(): void
    {
        $this->assertDoesNotMatchRegularExpression(
            '#[+/=]#',
            Cursor::encode(['i' => 'abc+def/ghi=', 'o' => 3])
        );
    }

    public function test_it_returns_null_for_garbage(): void
    {
        $this->assertNull(Cursor::decode('not-a-cursor!!!'));
        $this->assertNull(Cursor::decode(null));
    }

    public function test_a_tampered_cursor_does_not_decode_to_the_same_payload(): void
    {
        $encoded = Cursor::encode(['i' => 'a', 'o' => 1]);

        $this->assertNotSame(['i' => 'a', 'o' => 1], Cursor::decode(substr($encoded, 0, -2).'Zz'));
    }
}
