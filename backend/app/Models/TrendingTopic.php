<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrendingTopic extends Model
{
    /** @use HasFactory<\Database\Factories\TrendingTopicFactory> */
    use HasFactory;

    protected $table = 'trending_topics';

    /**
     * @var list<string>
     */
    protected $fillable = ['term', 'search_count', 'velocity', 'trending_since', 'last_computed_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'search_count' => 'integer',
            'velocity' => 'decimal:4',
            'trending_since' => 'datetime',
            'last_computed_at' => 'datetime',
        ];
    }
}
