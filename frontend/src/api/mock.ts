import {
  commentsByPost,
  conversations,
  currentUser,
  friendRequests,
  friendsOf,
  groupMembers,
  groupRules,
  groupPosts,
  groups,
  pendingGroupPosts,
  incomingMessage,
  messagesFor,
  notifications,
  posts,
  postById,
  postsByAuthor,
  profiles,
  reelComments,
  reels,
  storyTrays,
  suggestions,
  users,
  onlineFriends,
  trendingTopics,
} from './mock/data';
import type {
  Comment,
  ReactionType,
  Conversation,
  FriendRequest,
  Group,
  GroupDetail,
  GroupMember,
  GroupPrivacy,
  GroupRole,
  GroupType,
  ID,
  Message,
  Notification,
  Paginated,
  Post,
  Profile,
  Reel,
  SearchHistoryItem,
  SearchResults,
  SearchSuggestion,
  SearchTab,
  StoryTray,
  User,
} from '@/types/api';

/**
 * In-browser stand-in for the Laravel API.
 *
 * It exists so the interface is fully explorable without a running backend —
 * the shapes here are identical to the API resources, so swapping
 * `VITE_USE_MOCKS=false` is the only change needed to point at real HTTP.
 *
 * Every method is async with a small artificial latency, because a synchronous
 * mock hides loading states that real users will see.
 */

const latency = (ms = 180): Promise<void> => new Promise((resolve) => setTimeout(resolve, ms));

const clone = <T>(value: T): T => JSON.parse(JSON.stringify(value)) as T;

/** Search history, newest first — the same shape `/search/recent` returns. */
const searchHistory: SearchHistoryItem[] = [
  { id: 'sh1', query: 'Marcus Okafor', last_searched_at: new Date(Date.now() - 40 * 60 * 1000).toISOString() },
  { id: 'sh2', query: 'design systems', last_searched_at: new Date(Date.now() - 5 * 60 * 60 * 1000).toISOString() },
  { id: 'sh3', query: 'sourdough', last_searched_at: new Date(Date.now() - 26 * 60 * 60 * 1000).toISOString() },
];

function rememberSearch(term: string): void {
  const existing = searchHistory.find((item) => item.query.toLowerCase() === term.toLowerCase());

  if (existing) {
    existing.last_searched_at = new Date().toISOString();

    return;
  }

  searchHistory.unshift({ id: `sh-${Date.now()}`, query: term, last_searched_at: new Date().toISOString() });
  searchHistory.splice(10);
}

/** Cover/avatar placeholders for groups created during a session. */
const photo = (seed: string, w = 800, h = 600): string =>
  `https://picsum.photos/seed/${encodeURIComponent(seed)}/${w}/${h}`;

const paginate = <T>(items: T[], page = 1, perPage = 10): Paginated<T> => ({
  data: clone(items.slice((page - 1) * perPage, page * perPage)),
  meta: { total: items.length, current_page: page, per_page: perPage, has_more: page * perPage < items.length },
});

/**
 * Groups created during the session, and the joins/leaves applied to the seeded
 * ones. Kept outside `state` so `state.groups()` can read them.
 */
const createdGroups: Group[] = [];
const groupMembership = new Map<ID, 'member' | 'pending' | 'none'>();

