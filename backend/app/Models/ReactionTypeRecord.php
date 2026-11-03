<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReactionType as ReactionTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Seeded lookup table mirroring App\Enums\ReactionType.
 *
 * The enum is the source of truth for code paths; this table exists so the
 * palette can be tweaked per deployment without a code deploy.
 */
class ReactionTypeRecord extends Model
{
    /** @use HasFactory<\Database\Factories\ReactionTypeRecordFactory> */
    use HasFactory;

    protected $table = 'reaction_types';

    /**
     * @var list<string>
     */
    protected $fillable = ['key', 'label', 'emoji', 'color', 'position', 'is_active'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return list<array{key: string, label: string, emoji: string, color: string, position: int}>
     */
    public static function defaults(): array
    {
        return array_map(
            fn (ReactionTypeEnum $type, int $index): array => [
                'key' => $type->value,
                'label' => $type->label(),
                'emoji' => $type->emoji(),
                'color' => $type->color(),
                'position' => $index,
                'is_active' => true,
            ],
            ReactionTypeEnum::cases(),
            array_keys(ReactionTypeEnum::cases())
        );
    }
}
