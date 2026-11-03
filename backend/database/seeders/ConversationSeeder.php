<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * A believable inbox: a handful of 1:1 threads with read/unread state and one
 * group chat, so the messenger UI opens onto something real.
 */
final class ConversationSeeder extends Seeder
{
    /** @var list<list<string>> */
    private const array SCRIPTS = [
        [
            'Hey! Are you coming to the thing on Saturday?',
            'Which thing? The one with the cake or the one with the spreadsheet?',
            'The cake. Definitely the cake.',
            'Then yes. Obviously yes.',
            'Perfect. Bringing the good coffee?',
            'Always.',
        ],
        [
            'Did you see the new design system drop?',
            'Just skimmed it. The spacing scale finally makes sense.',
            'Right?! Took them long enough.',
            'I know. I have been manually nudging 4px for two years.',
        ],
        [
            'Sending you the photos from the trip 📷',
            'Oh these are SO good. That light!',
            'Golden hour does 90% of the work honestly',
            'Do not undersell it, you framed that beautifully',
            '🥹 thank you',
        ],
        [
            'Are we still on for the run tomorrow?',
            '6am?',
            'I was thinking a civilised 7.',
            'Coward. 6:30 and we call it even.',
            'Deal.',
        ],
    ];

    public function run(): void
    {
        $users = User::query()->orderBy('email')->get();

        if ($users->count() < 2) {
            return;
        }

        $ava = $users->firstWhere('username', 'avachen') ?? $users->first();
        $others = $users->where('id', '!=', $ava->id)->values();

        $messenger = app(\App\Services\Messaging\MessengerService::class);

        foreach (self::SCRIPTS as $index => $script) {
            $other = $others->get($index % max(1, $others->count()));

            if ($other === null) {
                continue;
            }

            $conversation = $messenger->privateConversation($ava, $other);

            foreach ($script as $position => $line) {
                $sender = $position % 2 === 0 ? $other : $ava;

                $message = $messenger->sendMessage($sender, $conversation, ['body' => $line]);

                // Leave the last message unread so the badge shows a count.
                if ($position < count($script) - ($index === 0 ? 1 : 3)) {
                    $messenger->markRead($ava, $conversation, $message->id);
                }
            }
        }

        // One group chat with the first four people.
        if ($others->count() >= 3) {
            $group = $messenger->createGroup(
                $ava,
                'Weekend plans 🗓️',
                $others->take(3)->pluck('id')->all()
            );

            foreach ([
                [$ava, 'Okay, Saturday. What are we actually doing?'],
                [$others->get(0), 'Market in the morning, then the park?'],
                [$others->get(1), 'I am in. Bringing bread.'],
                [$others->get(2), 'I will bring the thing that goes with bread.'],
                [$ava, 'Perfect. See you all there.'],
            ] as [$sender, $body]) {
                $messenger->sendMessage($sender, $group, ['body' => $body]);
            }
        }

        $this->command?->info('Seeded conversations and group chat.');
    }
}
