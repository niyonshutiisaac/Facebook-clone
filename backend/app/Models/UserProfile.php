<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    /** @use HasFactory<\Database\Factories\UserProfileFactory> */
    use HasFactory;

    protected $table = 'user_profiles';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id', 'headline', 'about', 'current_city', 'hometown',
        'relationship_status', 'partner_id', 'website', 'pronunciation',
        'favorite_quotes', 'blood_type', 'name_pronunciation_audio',
        'friends_count', 'followers_count', 'following_count', 'posts_count', 'photos_count',
        'featured_photos', 'work', 'education', 'places_lived',
        'contact_info', 'basic_info', 'family_members', 'life_events',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'featured_photos' => 'array',
            'work' => 'array',
            'education' => 'array',
            'places_lived' => 'array',
            'contact_info' => 'array',
            'basic_info' => 'array',
            'family_members' => 'array',
            'life_events' => 'array',
            'friends_count' => 'integer',
            'followers_count' => 'integer',
            'following_count' => 'integer',
            'posts_count' => 'integer',
            'photos_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_id');
    }
}
