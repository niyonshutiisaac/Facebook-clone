<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationType: string
{
    case FriendRequestReceived = 'friend_request_received';
    case FriendRequestAccepted = 'friend_request_accepted';
    case PostReacted = 'post_reacted';
    case PostCommented = 'post_commented';
    case CommentReplied = 'comment_replied';
    case CommentReacted = 'comment_reacted';
    case TaggedInPost = 'tagged';
    case TaggedInComment = 'tagged_comment';
    case MentionedInPost = 'mentioned_post';
    case MentionedInComment = 'mentioned_comment';
    case PostShared = 'post_shared';
    case BirthdayReminder = 'birthday';
    case GroupActivity = 'group_activity';
    case PageActivity = 'page_activity';
    case EventReminder = 'event_reminder';
    case StoryReacted = 'story_reacted';
    case StoryReplied = 'story_replied';
    case MemoryAvailable = 'memory';
    case LiveVideoStarted = 'live_started';
    case NewMessage = 'new_message';

    public function label(): string
    {
        return match ($this) {
            self::FriendRequestReceived => 'Friend requests',
            self::FriendRequestAccepted => 'Friend request accepted',
            self::PostReacted, self::CommentReacted => 'Reactions',
            self::PostCommented, self::CommentReplied => 'Comments',
            self::TaggedInPost, self::TaggedInComment => 'Tags',
            self::MentionedInPost, self::MentionedInComment => 'Mentions',
            self::PostShared => 'Shares',
            self::BirthdayReminder => 'Birthdays',
            self::GroupActivity => 'Group activity',
            self::PageActivity => 'Page activity',
            self::EventReminder => 'Events',
            self::StoryReacted, self::StoryReplied => 'Stories',
            self::MemoryAvailable => 'Memories',
            self::LiveVideoStarted => 'Live videos',
            self::NewMessage => 'Messages',
        };
    }
}
