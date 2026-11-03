<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Audience;
use App\Enums\PostType;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Realistic timeline content: text, photos, a poll, a link, a life event.
 *
 * Posts are spread across the last week with a decaying distribution so the
 * ranked feed has something to rank, and reactions are skewed towards "like"
 * the way real engagement is.
 */
final class PostSeeder extends Seeder
{
    /** @var list<array{body: string, type: PostType, media: int, audience: Audience}> */
    private array $templates = [];

    public function run(): void
    {
        $this->templates = [
            ['body' => 'Finally finished the thing I have been squeezing into evenings for three months. Shipping beats perfect.', 'type' => PostType::Text, 'media' => 0, 'audience' => Audience::Friends],
            ['body' => 'Golden hour on the way home. No filter, no crop, just lucky.', 'type' => PostType::Photo, 'media' => 1, 'audience' => Audience::Public],
            ['body' => 'Six hours of footage, ninety seconds of film. Worth every dropped frame.', 'type' => PostType::Video, 'media' => 1, 'audience' => Audience::Public],
            ['body' => 'Serious question for the timeline: what is the correct amount of garlic?', 'type' => PostType::Poll, 'media' => 0, 'audience' => Audience::Friends],
            ['body' => 'This explanation of how feed ranking actually works is the best short write-up I have read.', 'type' => PostType::Link, 'media' => 0, 'audience' => Audience::Public],
            ['body' => 'Feeling grateful. That is it, that is the post.', 'type' => PostType::Feeling, 'media' => 0, 'audience' => Audience::Friends],
            ['body' => 'New job, new city, same terrible coffee order. Starting Monday.', 'type' => PostType::LifeEvent, 'media' => 0, 'audience' => Audience::Public],
            ['body' => 'Reminder that "we have always done it this way" is not a design system.', 'type' => PostType::Text, 'media' => 0, 'audience' => Audience::Public],
            ['body' => 'Sunday market haul. The tomatoes are unreasonably good this year.', 'type' => PostType::Photo, 'media' => 3, 'audience' => Audience::Friends],
            ['body' => 'Fifteen kilometres along the canal and not one goose incident. A personal best.', 'type' => PostType::Text, 'media' => 0, 'audience' => Audience::Friends],
        ];

        $users = User::query()->get();

        foreach ($users as $user) {
            $postCount = random_int(2, 6);

            for ($i = 0; $i < $postCount; $i++) {
                $template = fake()->randomElement($this->templates);

                /** @var Post $post */
                $post = Post::factory()->create([
                    'user_id' => $user->id,
                    'type' => $template['type'],
                    'body' => $template['body'],
                    'audience' => $template['audience'],
                    'created_at' => now()->subMinutes(random_int(20, 60 * 24 * 6)),
                ]);

                if ($template['media'] > 0) {
                    \App\Models\PostMedia::factory()
                        ->count($template['media'])
                        ->sequence(fn ($sequence) => ['position' => $sequence->index])
                        ->create([
                            'post_id' => $post->id,
                            'type' => $template['type'] === PostType::Video ? 'video' : 'image',
                        ]);
                }

                if ($template['type'] === PostType::Poll) {
                    \App\Models\Poll::factory()
                        ->withOptions(random_int(2, 4))
                        ->create(['post_id' => $post->id, 'question' => 'What is the correct amount of garlic?']);
                }

                if ($template['type'] === PostType::Link) {
                    $post->link()->create([
                        'url' => 'https://engineering.fb.com/2021/01/26/production-ml/facebook-ranking/',
                        'domain' => 'engineering.fb.com',
                    ]);
                }

                if ($template['type'] === PostType::Feeling) {
                    \App\Models\PostFeeling::create([
                        'post_id' => $post->id,
                        'emoji' => '🙏',
                        'label' => 'grateful',
                    ]);
                }

                $this->reactTo($post, $users);
            }
        }

        $this->command?->info('Seeded posts with reactions and occasional comments.');
    }

    /**
     * Attach a realistic reaction distribution to a post.
     */
    private function reactTo(Post $post, \Illuminate\Support\Collection $users): void
    {
        $reactors = $users->where('id', '!=', $post->user_id)->random(min($users->count() - 1, random_int(0, 6)));

        foreach ($reactors as $reactor) {
            \App\Models\Reaction::factory()->create([
                'user_id' => $reactor->id,
                'reactable_type' => 'post',
                'reactable_id' => $post->id,
                'type' => fake()->randomElement(['like', 'like', 'like', 'love', 'care', 'haha', 'wow']),
            ]);
        }

        $post->recountReactions();
    }
}
