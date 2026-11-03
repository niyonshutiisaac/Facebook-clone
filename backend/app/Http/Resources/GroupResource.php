<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Group
 */
final class GroupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ?\App\Models\User $viewer */
        $viewer = $request->user();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'type' => $this->type,
            'privacy' => $this->privacy,
            'cover_url' => $this->cover_path !== null ? \App\Support\MediaUrl::image($this->cover_path, 'cover', (string) $this->id) : null,
            'avatar_url' => $this->avatar_path !== null ? \App\Support\MediaUrl::image($this->avatar_path, 'avatar', (string) $this->id) : null,
            'topics' => $this->topics,
            'members_count' => (int) $this->members_count,
            'posts_count' => (int) $this->posts_count,

            'membership' => $viewer === null ? null : [
                'is_member' => $this->membershipRecords()->where('user_id', $viewer->id)->where('status', 'active')->exists(),
                'role' => $this->roleOf($viewer),
                'is_admin' => $this->isAdmin($viewer),
            ],

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
