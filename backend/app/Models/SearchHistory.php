<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchHistory extends Model
{
    /** @use HasFactory<\Database\Factories\SearchHistoryFactory> */
    use HasFactory;

    protected $table = 'search_history';

    /**
     * @var list<string>
     */
    protected $fillable = ['user_id', 'query', 'result_type', 'result_id', 'hits', 'last_searched_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hits' => 'integer',
            'last_searched_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
