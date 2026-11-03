<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * A small, believable cast of demo users.
 *
 * Every seeded account uses the same password so the README's "sign in as"
 * instructions work, and Ava Chen is the primary demo account with the richest
 * data (photos, posts, conversations).
 */
final class DemoUserSeeder extends Seeder
{
    public const string DEMO_PASSWORD = 'Password1!';

    /** @var list<array{first: string, last: string, username: string, headline: ?string, city: ?string, hometown: ?string, about: ?string}> */
    private const array PEOPLE = [
        ['first' => 'Ava', 'last' => 'Chen', 'username' => 'avachen', 'headline' => 'Designing small things that matter.', 'city' => 'Brooklyn, New York', 'hometown' => 'Columbus, Ohio', 'about' => 'Product designer. Coffee scientist. Slow runner of fast marathons.'],
        ['first' => 'Marcus', 'last' => 'Okafor', 'username' => 'marcusok', 'headline' => 'Backend engineer · liking things that scale', 'city' => 'Austin, Texas', 'hometown' => 'Lagos, Nigeria', 'about' => 'Distributed systems, terrible puns, excellent jollof.'],
        ['first' => 'Priya', 'last' => 'Raman', 'username' => 'priyar', 'headline' => 'Photographer & educator', 'city' => 'Toronto, Ontario', 'hometown' => 'Chennai, India', 'about' => 'Teaching light, one frame at a time.'],
        ['first' => 'Sofia', 'last' => 'Marchetti', 'username' => 'sofiam', 'headline' => 'Pasta is a personality', 'city' => 'Bologna, Italy', 'hometown' => 'Bologna, Italy', 'about' => 'Cookbook author. Currently obsessed with focaccia hydration.'],
        ['first' => 'Jonas', 'last' => 'Weber', 'username' => 'jonasw', 'headline' => 'Cyclist. Sometimes triathlete.', 'city' => 'Berlin, Germany', 'hometown' => 'Leipzig, Germany', 'about' => 'I ride bikes and complain about cobblestones.'],
        ['first' => 'Amara', 'last' => 'Diallo', 'username' => 'amarad', 'headline' => 'Clinical researcher', 'city' => 'Atlanta, Georgia', 'hometown' => 'Dakar, Senegal', 'about' => 'Infectious disease epidemiology. Tea over coffee, don\'t @ me.'],
        ['first' => 'Ravi', 'last' => 'Shankar', 'username' => 'ravish', 'headline' => 'Building things for the web', 'city' => 'Bengaluru, India', 'hometown' => 'Pune, India', 'about' => 'Frontend. Framer Motion apologist.'],
        ['first' => 'Elena', 'last' => 'Novak', 'username' => 'elenan', 'headline' => 'Architect · drawing sustainable futures', 'city' => 'Prague, Czechia', 'hometown' => 'Brno, Czechia', 'about' => 'Passive houses and loud opinions about concrete.'],
        ['first' => 'Theo', 'last' => 'Laurent', 'username' => 'theolaurent', 'headline' => 'Sommelier in recovery', 'city' => 'Lyon, France', 'hometown' => 'Bordeaux, France', 'about' => 'Ask me about natural wine. Actually, please do not.'],
        ['first' => 'Yuki', 'last' => 'Tanaka', 'username' => 'yukit', 'headline' => 'Illustrator and animator', 'city' => 'Tokyo, Japan', 'hometown' => 'Kanazawa, Japan', 'about' => 'Drawing tiny worlds since 2011.'],
        ['first' => 'Nadia', 'last' => 'Haddad', 'username' => 'nadhaddad', 'headline' => 'Documentary filmmaker', 'city' => 'Beirut, Lebanon', 'hometown' => 'Saida, Lebanon', 'about' => 'Stories about home, and the roads between.'],
        ['first' => 'Sam', 'last' => 'Whitfield', 'username' => 'samw', 'headline' => 'High school teacher · history', 'city' => 'Manchester, United Kingdom', 'hometown' => 'Leeds, United Kingdom', 'about' => 'Teaching the 20th century to people born in the 21st.'],
    ];

    public function run(): void
    {
        $password = Hash::make(self::DEMO_PASSWORD);

        foreach (self::PEOPLE as $index => $person) {
            $username = strtolower($person['username']);

            /** @var User $user */
            $user = User::query()->updateOrCreate(
                ['email' => $username.'@example.com'],
                [
                    'username' => $username,
                    'password' => $password,
                    'first_name' => $person['first'],
                    'last_name' => $person['last'],
                    'locale' => 'en',
                    'timezone' => 'UTC',
                    'email_verified_at' => now(),
                    'is_online' => $index < 4,
                    'last_seen_at' => now()->subMinutes($index * 7 + 2),
                ]
            );

            $user->profile()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'headline' => $person['headline'],
                    'about' => $person['about'],
                    'current_city' => $person['city'],
                    'hometown' => $person['hometown'],
                    'work' => [['company' => ucfirst($username).' Studio', 'position' => $this->jobTitle($person['headline']), 'from' => '2019']],
                    'education' => [['school' => 'State University', 'degree' => 'B.S.', 'to' => '2015']],
                    'places_lived' => [['city' => $person['hometown'], 'type' => 'hometown'], ['city' => $person['city'], 'type' => 'current']],
                ]
            );

            $user->settings()->updateOrCreate(
                ['user_id' => $user->id],
                ['theme' => $index % 3 === 0 ? 'dark' : 'system', 'language' => 'en']
            );

            foreach (\App\Services\Auth\AuthService::PRIVACY_DEFAULTS as $field => $audience) {
                $user->privacySettings()->updateOrCreate(
                    ['field' => $field],
                    ['audience' => $audience]
                );
            }

            // A few photos per person so the profile grid is not empty.
            \App\Models\Photo::factory()
                ->count(random_int(3, 9))
                ->create(['user_id' => $user->id]);
        }

        $this->command?->info(sprintf('Seeded %d demo users (password: %s).', count(self::PEOPLE), self::DEMO_PASSWORD));
    }

    private function jobTitle(string $headline): string
    {
        return Str::of($headline)->explode('·')->first() ?: 'Member';
    }
}
