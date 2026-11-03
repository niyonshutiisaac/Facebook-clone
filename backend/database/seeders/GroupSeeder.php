<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Seeder;

final class GroupSeeder extends Seeder
{
    /** @var list<array{name: string, description: string, privacy: string}> */
    private const array GROUPS = [
        ['name' => 'Bread Appreciation Society', 'description' => 'Hydration ratios, starters, and the occasional crumb shot. Be kind, be floury.', 'privacy' => 'public'],
        ['name' => 'Frontend Performance Nerds', 'description' => 'Shaving milliseconds off the critical path since 2014.', 'privacy' => 'public'],
        ['name' => 'Trail Running — North Loop', 'description' => 'Weekend runs, route sharing, and mud photography.', 'privacy' => 'private_visible'],
        ['name' => 'Film Photography Exchange', 'description' => 'Trade film, share scans, argue about grain.', 'privacy' => 'public'],
    ];

    public function run(): void
    {
        $users = User::query()->get();
        $owner = $users->firstWhere('username', 'avachen') ?? $users->first();

        foreach (self::GROUPS as $index => $attributes) {
            /** @var Group $group */
            $group = Group::query()->updateOrCreate(
                ['slug' => \Illuminate\Support\Str::slug($attributes['name'])],
                array_merge($attributes, [
                    'owner_id' => $owner->id,
                    'type' => 'general',
                    'topics' => [$attributes['name']],
                ])
            );

            $group->membershipRecords()->updateOrCreate(
                ['user_id' => $owner->id],
                ['role' => 'admin', 'status' => 'active', 'joined_at' => now(), 'badge' => 'admin']
            );

            foreach ($users->where('id', '!=', $owner->id)->random(min($users->count() - 1, random_int(2, 5))) as $member) {
                $group->membershipRecords()->updateOrCreate(
                    ['user_id' => $member->id],
                    ['role' => 'member', 'status' => 'active', 'joined_at' => now()->subDays(random_int(1, 200))]
                );
            }

            $group->forceFill(['members_count' => $group->membershipRecords()->count()])->saveQuietly();

            if ($index === 0) {
                $group->rules()->createMany([
                    ['title' => 'Be kind', 'description' => 'No snobbery about starter hydration.', 'position' => 1],
                    ['title' => 'No spam', 'description' => 'Post your bake, not your brand.', 'position' => 2],
                ]);
            }
        }

        $this->command?->info(sprintf('Seeded %d groups.', count(self::GROUPS)));
    }
}
