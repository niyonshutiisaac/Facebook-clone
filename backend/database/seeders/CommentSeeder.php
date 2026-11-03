<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Comments — including one three-deep thread so the nested UI is exercised.
 */
final class CommentSeeder extends Seeder
{
    public function run(): void
    {
        $posts = Post::query()->inRandomOrder()->limit(20)->get();
        $users = User::query()->get();

        foreach ($posts as $post) {
            $topLevel = random_int(0, 4);

            for ($i = 0; $i < $topLevel; $i++) {
                $author = $users->random();

                /** @var Comment $comment */
                $comment = Comment::factory()->create([
                    'post_id' => $post->id,
                    'user_id' => $author->id,
                    'created_at' => now()->subMinutes(random_int(1, 600)),
                ]);

                // ~40% of comments get a reply, ~15% go three deep.
                if (fake()->boolean(40)) {
                    $reply = Comment::factory()->create([
                        'post_id' => $post->id,
                        'parent_id' => $comment->id,
                        'user_id' => $users->random()->id,
                        'depth' => 1,
                        'created_at' => now()->subMinutes(random_int(1, 500)),
                    ]);

                    if (fake()->boolean(35)) {
                        Comment::factory()->create([
                            'post_id' => $post->id,
                            'parent_id' => $reply->id,
                            'user_id' => $users->random()->id,
                            'depth' => 2,
                            'created_at' => now()->subMinutes(random_int(1, 400)),
                        ]);
                    }
                }

                if (fake()->boolean(30)) {
                    \App\Models\Reaction::factory()->create([
                        'user_id' => $users->random()->id,
                        'reactable_type' => 'comment',
                        'reactable_id' => $comment->id,
                        'type' => 'like',
                    ]);
                }
            }

            $post->recountComments();
        }

        $this->command?->info('Seeded nested comment threads.');
    }
}