/** Mutable state so writes (new posts, reactions, messages) survive navigation. */
const state = {
  posts: clone(posts),
  conversations: clone(conversations),
  notifications: clone(notifications),
  friendRequests: clone(friendRequests),
  reactions: new Map<string, Post['viewer_reaction']>(),
  reelReactions: new Map<string, Reel['viewer_reaction']>(),
  reelFollows: new Map<string, boolean>(),
  groupPosts: new Map<ID, ID[]>(Object.entries(clone(groupPosts)) as Array<[ID, ID[]]>),
  groupPending: new Map<ID, ID[]>(Object.entries(clone(pendingGroupPosts)) as Array<[ID, ID[]]>),
  groupMemberRoles: new Map<string, GroupRole>(),
  groupMemberStatus: new Map<string, GroupMember['status']>(),
  saved: new Set<ID>(posts.filter((p) => p.is_saved).map((p) => p.id)),

  /**
   * The group list with this session's membership changes folded in, newest
   * first so a freshly created group is immediately visible.
   */
  groups(): Group[] {
    return [...createdGroups, ...groups].map((group) => {
      const override = groupMembership.get(group.id);

      if (!override) return group;

      const isAdmin = group.membership?.is_admin ?? false;

      return {
        ...group,
        members_count: group.members_count + (override === 'member' ? 1 : override === 'none' ? -1 : 0),
        membership: {
          is_member: override === 'member',
          role: isAdmin ? 'admin' : override === 'member' ? 'member' : null,
          is_admin: isAdmin,
        },
      };
    });
  },
};

