<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportReason extends Model
{
    /** @use HasFactory<\Database\Factories\ReportReasonFactory> */
    use HasFactory;

    protected $table = 'report_reasons';

    /**
     * @var list<string>
     */
    protected $fillable = ['key', 'label', 'description', 'applies_to', 'requires_description', 'position'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requires_description' => 'boolean',
            'position' => 'integer',
        ];
    }
}
