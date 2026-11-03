<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupRule extends Model
{
    /** @use HasFactory<\Database\Factories\GroupRuleFactory> */
    use HasFactory;

    protected $table = 'group_rules';

    /**
     * @var list<string>
     */
    protected $fillable = ['group_id', 'title', 'description', 'position'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
