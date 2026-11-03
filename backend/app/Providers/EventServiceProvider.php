<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\MessageSent;
use App\Events\PostCreated;
use App\Listeners\InvalidateFeedCacheOnNewPost;
use App\Listeners\SendNewMessageNotification;
use App\Listeners\TrackPostView;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Reaction;
use App\Models\User;
use App\Observers\CommentObserver;
use App\Observers\MessageObserver;
use App\Observers\PostObserver;
use App\Observers\StoryObserver;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

final class EventServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, list<class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],

        MessageSent::class => [
            SendNewMessageNotification::class,
        ],

        PostCreated::class => [
            InvalidateFeedCacheOnNewPost::class,
        ],

        \Illuminate\Auth\Events\Login::class => [
            TrackPostView::class,
        ],
    ];

    /**
     * @var list<class-string>
     */
    protected $observers = [
        Post::class => [PostObserver::class],
        Comment::class => [CommentObserver::class],
        Story::class => [StoryObserver::class],
        User::class => [\App\Observers\UserObserver::class],
        Reaction::class => [\App\Observers\ReactionObserver::class],
        \App\Models\Message::class => [MessageObserver::class],
    ];

    public function boot(): void
    {
        //
    }

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
