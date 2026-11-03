<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostBackground extends Model
{
    /** @use HasFactory<\Database\Factories\PostBackgroundFactory> */
    use HasFactory;

    protected $table = 'post_backgrounds';

    /**
     * @var list<string>
     */
    protected $fillable = ['token', 'label', 'css_value', 'text_color', 'is_active', 'position'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * The eight gradients Facebook offers, keyed by token. Seeded by
     * PostBackgroundSeeder so the frontend and backend agree on the palette.
     */
    public const DEFAULTS = [
        ['token' => 'sunset', 'label' => 'Sunset', 'css_value' => 'linear-gradient(45deg, #FF512F, #DD2476)', 'text_color' => '#FFFFFF'],
        ['token' => 'ocean', 'label' => 'Ocean', 'css_value' => 'linear-gradient(45deg, #2193B0, #6DD5ED)', 'text_color' => '#FFFFFF'],
        ['token' => 'forest', 'label' => 'Forest', 'css_value' => 'linear-gradient(45deg, #134E5E, #71B280)', 'text_color' => '#FFFFFF'],
        ['token' => 'grape', 'label' => 'Grape', 'css_value' => 'linear-gradient(45deg, #4776E6, #8E54E9)', 'text_color' => '#FFFFFF'],
        ['token' => 'fire', 'label' => 'Fire', 'css_value' => 'linear-gradient(45deg, #F12711, #F5AF19)', 'text_color' => '#FFFFFF'],
        ['token' => 'midnight', 'label' => 'Midnight', 'css_value' => 'linear-gradient(45deg, #232526, #414345)', 'text_color' => '#FFFFFF'],
        ['token' => 'bubblegum', 'label' => 'Bubblegum', 'css_value' => 'linear-gradient(45deg, #FF6FD8, #3813C2)', 'text_color' => '#FFFFFF'],
        ['token' => 'mint', 'label' => 'Mint', 'css_value' => 'linear-gradient(45deg, #00B09B, #96C93D)', 'text_color' => '#FFFFFF'],
    ];
}
