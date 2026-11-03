<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\MeController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\Event\EventController;
use App\Http\Controllers\Feed\NewsFeedController;
use App\Http\Controllers\Friend\FriendController;
use App\Http\Controllers\Friend\FriendRequestController;
use App\Http\Controllers\Friend\FriendSuggestionController;
use App\Http\Controllers\Group\GroupController;
use App\Http\Controllers\Group\GroupMemberController;
use App\Http\Controllers\Media\MediaUploadController;
use App\Http\Controllers\Messenger\ConversationController;
use App\Http\Controllers\Messenger\MessageController;
use App\Http\Controllers\Messenger\PresenceController;
use App\Http\Controllers\Notification\NotificationController;
use App\Http\Controllers\Page\PageController;
use App\Http\Controllers\Post\CommentController;
use App\Http\Controllers\Post\PostController;
use App\Http\Controllers\Post\ReactionController;
use App\Http\Controllers\Post\SavedPostController;
use App\Http\Controllers\User\ProfileController;
use App\Http\Controllers\Reel\ReelController;
use App\Http\Controllers\Search\SearchController;
use App\Http\Controllers\Story\StoryController;
use App\Http\Controllers\User\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API routes
|--------------------------------------------------------------------------
|
| Everything below is prefixed with /api and, with the exception of the guest
| auth routes, requires a Sanctum token or a stateful SPA session cookie.
|
| Naming convention: resources are plural, state-changing verbs are nested
| under the resource they act on (`/posts/{post}/reactions`), which keeps the
| frontend's api layer a 1:1 mirror of this file.
|
*/

