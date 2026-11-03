<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use App\Services\Social\PrivacyService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Visitor-aware profile payload.
 *
 * Unlike UserResource (which is safe for any audience), this one resolves each
 * sensitive field through PrivacyService before including it, so the same
 * endpoint serves "only me", "friends" and "public" visitors correctly.
 *
 * @mixin User
 */
final class ProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ?User $viewer */
        $viewer = $request->user();

        /** @var PrivacyService $privacy */
        $privacy = app(PrivacyService::class);

        $profile = $this->profile;

        $visible = fn (string $field): bool => $privacy->canSee(
            $viewer,
            $privacy->fieldAudience($this->resource, $field),
            null,
            $field,
            (string) $this->id
        );

        return array_merge((new UserResource($this->resource))->toArray($request), [
            'email' => $this->when($visible('email'), $this->email),
            'phone' => $this->when($visible('phone'), $this->phone),
            'birth_date' => $this->when($visible('birth_date'), $this->birth_date?->format('Y-m-d')),
            'gender' => $this->when($visible('gender'), $this->gender),
            'pronoun' => $this->when($visible('pronoun'), $this->pronoun),
            'age' => $this->when($visible('birth_date'), $this->age()),

            'about' => $this->when($profile !== null && $visible('about'), $profile->about),
            'website' => $this->when($profile !== null && $visible('website'), $profile->website),
            'relationship_status' => $this->when($profile !== null && $visible('relationship_status'), $profile->relationship_status),
            'favorite_quotes' => $this->when($profile !== null && $visible('favorite_quotes'), $profile->favorite_quotes),
            'pronunciation' => $this->when($profile !== null && $visible('pronunciation'), $profile->pronunciation),

            'work' => $this->when($profile !== null && $visible('work'), $profile->work),
            'education' => $this->when($profile !== null && $visible('education'), $profile->education),
            'places_lived' => $this->when($profile !== null && $visible('places_lived'), $profile->places_lived),
            'contact_info' => $this->when($profile !== null && $visible('contact_info'), $profile->contact_info),
            'basic_info' => $this->when($profile !== null && $visible('basic_info'), $profile->basic_info),
            'family_members' => $this->when($profile !== null && $visible('family_members'), $profile->family_members),
            'life_events' => $this->when($profile !== null && $visible('life_events'), $profile->life_events),
            'featured_photos' => $this->when($profile !== null, $profile->featured_photos),

            'friends_preview' => UserResource::collection(
                $this->whenLoaded('friendsPreview', $this->friends()->take(9))
            ),
        ]);
    }
}
