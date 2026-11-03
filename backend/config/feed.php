<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | News feed ranking
    |--------------------------------------------------------------------------
    |
    | The weights below must sum to 1.0 — the ranking service normalises them
    | defensively, but keeping them honest here makes the model readable.
    |
    |   affinity   how close you are to the author (weighted interactions)
    |   quality    intrinsic quality of the content (media, text, structure)
    |   engagement how other people are reacting right now (velocity)
    |   decay      how new the post is
    |
    | Facebook-style ranking also applies *fatigue* penalties so a single
    | author or a single content type cannot monopolise the first screen.
    |
    */

    'weights' => [
        'affinity' => (float) env('FEED_WEIGHT_AFFINITY', 0.35),
        'quality' => (float) env('FEED_WEIGHT_QUALITY', 0.25),
        'engagement' => (float) env('FEED_WEIGHT_ENGAGEMENT', 0.25),
        'decay' => (float) env('FEED_WEIGHT_DECAY', 0.15),
    ],

    'decay' => [
        // Half-life in hours used by exp(-hours / horizon).
        'horizon_hours' => (float) env('FEED_DECAY_HORIZON_HOURS', 24),
        // Posts older than this are effectively gone from the ranked feed.
        'max_age_hours' => (int) env('FEED_MAX_AGE_HOURS', 24 * 7),
    ],

    'candidates' => [
        // How many candidate posts are pulled from the database before ranking.
        'limit' => (int) env('FEED_CANDIDATE_LIMIT', 500),
        // How far back candidates are drawn from.
        'lookback_days' => (int) env('FEED_LOOKBACK_DAYS', 7),
    ],

    'pagination' => [
        'page_size' => (int) env('FEED_PAGE_SIZE', 10),
        'max_page_size' => 50,
    ],

    'cache' => [
        // Redis store defined in config/cache.php ('feed' connection).
        'ttl' => (int) env('FEED_CACHE_TTL', 900),
        'lock_seconds' => 10,
        'warm_active_minutes' => 60,
    ],

    'fatigue' => [
        // Multiplier applied to consecutive posts by the same author.
        'author' => [1.0, 0.55, 0.22, 0.05],
        // Applied from the third consecutive post of the same type onwards.
        'type_threshold' => 3,
        'type_multiplier' => 0.4,
    ],

    /*
     * A viewer deep into one session has told us they are enjoying themselves;
     * surface more of the same. Capped at +15%.
     */
    'session_boost' => [
        'max' => 0.15,
        'depth_at_max' => 20,
    ],

    'affinity' => [
        'cache_ttl' => (int) env('FEED_AFFINITY_CACHE_TTL', 3600),
        // Ceilings applied to raw signal counts before normalisation.
        'ceilings' => [
            'reactions' => 50,
            'comments' => 30,
            'messages' => 200,
            'profile_views' => 25,
            'shares' => 20,
        ],
    ],

    'quality' => [
        'weights' => [
            'media' => 0.30,
            'text' => 0.25,
            'structure' => 0.20,
            'author_health' => 0.15,
            'link' => 0.10,
        ],
        'penalty_caps' => 0.35,
    ],
];
