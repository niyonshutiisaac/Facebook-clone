/**
 * Types mirroring the Laravel API resources one-to-one.
 *
 * Keeping these generated from (and named after) the backend payloads is what
 * makes the `api/` layer a thin passthrough: when the shape changes, this file
 * is the only thing that has to change with it.
 */

export type ID = string;

export type ReactionType = 'like' | 'love' | 'care' | 'haha' | 'wow' | 'sad' | 'angry';

export type Audience = 'public' | 'friends' | 'friends_except' | 'specific' | 'only_me';

export type PostType =
  | 'text'
  | 'photo'
  | 'video'
  | 'link'
  | 'poll'
  | 'feeling'
  | 'check_in'
  | 'life_event'
  | 'shared'
  | 'avatar_update'
  | 'cover_update';

export type MessageType = 'text' | 'image' | 'video' | 'audio' | 'file' | 'gif' | 'sticker' | 'system';

export type DeliveryStatus = 'sending' | 'sent' | 'delivered' | 'read' | 'failed';

export type RelationshipStatus =
  | 'single'
  | 'in_a_relationship'
  | 'engaged'
  | 'married'
  | 'its_complicated'
  | 'separated'
  | 'divorced'
  | 'widowed';

// ------------------------------------------------------------------ media --

export interface Media {
  id: ID;
  type: 'image' | 'video' | 'gif' | 'audio';
  url: string;
  thumbnail_url?: string | null;
  poster_url?: string | null;
  width?: number | null;
  height?: number | null;
  duration?: number | null;
  alt_text?: string | null;
  mime_type?: string | null;
  size?: number | null;
}

export interface LinkPreview {
  id?: ID;
  url: string;
  domain: string;
  title?: string | null;
  description?: string | null;
  image_url?: string | null;
  favicon_url?: string | null;
  site_name?: string | null;
}

// ------------------------------------------------------------------ users --

export interface Relationship {
  is_friend: boolean;
  is_following: boolean;
  mutual_friends_count: number;
  request_sent: boolean;
  request_received: boolean;
}

export interface User {
  id: ID;
  name: string;
  username?: string | null;
  first_name?: string;
  last_name?: string;
  email?: string | null;
  avatar_url: string | null;
  cover_url?: string | null;
  is_verified?: boolean;
  is_online?: boolean;
  last_seen_at?: string | null;
  /** Present only when someone other than the account owner is looking. */
  relationship?: Relationship;
}

export interface WorkEntry {
  company: string;
  position?: string;
  from?: string;
  to?: string | null;
  description?: string;
}

export interface EducationEntry {
  school: string;
  degree?: string;
  from?: string;
  to?: string | null;
  description?: string;
}

export interface PlaceEntry {
  city: string;
  type: 'current' | 'hometown' | 'previous';
}

export interface ProfileContactInfo {
  email?: string | null;
  phone?: string | null;
  website?: string | null;
  address?: string | null;
}

export interface ProfileBasicInfo {
  birth_date?: string | null;
  gender?: string | null;
  pronouns?: string | null;
  relationship?: RelationshipStatus | null;
  languages?: string[];
}

export interface Profile extends User {
  headline?: string | null;
  bio?: string | null;
  about?: string | null;
  current_city?: string | null;
  hometown?: string | null;
  relationship_status?: RelationshipStatus | null;
  work?: WorkEntry[];
  education?: EducationEntry[];
  places_lived?: PlaceEntry[];
  contact_info?: ProfileContactInfo | null;
  basic_info?: ProfileBasicInfo | null;
  favorite_quotes?: string | null;
  friends_count: number;
  followers_count?: number;
  photos_count?: number;
  posts_count?: number;
  /** True when the viewer owns this profile. */
  can_edit?: boolean;
}

// ------------------------------------------------------------------ posts --

export interface PollOption {
  id: ID;
  label: string;
  votes_count: number;
  percentage: number;
  voted_by_viewer?: boolean;
}

export interface Poll {
  id: ID;
  question: string;
  options: PollOption[];
  total_votes: number;
  voters_count: number;
  allows_multiple: boolean;
  closes_at?: string | null;
}

export interface Feeling {
  id: ID;
  emoji: string;
  label: string;
}

export interface Post {
  id: ID;
  type: PostType;
  body: string | null;
  body_html?: string | null;
  audience: Audience;
  author: User;
  media: Media[];
  link?: { url: string; domain: string; preview?: LinkPreview | null } | null;
  poll?: Poll | null;
  feelings?: Feeling[] | null;
  shared_post?: Post | null;
  background?: { slug: string; from: string; to: string; text_color: string } | null;
  location?: string | null;
  group?: { id: ID; name: string; avatar_url: string | null } | null;
  page?: { id: ID; name: string; avatar_url: string | null } | null;
  reactions_count: number;
  comments_count: number;
  shares_count: number;
  views_count?: number;
  reaction_summary: Partial<Record<ReactionType, number>>;
  top_reactions: ReactionType[];
  viewer_reaction?: ReactionType | null;
  is_saved?: boolean;
  is_pinned?: boolean;
  is_edited?: boolean;
  created_at: string;
  updated_at?: string;
  can?: {
    edit?: boolean;
    delete?: boolean;
    comment?: boolean;
    share?: boolean;
  };
}

export interface Comment {
  id: ID;
  body: string;
  author: User;
  post_id?: ID;
  parent_id?: ID | null;
  depth: number;
  likes_count: number;
  replies_count: number;
  viewer_reaction?: ReactionType | null;
  media?: Media | null;
  replies?: Comment[];
  is_deleted?: boolean;
  created_at: string;
  updated_at?: string;
}

// ------------------------------------------------------------- messenger --