// ------------------------------------------------------------------ guests --
Route::prefix('auth')->name('auth.')->group(function (): void {
    Route::post('register', RegisterController::class)
        ->middleware('throttle:5,1')
        ->name('register');

    Route::post('login', LoginController::class)
        ->middleware('throttle:login')
        ->name('login');

    Route::post('availability', [RegisterController::class, 'checkAvailability'])
        ->middleware('throttle:30,1')
        ->name('availability');

    Route::post('forgot-password', [PasswordResetController::class, 'send'])
        ->middleware('throttle:5,1')
        ->name('password.email');

    Route::post('reset-password', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:10,1')
        ->name('password.update');

    Route::post('email/verify/{id}', [EmailVerificationController::class, 'verify'])
        ->name('verification.verify');
});

// ------------------------------------------------------------ authenticated --
Route::middleware(['auth:sanctum', 'account.status', 'track.activity'])->group(function (): void {

    // --- session & account ---
    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('logout', LogoutController::class)->name('logout');
        Route::get('me', MeController::class)->name('me');
        Route::get('me/notifications', [MeController::class, 'notifications'])->name('me.notifications');
        Route::put('password', [PasswordResetController::class, 'change'])->name('password.change');
        Route::post('email/resend', [EmailVerificationController::class, 'resend'])
            ->middleware('throttle:1,1')
            ->name('verification.send');

        Route::get('sessions', [SessionController::class, 'index'])->name('sessions.index');
        Route::delete('sessions', [SessionController::class, 'destroyAll'])->name('sessions.destroyAll');
        Route::delete('sessions/{id}', [SessionController::class, 'destroy'])->name('sessions.destroy');

        Route::post('two-factor/enable', [TwoFactorController::class, 'enable'])->name('2fa.enable');
        Route::post('two-factor/verify', [TwoFactorController::class, 'confirm'])->name('2fa.confirm');
        Route::delete('two-factor/disable', [TwoFactorController::class, 'disable'])->name('2fa.disable');
        Route::post('two-factor/sms/send', [TwoFactorController::class, 'sendSms'])->name('2fa.sms.send');
        Route::post('two-factor/sms/verify', [TwoFactorController::class, 'verifySms'])->name('2fa.sms.verify');
    });

    // --- news feed ---
    Route::prefix('feed')->name('feed.')->group(function (): void {
        Route::get('/', [NewsFeedController::class, 'index'])->name('index');
        Route::get('recent', [NewsFeedController::class, 'recent'])->name('recent');
        Route::get('new-posts-count', [NewsFeedController::class, 'newPostsCount'])->name('new');
        Route::post('interactions', [NewsFeedController::class, 'trackInteractions'])
            ->middleware('throttle:60,1')
            ->name('interactions');
        Route::post('hide/{post}', [NewsFeedController::class, 'hide'])->name('hide');
        Route::post('snooze', [NewsFeedController::class, 'snooze'])->name('snooze');
    });

    // --- posts ---
    Route::apiResource('posts', PostController::class);
    Route::post('posts/link-preview', [PostController::class, 'linkPreview'])->name('posts.link-preview');
    Route::put('posts/{post}/audience', [PostController::class, 'audience'])->name('posts.audience');
    Route::get('posts/{post}/edit-history', [PostController::class, 'editHistory'])->name('posts.history');
    Route::post('posts/{post}/share', [PostController::class, 'share'])->name('posts.share');
    Route::post('posts/{post}/pin', [PostController::class, 'pin'])->name('posts.pin');

    // --- reactions (polymorphic: post | comment | message | story) ---
    Route::put('{targetType}/{targetId}/reactions', [ReactionController::class, 'toggle'])
        ->whereIn('targetType', ['post', 'comment', 'message', 'story'])
        ->name('reactions.toggle');
    Route::get('{targetType}/{targetId}/reactions', [ReactionController::class, 'summary'])
        ->whereIn('targetType', ['post', 'comment', 'message', 'story'])
        ->name('reactions.summary');
    Route::get('{targetType}/{targetId}/reactions/reactors', [ReactionController::class, 'reactors'])
        ->whereIn('targetType', ['post', 'comment', 'message', 'story'])
        ->name('reactions.reactors');

    // --- comments ---
    Route::get('posts/{post}/comments', [CommentController::class, 'index'])->name('comments.index');
    Route::post('posts/{post}/comments', [CommentController::class, 'store'])->name('comments.store');
    Route::put('comments/{comment}', [CommentController::class, 'update'])->name('comments.update');
    Route::delete('comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');
    Route::get('comments/{comment}/replies', [CommentController::class, 'replies'])->name('comments.replies');
    Route::post('comments/{comment}/replies', [CommentController::class, 'store'])->name('comments.replies.store');

    // --- saved items ---
    Route::get('saved', [SavedPostController::class, 'index'])->name('saved.index');
    Route::post('posts/{post}/save', [SavedPostController::class, 'store'])->name('saved.store');
    Route::delete('posts/{post}/save', [SavedPostController::class, 'destroy'])->name('saved.destroy');
    Route::get('saved/collections', [SavedPostController::class, 'collections'])->name('saved.collections');
    Route::post('saved/collections', [SavedPostController::class, 'storeCollection'])->name('saved.collections.store');
    Route::put('saved/collections/{collection}', [SavedPostController::class, 'updateCollection'])->name('saved.collections.update');
    Route::delete('saved/collections/{collection}', [SavedPostController::class, 'destroyCollection'])->name('saved.collections.destroy');

    // --- users & profiles ---
    Route::apiResource('users', UserController::class)->only(['index', 'show']);
    Route::get('users/{user}/mutual-friends/{other}', [UserController::class, 'mutualFriends'])->name('users.mutual');
    Route::post('users/{user}/block', [UserController::class, 'block'])->name('users.block');
    Route::delete('users/{user}/block', [UserController::class, 'unblock'])->name('users.unblock');
    Route::post('users/{user}/mute', [UserController::class, 'mute'])->name('users.mute');
    Route::delete('users/{user}/mute', [UserController::class, 'unmute'])->name('users.unmute');
    Route::post('users/{user}/restrict', [UserController::class, 'restrict'])->name('users.restrict');
    Route::delete('users/{user}/restrict', [UserController::class, 'unrestrict'])->name('users.unrestrict');
    Route::get('users/{user}/activity-log', [UserController::class, 'activityLog'])->name('users.activity');

    Route::get('users/{user}/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('users/{user}/timeline', [ProfileController::class, 'timeline'])->name('profile.timeline');
    Route::get('users/{user}/photos', [ProfileController::class, 'photos'])->name('profile.photos');
    Route::get('users/{user}/friends', [ProfileController::class, 'friends'])->name('profile.friends');

    Route::match(['get', 'put'], 'settings', [UserController::class, 'settings'])->name('settings');
    Route::match(['get', 'put'], 'privacy/settings', [UserController::class, 'privacy'])->name('privacy.settings');

    // --- my profile ---
    Route::prefix('me')->name('me.')->group(function (): void {
        Route::put('/', [ProfileController::class, 'update'])->name('update');
        Route::post('avatar', [ProfileController::class, 'avatar'])->name('avatar.store');
        Route::delete('avatar', [ProfileController::class, 'destroyAvatar'])->name('avatar.destroy');
        Route::post('cover', [ProfileController::class, 'cover'])->name('cover.store');
        Route::delete('cover', [ProfileController::class, 'destroyCover'])->name('cover.destroy');
    });

    // --- friends ---
    Route::prefix('friends')->name('friends.')->group(function (): void {
        Route::get('/', [FriendController::class, 'index'])->name('index');
        Route::get('close', [FriendController::class, 'closeFriends'])->name('close');
        Route::delete('{user}', [FriendController::class, 'unfriend'])->name('unfriend');
        Route::post('{user}/follow', [FriendController::class, 'follow'])->name('follow');
        Route::post('{user}/close-friend', [FriendController::class, 'setCloseFriend'])->name('close-friend');

        Route::get('requests', [FriendRequestController::class, 'received'])->name('requests');
        Route::get('sent-requests', [FriendRequestController::class, 'sent'])->name('sent-requests');
        Route::post('request/{user}', [FriendRequestController::class, 'store'])
            ->middleware('throttle:20,1')
            ->name('requests.store');
        Route::put('request/{friend_request}/accept', [FriendRequestController::class, 'accept'])->name('requests.accept');
        Route::put('request/{friend_request}/decline', [FriendRequestController::class, 'decline'])->name('requests.decline');
        Route::delete('request/{friend_request}', [FriendRequestController::class, 'destroy'])->name('requests.destroy');

        Route::get('suggestions', [FriendSuggestionController::class, 'index'])->name('suggestions');
        Route::post('suggestions/{user}/dismiss', [FriendSuggestionController::class, 'dismiss'])->name('suggestions.dismiss');

        Route::get('lists', [FriendController::class, 'lists'])->name('lists');
        Route::post('lists', [FriendController::class, 'storeList'])->name('lists.store');
        Route::put('lists/{list}', [FriendController::class, 'updateList'])->name('lists.update');
        Route::delete('lists/{list}', [FriendController::class, 'destroyList'])->name('lists.destroy');
        Route::post('lists/{list}/members', [FriendController::class, 'addMembers'])->name('lists.members');
    });

    // --- messenger ---
    Route::prefix('conversations')->name('conversations.')->group(function (): void {
        Route::get('/', [ConversationController::class, 'index'])->name('index');
        Route::post('/', [ConversationController::class, 'store'])->name('store');
        Route::get('{conversation}', [ConversationController::class, 'show'])->name('show');
        Route::delete('{conversation}', [ConversationController::class, 'destroy'])->name('destroy');

        Route::get('{conversation}/messages', [ConversationController::class, 'messages'])->name('messages');
        Route::get('{conversation}/search', [ConversationController::class, 'search'])->name('search');
        Route::post('{conversation}/read', [ConversationController::class, 'markRead'])->name('read');
        Route::post('{conversation}/mute', [ConversationController::class, 'mute'])->name('mute');
        Route::post('{conversation}/archive', [ConversationController::class, 'archive'])->name('archive');
        Route::post('{conversation}/pin', [ConversationController::class, 'pin'])->name('pin');
    });

    Route::post('conversations/{conversation}/messages', [MessageController::class, 'store'])->name('messages.store');
    Route::post('conversations/{conversation}/typing', [MessageController::class, 'typing'])
        ->middleware('throttle:60,1')
        ->name('messages.typing');

    Route::delete('messages/{message}', [MessageController::class, 'destroy'])->name('messages.destroy');
    Route::post('messages/{message}/react', [MessageController::class, 'react'])->name('messages.react');
    Route::delete('messages/{message}/react', [MessageController::class, 'unreact'])->name('messages.unreact');
    Route::post('messages/{message}/forward', [MessageController::class, 'forward'])->name('messages.forward');

    Route::prefix('presence')->name('presence.')->group(function (): void {
        Route::post('heartbeat', [PresenceController::class, 'heartbeat'])->name('heartbeat');
        Route::post('disconnect', [PresenceController::class, 'disconnect'])->name('disconnect');
        Route::get('online-friends', [PresenceController::class, 'onlineFriends'])->name('online');
        Route::post('status', [PresenceController::class, 'status'])->name('status');
    });

    // --- stories ---
    Route::prefix('stories')->name('stories.')->group(function (): void {
        Route::get('feed', [StoryController::class, 'feed'])->name('feed');
        Route::post('/', [StoryController::class, 'store'])->name('store');
        Route::get('{story}', [StoryController::class, 'show'])->name('show');
        Route::delete('{story}', [StoryController::class, 'destroy'])->name('destroy');
        Route::post('{story}/view', [StoryController::class, 'view'])->name('view');
        Route::post('{story}/react', [StoryController::class, 'react'])->name('react');
        Route::post('{story}/reply', [StoryController::class, 'reply'])->name('reply');
        Route::get('{story}/viewers', [StoryController::class, 'viewers'])->name('viewers');
    });

    // --- reels ---
    Route::prefix('reels')->name('reels.')->group(function (): void {
        Route::get('feed', [ReelController::class, 'feed'])->name('feed');
        Route::post('/', [ReelController::class, 'store'])->name('store');
        Route::get('{reel}', [ReelController::class, 'show'])->name('show');
        Route::delete('{reel}', [ReelController::class, 'destroy'])->name('destroy');
        Route::post('{reel}/view', [ReelController::class, 'view'])->name('view');
        Route::post('{reel}/share', [ReelController::class, 'share'])->name('share');
        Route::get('{reel}/comments', [ReelController::class, 'comments'])->name('comments');
        Route::post('{reel}/comments', [ReelController::class, 'storeComment'])->name('comments.store');
    });

    // --- groups ---
    Route::prefix('groups')->name('groups.')->group(function (): void {
        Route::get('discover', [GroupController::class, 'discover'])->name('discover');
        Route::get('my-groups', [GroupController::class, 'myGroups'])->name('mine');
        Route::post('/', [GroupController::class, 'store'])->name('store');
        Route::get('{group}', [GroupController::class, 'show'])->name('show');
        Route::put('{group}', [GroupController::class, 'update'])->name('update');
        Route::delete('{group}', [GroupController::class, 'destroy'])->name('destroy');

        Route::get('{group}/posts', [GroupController::class, 'posts'])->name('posts');
        Route::post('{group}/posts', [GroupController::class, 'storePost'])->name('posts.store');
        Route::get('{group}/pending-posts', [GroupController::class, 'pendingPosts'])->name('posts.pending');
        Route::post('{group}/posts/{post}/approve', [GroupController::class, 'approvePost'])->name('posts.approve');
        Route::delete('{group}/posts/{post}', [GroupController::class, 'declinePost'])->name('posts.decline');
        Route::get('{group}/members', [GroupController::class, 'members'])->name('members');
        Route::post('{group}/join', [GroupController::class, 'join'])->name('join');
        Route::delete('{group}/leave', [GroupController::class, 'leave'])->name('leave');

        Route::post('{group}/members/{user}/approve', [GroupMemberController::class, 'approve'])->name('members.approve');
        Route::delete('{group}/members/{user}', [GroupMemberController::class, 'destroy'])->name('members.destroy');
        Route::post('{group}/members/{user}/ban', [GroupMemberController::class, 'ban'])->name('members.ban');
        Route::put('{group}/members/{user}/role', [GroupMemberController::class, 'role'])->name('members.role');
    });

    // --- pages ---
    Route::prefix('pages')->name('pages.')->group(function (): void {
        Route::post('/', [PageController::class, 'store'])->name('store');
        Route::get('{page}', [PageController::class, 'show'])->name('show');
        Route::put('{page}', [PageController::class, 'update'])->name('update');
        Route::delete('{page}', [PageController::class, 'destroy'])->name('destroy');
        Route::get('{page}/posts', [PageController::class, 'posts'])->name('posts');
        Route::post('{page}/posts', [PageController::class, 'storePost'])->name('posts.store');
        Route::post('{page}/follow', [PageController::class, 'follow'])->name('follow');
        Route::delete('{page}/follow', [PageController::class, 'unfollow'])->name('unfollow');
    });

    // --- events ---
    Route::prefix('events')->name('events.')->group(function (): void {
        Route::get('discover', [EventController::class, 'discover'])->name('discover');
        Route::post('/', [EventController::class, 'store'])->name('store');
        Route::get('{event}', [EventController::class, 'show'])->name('show');
        Route::put('{event}', [EventController::class, 'update'])->name('update');
        Route::delete('{event}', [EventController::class, 'destroy'])->name('destroy');
        Route::post('{event}/respond', [EventController::class, 'respond'])->name('respond');
        Route::post('{event}/invite', [EventController::class, 'invite'])->name('invite');
    });

    // --- notifications ---
    Route::prefix('notifications')->name('notifications.')->group(function (): void {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::get('unread-count', [NotificationController::class, 'unreadCount'])->name('unread');
        Route::put('read-all', [NotificationController::class, 'markAllRead'])->name('read-all');
        Route::put('{notification}/read', [NotificationController::class, 'markRead'])->name('read');
        Route::delete('{notification}', [NotificationController::class, 'destroy'])->name('destroy');
        Route::match(['get', 'put'], 'preferences', [NotificationController::class, 'preferences'])->name('preferences');
    });

    // --- search ---
    Route::prefix('search')->name('search.')->group(function (): void {
        Route::get('/', [SearchController::class, 'index'])->name('index');
        Route::get('suggestions', [SearchController::class, 'suggestions'])->name('suggestions');
        Route::get('recent', [SearchController::class, 'recent'])->name('recent');
        Route::delete('recent', [SearchController::class, 'destroyRecent'])->name('recent.destroy');
        Route::delete('recent/{id}', [SearchController::class, 'destroyRecent'])->name('recent.destroy-one');
        Route::get('trending', [SearchController::class, 'trending'])->name('trending');
    });

    // --- media ---
    Route::prefix('media')->name('media.')->group(function (): void {
        Route::post('upload', [MediaUploadController::class, 'upload'])->name('upload');
        Route::post('upload-multiple', [MediaUploadController::class, 'uploadMultiple'])->name('upload-multiple');
        Route::delete('{id}', [MediaUploadController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('albums')->name('albums.')->group(function (): void {
        Route::get('/', [MediaUploadController::class, 'albums'])->name('index');
        Route::post('/', [MediaUploadController::class, 'storeAlbum'])->name('store');
        Route::get('{album}', [MediaUploadController::class, 'showAlbum'])->name('show');
        Route::put('{album}', [MediaUploadController::class, 'updateAlbum'])->name('update');
        Route::delete('{album}', [MediaUploadController::class, 'destroyAlbum'])->name('destroy');
    });

    // --- reports ---
    Route::post('reports', [\App\Http\Controllers\User\ReportController::class, 'store'])->name('reports.store');
    Route::get('reports/reasons', [\App\Http\Controllers\User\ReportController::class, 'reasons'])->name('reports.reasons');
});