export const mockApi = {
  // ------------------------------------------------------------------ auth --
  async login(identity: string, password: string): Promise<{ user: User; token: string; token_type: string }> {
    await latency(420);

    if (password.length < 8) {
      throw new Error('The credentials you entered are incorrect.');
    }

    return {
      user: { ...clone(currentUser), email: identity.includes('@') ? identity : `${identity}@example.com` },
      token: 'mock-token',
      token_type: 'Bearer',
    };
  },

  async register(input: {
    first_name: string;
    last_name: string;
    email?: string;
    phone?: string;
  }): Promise<{ user: User; token: string; token_type: string }> {
    await latency(520);

    const user: User = {
      id: 'me',
      name: `${input.first_name} ${input.last_name}`,
      username: `${input.first_name}${input.last_name}`.toLowerCase(),
      first_name: input.first_name,
      last_name: input.last_name,
      email: input.email ?? null,
      avatar_url: null,
      is_online: true,
      last_seen_at: new Date().toISOString(),
    };

    return { user, token: 'mock-token', token_type: 'Bearer' };
  },

  async me(): Promise<{ user: User; unread_notifications: number; unread_messages: number; pending_friend_requests: number }> {
    await latency(120);

    return {
      user: clone(currentUser),
      unread_notifications: state.notifications.filter((n) => !n.is_read).length,
      unread_messages: state.conversations.reduce((sum, c) => sum + c.unread_count, 0),
      pending_friend_requests: state.friendRequests.length,
    };
  },

  // ------------------------------------------------------------------ feed --
  async feed(page = 1, perPage = 5): Promise<Paginated<Post>> {
    await latency(260);

    return paginate(
      state.posts.map((post) => ({ ...post, viewer_reaction: state.reactions.get(post.id) ?? null })),
      page,
      perPage,
    );
  },

  // ----------------------------------------------------------------- posts --
  async createPost(input: { body: string; audience?: string; media?: unknown[] }): Promise<Post> {
    await latency(340);

    const post: Post = {
      id: `local-${Date.now()}`,
      type: input.media && input.media.length > 0 ? 'photo' : 'text',
      body: input.body,
      audience: (input.audience as Post['audience']) ?? 'friends',
      author: clone(currentUser),
      media: [],
      reactions_count: 0,
      comments_count: 0,
      shares_count: 0,
      reaction_summary: {},
      top_reactions: [],
      viewer_reaction: null,
      is_saved: false,
      created_at: new Date().toISOString(),
      can: { edit: true, delete: true, comment: true, share: true },
    };

    state.posts.unshift(post);
    commentsByPost.set(post.id, []);

    return clone(post);
  },

  async post(id: ID): Promise<Post> {
    await latency(140);

    const post = state.posts.find((p) => p.id === id) ?? postById(id);

    if (!post) throw new Error('Post not found.');

    return clone(post);
  },

  async timeline(userId: ID, page = 1): Promise<Paginated<Post>> {
    await latency(200);

    return paginate(postsByAuthor(userId), page, 8);
  },

  async saved(): Promise<Paginated<Post>> {
    await latency(180);

    return paginate(
      state.posts.filter((p) => state.saved.has(p.id)),
      1,
      20,
    );
  },

  async toggleSave(postId: ID): Promise<{ is_saved: boolean }> {
    await latency(90);

    if (state.saved.has(postId)) state.saved.delete(postId);
    else state.saved.add(postId);

    return { is_saved: state.saved.has(postId) };
  },

  // -------------------------------------------------------------- reactions --
  async toggleReaction(targetId: ID, reaction: Post['viewer_reaction']): Promise<{
    reaction: Post['viewer_reaction'] | null;
    summary: Post['reaction_summary'];
    total: number;
  }> {
    await latency(80);

    const post = state.posts.find((p) => p.id === targetId);

    if (!post) return { reaction: null, summary: {}, total: 0 };

    const previous = state.reactions.get(targetId) ?? null;

    const decrement = (type: ReactionType): void => {
      post.reaction_summary = {
        ...post.reaction_summary,
        [type]: Math.max(0, (post.reaction_summary[type] ?? 1) - 1),
      };
    };

    if (previous !== null && previous === reaction) {
      state.reactions.delete(targetId);
      post.reactions_count = Math.max(0, post.reactions_count - 1);
      decrement(previous);
    } else {
      if (previous) {
        decrement(previous);
      } else {
        post.reactions_count += 1;
      }

      state.reactions.set(targetId, reaction);
      post.reaction_summary = {
        ...post.reaction_summary,
        [reaction!]: (post.reaction_summary[reaction!] ?? 0) + 1,
      };
    }

    post.top_reactions = Object.entries(post.reaction_summary)
      .sort((a, b) => (b[1] ?? 0) - (a[1] ?? 0))
      .slice(0, 3)
      .map(([t]) => t as keyof Post['reaction_summary']);

    return {
      reaction: state.reactions.get(targetId) ?? null,
      summary: post.reaction_summary,
      total: post.reactions_count,
    };
  },

  // --------------------------------------------------------------- comments --
  async comments(postId: ID, page = 1): Promise<Paginated<Comment>> {
    await latency(160);

    const thread = (commentsByPost.get(postId) ?? []).filter((c) => c.depth === 0);

    return paginate(thread, page, 20);
  },

  async replies(commentId: ID): Promise<Comment[]> {
    await latency(120);

    for (const thread of commentsByPost.values()) {
      const found = thread.filter((c) => c.parent_id === commentId);

      if (found.length > 0) return clone(found);
    }

    return [];
  },

  async createComment(postId: ID, body: string, parentId: ID | null = null): Promise<Comment> {
    await latency(200);

    const comment: Comment = {
      id: `local-c-${Date.now()}`,
      body,
      author: clone(currentUser),
      post_id: postId,
      parent_id: parentId,
      depth: parentId ? 1 : 0,
      likes_count: 0,
      replies_count: 0,
      viewer_reaction: null,
      created_at: new Date().toISOString(),
    };

    const thread = commentsByPost.get(postId) ?? [];
    thread.push(comment);
    commentsByPost.set(postId, thread);

    const post = state.posts.find((p) => p.id === postId);

    if (post) post.comments_count += 1;

    return clone(comment);
  },

  // ------------------------------------------------------------- messenger --
  async conversations(): Promise<Conversation[]> {
    await latency(180);

    return clone(state.conversations);
  },

  async messages(conversationId: ID): Promise<Message[]> {
    await latency(200);

    return clone(messagesFor(conversationId));
  },

  async sendMessage(conversationId: ID, body: string): Promise<Message> {
    await latency(220);

    const conversation = state.conversations.find((c) => c.id === conversationId);

    if (!conversation) throw new Error('Conversation not found.');

    const message: Message = {
      id: `local-msg-${Date.now()}`,
      conversation_id: conversationId,
      sender_id: currentUser.id,
      sender: clone(currentUser),
      type: 'text',
      body,
      delivery_status: 'sent',
      is_mine: true,
      is_emoji_only: /^[\p{Emoji}\s]+$/u.test(body) && body.trim().length <= 4,
      reactions: [],
      created_at: new Date().toISOString(),
    };

    messagesFor(conversationId).push(message);
    conversation.last_message = message;
    conversation.updated_at = message.created_at;

    // 60% of the time the other person is online and replies.
    if (Math.random() < 0.6) {
      setTimeout(() => {
        incomingMessage(conversationId);
      }, 2200);
    }

    return clone(message);
  },

  async markRead(conversationId: ID): Promise<{ marked_read: number }> {
    await latency(80);

    const conversation = state.conversations.find((c) => c.id === conversationId);

    if (!conversation) return { marked_read: 0 };

    const count = conversation.unread_count;
    conversation.unread_count = 0;

    for (const message of messagesFor(conversationId)) {
      if (message.sender_id !== currentUser.id) message.delivery_status = 'read';
    }

    return { marked_read: count };
  },

  async setTyping(conversationId: ID, typing: boolean): Promise<{ typing: boolean }> {
    const conversation = state.conversations.find((c) => c.id === conversationId);

    if (conversation) {
      conversation.typing_user_ids = typing ? [currentUser.id] : [];
    }

    return { typing };
  },

  // ---------------------------------------------------------------- people --
  async profile(username: string): Promise<{ profile: Profile; friends_preview: User[]; mutual_friends: User[] }> {
    await latency(200);

    const user = users.find((u) => u.username === username) ?? currentUser;
    const profile = profiles.find((p) => p.id === user.id) ?? profiles[0]!;

    return {
      profile: clone({ ...profile, can_edit: user.id === currentUser.id }),
      friends_preview: clone(friendsOf(user.id).slice(0, 9)),
      mutual_friends: user.id === currentUser.id ? [] : clone(friendsOf(user.id).slice(0, 6)),
    };
  },

  async friends(): Promise<User[]> {
    await latency(160);

    return clone(friendsOf(currentUser.id));
  },

  async receivedRequests(): Promise<FriendRequest[]> {
    await latency(140);

    return clone(state.friendRequests);
  },

  async acceptRequest(id: ID): Promise<{ is_friend: boolean }> {
    await latency(160);

    state.friendRequests = state.friendRequests.filter((r) => r.id !== id);

    return { is_friend: true };
  },

  async suggestions(): Promise<User[]> {
    await latency(180);

    return clone(suggestions);
  },

  async search(term: string): Promise<{ people: User[]; posts: Post[] }> {
    await latency(220);

    const needle = term.trim().toLowerCase();

    if (!needle) return { people: [], posts: [] };

    return {
      people: clone(users.filter((u) => u.name.toLowerCase().includes(needle))).slice(0, 8),
      posts: clone(state.posts.filter((p) => (p.body ?? '').toLowerCase().includes(needle))).slice(0, 5),
    };
  },

  async onlineFriends(): Promise<User[]> {
    await latency(120);

    return clone(onlineFriends);
  },

  async trending(): Promise<Array<{ id: string; name: string; posts: number }>> {
    await latency(120);

    return clone(trendingTopics);
  },

  // ---------------------------------------------------------- notifications --
  async notifications(): Promise<Paginated<Notification>> {
    await latency(180);

    return paginate(state.notifications, 1, 30);
  },

  async markAllRead(): Promise<{ marked_read: number }> {
    await latency(120);

    let count = 0;

    for (const notification of state.notifications) {
      if (!notification.is_read) {
        notification.is_read = true;
        count += 1;
      }
    }

    return { marked_read: count };
  },

  // ---------------------------------------------------------------- stories --
  async stories(): Promise<StoryTray[]> {
    await latency(200);

    return clone(storyTrays);
  },

  async viewStory(storyId: ID): Promise<{ viewed: boolean }> {
    for (const tray of storyTrays) {
      const story = tray.stories.find((item) => item.id === storyId);

      if (story) {
        story.views_count += 1;

        return { viewed: true };
      }
    }

    return { viewed: false };
  },

  async reactToStory(_storyId: ID, reaction: string): Promise<{ reacted: boolean; reaction: string }> {
    await latency(120);

    return { reacted: true, reaction };
  },

  async replyToStory(storyId: ID, _body: string): Promise<{ replied: boolean }> {
    await latency(200);

    for (const tray of storyTrays) {
      const story = tray.stories.find((item) => item.id === storyId);

      if (story) {
        story.replies_count = (story.replies_count ?? 0) + 1;

        break;
      }
    }

    return { replied: true };
  },

  // ----------------------------------------------------------------- reels --
  async reelsFeed(page = 1, perPage = 5): Promise<Paginated<Reel>> {
    await latency(240);

    const page1 = reels
      .slice((page - 1) * perPage, page * perPage)
      .map((reel) => ({ ...clone(reel), viewer_reaction: state.reelReactions.get(reel.id) ?? null }));

    return {
      data: page1.map((reel) => ({
        ...reel,
        creator: { ...reel.creator, is_following: state.reelFollows.get(reel.creator.id) ?? reel.creator.is_following },
      })),
      meta: { total: reels.length, current_page: page, per_page: perPage, has_more: page * perPage < reels.length },
    };
  },

  async viewReel(
    reelId: ID,
    watchedSeconds: number,
  ): Promise<{ views_count: number; watched_seconds: number }> {
    const reel = reels.find((item) => item.id === reelId);

    if (reel) reel.views_count += 1;

    return { views_count: reel?.views_count ?? 0, watched_seconds: watchedSeconds };
  },

  async reactToReel(reelId: ID, reaction: ReactionType | null): Promise<{ likes_count: number; viewer_reaction: ReactionType | null }> {
    await latency(120);

    const reel = reels.find((item) => item.id === reelId);

    if (!reel) return { likes_count: 0, viewer_reaction: null };

    const previous = state.reelReactions.get(reelId) ?? null;

    // Toggling a reaction on: count it. Toggling off: remove it. Switching
    // from one reaction to another leaves the count alone.
    if (previous === null && reaction !== null) reel.likes_count += 1;
    if (previous !== null && reaction === null) reel.likes_count = Math.max(0, reel.likes_count - 1);

    if (reaction === null) state.reelReactions.delete(reelId);
    else state.reelReactions.set(reelId, reaction);

    return { likes_count: reel.likes_count, viewer_reaction: reaction };
  },

  async shareReel(reelId: ID): Promise<{ shares_count: number }> {
    await latency(180);

    const reel = reels.find((item) => item.id === reelId);

    if (reel) reel.shares_count += 1;

    return { shares_count: reel?.shares_count ?? 0 };
  },

  async reelComments(reelId: ID): Promise<Paginated<Comment>> {
    await latency(180);

    return paginate(reelComments.get(reelId) ?? [], 1, 50);
  },

  async createReelComment(reelId: ID, body: string): Promise<Comment> {
    await latency(260);

    const comment: Comment = {
      id: `rc-${reelId}-${Date.now()}`,
      body,
      author: clone(currentUser),
      parent_id: null,
      depth: 0,
      likes_count: 0,
      replies_count: 0,
      viewer_reaction: null,
      created_at: new Date().toISOString(),
    };

    const thread = reelComments.get(reelId) ?? [];
    thread.push(comment);
    reelComments.set(reelId, thread);

    const reel = reels.find((item) => item.id === reelId);

    if (reel) reel.comments_count += 1;

    return comment;
  },

  // ---------------------------------------------------------------- groups --
  async groups(): Promise<Paginated<Group>> {
    await latency(220);

    return paginate(state.groups(), 1, 50);
  },

  async myGroups(): Promise<Group[]> {
    await latency(200);

    return state.groups().filter((group) => group.membership?.is_member);
  },

  async group(idOrSlug: ID): Promise<GroupDetail> {
    await latency(220);

    const group = state.groups().find((item) => item.id === idOrSlug || item.slug === idOrSlug);

    if (!group) throw new Error('That group does not exist.');

    const admins = (groupMembers[group.id] ?? [])
      .filter((member) => member.role !== 'member')
      .map((member) => member.user);

    return { group, rules: groupRules[group.id] ?? [], admins: admins.length > 0 ? admins : [currentUser] };
  },

  async groupPosts(groupId: ID, page = 1): Promise<Paginated<Post>> {
    await latency(240);

    const ids = state.groupPosts.get(groupId) ?? [];

    return paginate(
      ids
        .map((id) => state.posts.find((post) => post.id === id))
        .filter((post): post is Post => Boolean(post))
        .map((post) => ({ ...post, viewer_reaction: state.reactions.get(post.id) ?? null })),
      page,
      10,
    );
  },

  async pendingGroupPosts(groupId: ID): Promise<Paginated<Post>> {
    await latency(200);

    const ids = state.groupPending.get(groupId) ?? [];

    return paginate(
      ids.map((id) => state.posts.find((post) => post.id === id)).filter((post): post is Post => Boolean(post)),
      1,
      20,
    );
  },

  async createGroupPost(groupId: ID, body: string): Promise<Post> {
    await latency(320);

    const post: Post = {
      id: `gp-${groupId}-${Date.now()}`,
      author: clone(currentUser),
      type: 'text',
      body,
      audience: 'public',
      media: [],
      reactions_count: 0,
      comments_count: 0,
      shares_count: 0,
      viewer_reaction: null,
      reaction_summary: {},
      top_reactions: [],
      is_saved: false,
      created_at: new Date().toISOString(),
      can: { edit: true, delete: true, comment: true, share: true },
    };

    state.posts.unshift(post);

    // The demo account is an admin of Kigali Founders, so its posts appear
    // straight away; everywhere else they wait in the queue.
    const group = state.groups().find((item) => item.id === groupId);
    const queue = group?.membership?.is_admin ? 'approved' : 'pending';

    if (queue === 'approved') {
      state.groupPosts.set(groupId, [post.id, ...(state.groupPosts.get(groupId) ?? [])]);
    } else {
      state.groupPending.set(groupId, [post.id, ...(state.groupPending.get(groupId) ?? [])]);
    }

    return post;
  },

  async approveGroupPost(groupId: ID, postId: ID): Promise<{ approved: boolean }> {
    await latency(180);

    state.groupPending.set(
      groupId,
      (state.groupPending.get(groupId) ?? []).filter((id) => id !== postId),
    );
    state.groupPosts.set(groupId, [postId, ...(state.groupPosts.get(groupId) ?? [])]);

    return { approved: true };
  },

  async declineGroupPost(groupId: ID, postId: ID): Promise<{ declined: boolean }> {
    await latency(180);

    state.groupPending.set(
      groupId,
      (state.groupPending.get(groupId) ?? []).filter((id) => id !== postId),
    );

    return { declined: true };
  },

  async groupMembers(groupId: ID, status?: string): Promise<Paginated<GroupMember>> {
    await latency(200);

    const members = (groupMembers[groupId] ?? []).map((member) => ({
      ...member,
      role: state.groupMemberRoles.get(`${groupId}:${member.user.id}`) ?? member.role,
      status: state.groupMemberStatus.get(`${groupId}:${member.user.id}`) ?? member.status,
    }));

    return paginate(status ? members.filter((member) => member.status === status) : members, 1, 50);
  },

  async joinGroup(groupId: ID): Promise<{ status: 'active' | 'pending' }> {
    await latency(240);

    const group = state.groups().find((item) => item.id === groupId);

    // Public groups join immediately; private ones queue for an admin.
    const status = group?.privacy === 'public' ? 'active' : 'pending';

    groupMembership.set(groupId, status === 'active' ? 'member' : 'pending');

    return { status };
  },

  async leaveGroup(groupId: ID): Promise<{ left: boolean }> {
    await latency(200);

    groupMembership.set(groupId, 'none');

    return { left: true };
  },

  async createGroup(input: {
    name: string;
    description?: string;
    privacy: GroupPrivacy;
    type: GroupType;
  }): Promise<Group> {
    await latency(420);

    const slug = input.name
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/(^-|-$)/g, '');

    const group: Group = {
      id: `g-${Date.now()}`,
      name: input.name,
      slug,
      description: input.description ?? null,
      type: input.type,
      privacy: input.privacy,
      cover_url: photo(`group-cover-${slug}`, 1200, 400),
      avatar_url: photo(`group-avatar-${slug}`, 200, 200),
      topics: [],
      members_count: 1,
      posts_count: 0,
      membership: { is_member: true, role: 'admin', is_admin: true },
      created_at: new Date().toISOString(),
    };

    createdGroups.unshift(group);
    groupMembership.set(group.id, 'member');
    state.groupPosts.set(group.id, []);
    state.groupPending.set(group.id, []);

    return group;
  },

  async setGroupMemberRole(groupId: ID, userId: ID, role: GroupRole): Promise<{ role: GroupRole }> {
    await latency(180);

    state.groupMemberRoles.set(`${groupId}:${userId}`, role);

    return { role };
  },

  async removeGroupMember(groupId: ID, userId: ID): Promise<{ removed: boolean }> {
    await latency(180);

    state.groupMemberStatus.set(`${groupId}:${userId}`, 'banned');

    return { removed: true };
  },

  // ---------------------------------------------------------------- search --
  async searchAll(term: string, type: SearchTab = 'all'): Promise<SearchResults> {
    await latency(220);

    const needle = term.trim().toLowerCase();

    if (!needle) return { people: [], posts: [], groups: [] };

    const people = users.filter((user) => user.name.toLowerCase().includes(needle));
    const matchingPosts = state.posts.filter((post) => (post.body ?? '').toLowerCase().includes(needle));
    const matchingGroups = state
      .groups()
      .filter((group) => group.name.toLowerCase().includes(needle) || (group.description ?? '').toLowerCase().includes(needle));

    if (term.trim()) rememberSearch(term.trim());

    return {
      people: type === 'all' || type === 'people' ? clone(people).slice(0, type === 'all' ? 5 : 20) : [],
      posts: type === 'all' || type === 'posts' ? clone(matchingPosts).slice(0, type === 'all' ? 5 : 20) : [],
      groups: type === 'all' || type === 'groups' ? clone(matchingGroups).slice(0, type === 'all' ? 5 : 20) : [],
    };
  },

  async searchSuggestions(term: string): Promise<SearchSuggestion[]> {
    await latency(120);

    const needle = term.trim().toLowerCase();

    if (needle.length < 2) return [];

    const people: SearchSuggestion[] = users
      .filter((user) => user.name.toLowerCase().includes(needle))
      .slice(0, 5)
      .map((user) => ({
        id: user.id,
        type: 'user',
        title: user.name,
        username: user.username,
        subtitle: `@${user.username ?? user.id}`,
        avatar_url: user.avatar_url,
      }));

    const groups: SearchSuggestion[] = state
      .groups()
      .filter((group) => group.name.toLowerCase().includes(needle))
      .slice(0, 3)
      .map((group) => ({
        id: group.id,
        type: 'group',
        title: group.name,
        subtitle: `${group.members_count.toLocaleString()} members`,
        avatar_url: group.avatar_url,
      }));

    return [...people, ...groups].slice(0, 8);
  },

  async recentSearches(): Promise<SearchHistoryItem[]> {
    await latency(100);

    return clone(searchHistory);
  },

  async clearRecentSearches(id?: ID): Promise<{ deleted: number }> {
    await latency(120);

    const before = searchHistory.length;

    if (id) {
      const index = searchHistory.findIndex((item) => item.id === id);

      if (index >= 0) searchHistory.splice(index, 1);
    } else {
      searchHistory.length = 0;
    }

    return { deleted: before - searchHistory.length };
  },

  async setFollowing(userId: ID, following: boolean): Promise<{ is_following: boolean }> {
    await latency(160);

    state.reelFollows.set(userId, following);

    return { is_following: following };
  },
};

export type MockApi = typeof mockApi;