export interface Message {
  id: ID;
  conversation_id: ID;
  sender_id: ID;
  sender?: User;
  type: MessageType;
  body: string | null;
  media?: Media | null;
  reply_to?: { id: ID; body: string | null; sender_name?: string } | null;
  delivery_status: DeliveryStatus;
  is_mine?: boolean;
  is_deleted?: boolean;
  is_deleted_for_me?: boolean;
  is_emoji_only?: boolean;
  read_by?: Array<{ id: ID; read_at: string }>;
  reactions?: Array<{ user_id: ID; reaction: ReactionType }>;
  created_at: string;
  /** Client-only: set while the optimistic message is in flight. */
  pending?: boolean;
}

export interface Conversation {
  id: ID;
  type: 'private' | 'group';
  title?: string | null;
  avatar_url?: string | null;
  participants: User[];
  /** Resolved display title for private threads (the other person's name). */
  display_name: string;
  display_avatar: string | null;
  last_message?: Message | null;
  unread_count: number;
  is_muted?: boolean;
  is_pinned?: boolean;
  is_archived?: boolean;
  typing_user_ids?: ID[];
  updated_at: string;
}

// --------------------------------------------------------- notifications --

export interface Notification {
  id: ID;
  type: string;
  /** Pre-built sentence, e.g. "Ava and 4 others reacted to your post." */
  text: string;
  icon: string;
  actor?: User | null;
  actors?: User[];
  subject_preview?: string | null;
  target_url?: string | null;
  is_read: boolean;
  group_count: number;
  created_at: string;
}

// ------------------------------------------------------------ friendships --

export interface FriendRequest {
  id: ID;
  sender: User;
  recipient?: User;
  message?: string | null;
  status: 'pending' | 'accepted' | 'declined' | 'cancelled';
  mutual_friends_count: number;
  created_at: string;
}

// ---------------------------------------------------------------- stories --

export interface Story {
  id: ID;
  author: User;
  type: 'photo' | 'video' | 'text';
  body?: string | null;
  background?: string | null;
  media?: Media | null;
  has_unseen?: boolean;
  views_count: number;
  reactions_count?: number;
  replies_count?: number;
  created_at: string;
  expires_at: string;
}

export interface StoryTray {
  user: User;
  stories: Story[];
  has_unseen: boolean;
  latest_at: string;
}

// ------------------------------------------------------------------ reels --

export interface ReelAudio {
  id: ID;
  title: string;
  artist?: string | null;
  artwork_url?: string | null;
}

/**
 * A reel's creator. The follow flag rides along so the player can render the
 * "Follow" / "Following" pill without a second round trip per card.
 */
export interface ReelCreator extends User {
  is_following?: boolean;
}

export interface Reel {
  id: ID;
  url: string;
  poster_url: string | null;
  caption: string | null;
  /** Seconds. */
  duration: number;
  width: number | null;
  height: number | null;
  audio: ReelAudio | null;
  creator: ReelCreator;
  views_count: number;
  likes_count: number;
  comments_count: number;
  shares_count: number;
  viewer_reaction: ReactionType | null;
  allows_remix: boolean;
  created_at: string;
}

// ----------------------------------------------------------------- groups --

export type GroupPrivacy = 'public' | 'private_visible' | 'private_hidden';
export type GroupType = 'general' | 'social_learning' | 'buy_and_sell';
export type GroupRole = 'member' | 'moderator' | 'admin';
export type GroupMemberStatus = 'active' | 'pending' | 'banned';

export interface GroupMembership {
  is_member: boolean;
  role: GroupRole | null;
  is_admin: boolean;
}

export interface Group {
  id: ID;
  name: string;
  slug: string;
  description: string | null;
  type: GroupType;
  privacy: GroupPrivacy;
  cover_url: string | null;
  avatar_url: string | null;
  topics: string[] | null;
  members_count: number;
  posts_count: number;
  /** Null only when the viewer is a guest; groups always resolve otherwise. */
  membership: GroupMembership | null;
  created_at: string;
}

export interface GroupMember {
  id: ID;
  user: User;
  role: GroupRole;
  status: GroupMemberStatus;
  badge: string | null;
  joined_at: string | null;
}

export interface GroupRule {
  id: ID;
  title: string;
  description: string | null;
}

export interface GroupDetail {
  group: Group;
  rules: GroupRule[];
  admins: User[];
}

// ----------------------------------------------------------------- search --

export type SearchTab = 'all' | 'people' | 'posts' | 'groups';

export interface SearchSuggestion {
  id: ID;
  type: 'user' | 'page' | 'group' | 'event';
  title: string;
  subtitle: string | null;
  avatar_url: string | null;
  /** Present on people: profiles are addressed by username, not by id. */
  username?: string | null;
}

export interface SearchHistoryItem {
  id: ID;
  query: string;
  last_searched_at: string;
}

export interface TrendingTopic {
  id: ID;
  name: string;
  posts: number;
}

/**
 * One bucket per tab. Pages and events are part of the API but have no surface
 * in this build yet, so the client requests — and renders — only the tabs it
 * can actually show.
 */
export interface SearchResults {
  people: User[];
  posts: Post[];
  groups: Group[];
}

// -------------------------------------------------------------- responses --

export interface Paginated<T> {
  data: T[];
  meta?: {
    cursor?: string | null;
    next_cursor?: string | null;
    has_more?: boolean;
    total?: number;
    current_page?: number;
    per_page?: number;
  };
}

export interface AuthPayload {
  user: User & { email?: string };
  token: string;
  token_type: string;
  expires_in?: number;
  settings?: Record<string, unknown>;
  unread_notifications?: number;
  unread_messages?: number;
  pending_friend_requests?: number;
}
