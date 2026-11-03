<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class ReportReasonSeeder extends Seeder
{
    /** @var list<array{slug: string, label: string, description: string, category: string, severity: string, requires_description: bool, applies_to: list<string>}> */
    private const array REASONS = [
        ['slug' => 'nudity', 'label' => 'Nudity or sexual activity', 'description' => 'Images or content involving nudity or sexual activity.', 'category' => 'sensitive', 'severity' => 'high', 'requires_description' => false, 'applies_to' => ['post', 'comment', 'photo', 'story', 'reel']],
        ['slug' => 'violence', 'label' => 'Violence or dangerous organisations', 'description' => 'Content that threatens violence or promotes dangerous groups.', 'category' => 'safety', 'severity' => 'critical', 'requires_description' => false, 'applies_to' => ['post', 'comment', 'photo', 'story', 'reel', 'group', 'page']],
        ['slug' => 'harassment', 'label' => 'Harassment or bullying', 'description' => 'Someone is being targeted, bullied or threatened.', 'category' => 'safety', 'severity' => 'high', 'requires_description' => false, 'applies_to' => ['post', 'comment', 'message', 'user', 'story', 'reel']],
        ['slug' => 'hate_speech', 'label' => 'Hate speech', 'description' => 'Attacks based on race, ethnicity, religion, gender or orientation.', 'category' => 'safety', 'severity' => 'critical', 'requires_description' => false, 'applies_to' => ['post', 'comment', 'message', 'user', 'group', 'page']],
        ['slug' => 'spam', 'label' => 'Spam', 'description' => 'Misleading posts, scams or repetitive content.', 'category' => 'quality', 'severity' => 'medium', 'requires_description' => false, 'applies_to' => ['post', 'comment', 'message', 'user', 'group', 'page']],
        ['slug' => 'false_information', 'label' => 'False information', 'description' => 'Information that is misleading or demonstrably false.', 'category' => 'quality', 'severity' => 'high', 'requires_description' => false, 'applies_to' => ['post', 'photo', 'reel', 'story']],
        ['slug' => 'copyright', 'label' => 'Intellectual property violation', 'description' => 'Content that infringes your copyright or trademark.', 'category' => 'legal', 'severity' => 'medium', 'requires_description' => true, 'applies_to' => ['post', 'photo', 'reel', 'story', 'page']],
        ['slug' => 'self_harm', 'label' => 'Suicide or self-injury', 'description' => 'Content that promotes suicide or self-harm.', 'category' => 'safety', 'severity' => 'critical', 'requires_description' => false, 'applies_to' => ['post', 'comment', 'message', 'user', 'story', 'reel']],
        ['slug' => 'fake_account', 'label' => 'Fake account', 'description' => 'This account is pretending to be someone else.', 'category' => 'account', 'severity' => 'medium', 'requires_description' => true, 'applies_to' => ['user']],
        ['slug' => 'underage', 'label' => 'Underage account', 'description' => 'This account belongs to someone under the minimum age.', 'category' => 'account', 'severity' => 'high', 'requires_description' => true, 'applies_to' => ['user']],
        ['slug' => 'other', 'label' => 'Something else', 'description' => 'Describe the problem and we will review it.', 'category' => 'other', 'severity' => 'low', 'requires_description' => true, 'applies_to' => ['post', 'comment', 'message', 'user', 'group', 'page', 'photo', 'story', 'reel']],
    ];

    public function run(): void
    {
        $now = now();

        foreach (self::REASONS as $index => $reason) {
            DB::table('report_reasons')->updateOrInsert(
                ['slug' => $reason['slug']],
                array_merge($reason, [
                    'applies_to' => json_encode($reason['applies_to'], JSON_THROW_ON_ERROR),
                    'position' => $index + 1,
                    'updated_at' => $now,
                    'created_at' => $now,
                ])
            );
        }
    }
}
