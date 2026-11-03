import {
  useInfiniteQuery,
  useMutation,
  useQuery,
  useQueryClient,
  type InfiniteData,
} from '@tanstack/react-query';
import { mockApi, USE_MOCKS, api } from './client';
import type {
  Comment,
  Conversation,
  FriendRequest,
  ID,
  Message,
  Notification,
  Paginated,
  Post,
  Group,
  GroupDetail,
  GroupMember,
  GroupPrivacy,
  GroupRole,
  GroupType,
  Profile,
  ReactionType,
  Reel,
  SearchHistoryItem,
  SearchResults,
  SearchSuggestion,
  SearchTab,
  StoryTray,
  User,
} from '@/types/api';

/**
 * The data layer.
 *
 * Every hook mirrors one endpoint and one cache key. Call sites never touch
 * axios or the mock directly, so switching between the two is a matter of the
 * `VITE_USE_MOCKS` flag rather than a rewrite.
 */

export const queryKeys = {
  feed: ['feed'] as const,
  post: (id: ID) => ['post', id] as const,
  timeline: (userId: ID) => ['timeline', userId] as const,
  comments: (postId: ID) => ['comments', postId] as const,
  replies: (commentId: ID) => ['replies', commentId] as const,
  conversations: ['conversations'] as const,
  messages: (conversationId: ID) => ['messages', conversationId] as const,
  notifications: ['notifications'] as const,
  profile: (username: string) => ['profile', username] as const,
  friends: ['friends'] as const,
  requests: ['friend-requests'] as const,
  suggestions: ['suggestions'] as const,
  stories: ['stories'] as const,
  reels: ['reels'] as const,
  reelComments: (reelId: ID) => ['reel-comments', reelId] as const,
  groups: ['groups'] as const,
  myGroups: ['groups', 'mine'] as const,
  group: (idOrSlug: ID) => ['group', idOrSlug] as const,
  groupPosts: (groupId: ID) => ['group-posts', groupId] as const,
  pendingGroupPosts: (groupId: ID) => ['group-posts', groupId, 'pending'] as const,
  groupMembers: (groupId: ID) => ['group-members', groupId] as const,
  saved: ['saved'] as const,
  search: (term: string, tab?: SearchTab) => ['search', term, tab ?? 'all'] as const,
  searchSuggestions: (term: string) => ['search-suggestions', term] as const,
  recentSearches: ['search-recent'] as const,
  onlineFriends: ['online-friends'] as const,
  trending: ['trending'] as const,
};

// -------------------------------------------------------------------- feed --

export function useFeed() {
  return useInfiniteQuery({
    queryKey: queryKeys.feed,
    initialPageParam: 1,
    queryFn: async ({ pageParam }) => {
      if (USE_MOCKS) return mockApi.feed(pageParam, 5);

      const { data } = await api.get<Paginated<Post>>('/feed', { params: { page: pageParam, limit: 10 } });

      return data;
    },
    getNextPageParam: (lastPage, pages) => {
      const loaded = pages.reduce((sum, page) => sum + page.data.length, 0);

      return lastPage.meta?.has_more && loaded < (lastPage.meta?.total ?? 0) ? pages.length + 1 : undefined;
    },
  });
}

export function usePost(id: ID) {
  return useQuery({
    queryKey: queryKeys.post(id),
    enabled: Boolean(id),
    queryFn: async () => {
      if (USE_MOCKS) return mockApi.post(id);

      const { data } = await api.get<Post>(`/posts/${id}`);

      return data;
    },
  });
}

export function useTimeline(userId: ID) {
  return useQuery({
    queryKey: queryKeys.timeline(userId),
    enabled: Boolean(userId),
    queryFn: async () => {
      if (USE_MOCKS) return mockApi.timeline(userId);

      const { data } = await api.get<Paginated<Post>>(`/users/${userId}/timeline`);

      return data;
    },
  });
}

// ------------------------------------------------------------------ composer --

export function useCreatePost() {
  const client = useQueryClient();

  return useMutation({
    mutationFn: async (input: { body: string; audience?: string }) => {
      if (USE_MOCKS) return mockApi.createPost(input);

      const { data } = await api.post<Post>('/posts', input);

      return data;
    },
    onSuccess: (post) => {
      client.setQueryData<InfiniteData<Paginated<Post>>>(queryKeys.feed, (old) => {
        if (!old) return old;

        return {
          ...old,
          pages: old.pages.map((page, index) =>
            index === 0 ? { ...page, data: [post, ...page.data] } : page,
          ),
        };
      });
    },
  });
}

// ---------------------------------------------------------------- reactions --

export function useToggleReaction() {
  const client = useQueryClient();

  return useMutation({
    mutationFn: async (variables: { targetType: 'post' | 'comment' | 'message' | 'story'; targetId: ID; reaction: string }) => {
      if (USE_MOCKS) {
        return mockApi.toggleReaction(variables.targetId, variables.reaction as Post['viewer_reaction']);
      }

      const { data } = await api.put<{
        reaction: Post['viewer_reaction'] | null;
        summary: Post['reaction_summary'];
        total: number;
      }>(`/${variables.targetType}/${variables.targetId}/reactions`, { type: variables.reaction });

      return data;
    },

    // Optimistically flip the reaction in the feed cache: a like that takes
    // 300ms to appear feels broken.
    onMutate: async (variables) => {
      if (variables.targetType !== 'post') return;

      await client.cancelQueries({ queryKey: queryKeys.feed });

      const previous = client.getQueryData<InfiniteData<Paginated<Post>>>(queryKeys.feed);

      client.setQueryData<InfiniteData<Paginated<Post>>>(queryKeys.feed, (old) => {
        if (!old) return old;

        return {
          ...old,
          pages: old.pages.map((page) => ({
            ...page,
            data: page.data.map((post) => {
              if (post.id !== variables.targetId) return post;

              const isSame = post.viewer_reaction === variables.reaction;
              const next = isSame ? null : (variables.reaction as Post['viewer_reaction']);

              return {
                ...post,
                viewer_reaction: next,
                reactions_count: Math.max(0, post.reactions_count + (isSame ? -1 : next ? 1 : 0)),
              };
            }),
          })),
        };
      });

      return { previous };
    },

    onError: (_error, _variables, context) => {
      if (context?.previous) client.setQueryData(queryKeys.feed, context.previous);
    },

    onSettled: () => {
      void client.invalidateQueries({ queryKey: queryKeys.feed });
    },
  });
}

// ------------------------------------------------------------------ comments --

export function useComments(postId: ID) {
  return useInfiniteQuery({
    queryKey: queryKeys.comments(postId),
    enabled: Boolean(postId),
    initialPageParam: 1,
    queryFn: async ({ pageParam }) => {
      if (USE_MOCKS) return mockApi.comments(postId, pageParam);

      const { data } = await api.get<Paginated<Comment>>(`/posts/${postId}/comments`, { params: { page: pageParam } });

      return data;
    },
    getNextPageParam: (lastPage, pages) =>
      lastPage.meta?.has_more ? pages.length + 1 : undefined,
  });
}

export function useReplies(commentId: ID, enabled = true) {
  return useQuery({
    queryKey: queryKeys.replies(commentId),
    enabled: enabled && Boolean(commentId),
    queryFn: async () => {
      if (USE_MOCKS) return mockApi.replies(commentId);

      const { data } = await api.get<Comment[]>(`/comments/${commentId}/replies`);

      return data;
    },
  });
}

export function useCreateComment(postId: ID) {
  const client = useQueryClient();

  return useMutation({
    mutationFn: async (input: { body: string; parentId?: ID | null }) => {
      if (USE_MOCKS) return mockApi.createComment(postId, input.body, input.parentId ?? null);

      const url = input.parentId ? `/comments/${input.parentId}/replies` : `/posts/${postId}/comments`;
      const { data } = await api.post<Comment>(url, { body: input.body, post_id: postId });

      return data;
    },
    onSuccess: () => {
      void client.invalidateQueries({ queryKey: queryKeys.comments(postId) });
      void client.invalidateQueries({ queryKey: queryKeys.feed });
    },
  });
}

export function useToggleSave() {
  const client = useQueryClient();

  return useMutation({
    mutationFn: async (postId: ID) => {
      if (USE_MOCKS) return mockApi.toggleSave(postId);

      const { data } = await api.post<{ is_saved: boolean }>(`/posts/${postId}/save`);

      return data;
    },
    onSuccess: () => {
      void client.invalidateQueries({ queryKey: queryKeys.saved });
      void client.invalidateQueries({ queryKey: queryKeys.feed });
    },
  });
}

// ---------------------------------------------------------------- messenger --

export function useConversations() {
  return useQuery({
    queryKey: queryKeys.conversations,
    queryFn: async () => {
      if (USE_MOCKS) return mockApi.conversations();

      const { data } = await api.get<Conversation[]>('/conversations');

      return data;
    },
    refetchInterval: USE_MOCKS ? 15_000 : false,
  });
}

export function useMessages(conversationId: ID) {
  return useQuery({
    queryKey: queryKeys.messages(conversationId),
    enabled: Boolean(conversationId),
    queryFn: async () => {
      if (USE_MOCKS) return mockApi.messages(conversationId);

      const { data } = await api.get<Message[]>(`/conversations/${conversationId}/messages`);

      return data;
    },
    refetchInterval: USE_MOCKS ? 4_000 : false,
  });
}

export function useSendMessage(conversationId: ID) {
  const client = useQueryClient();

  return useMutation({
    mutationFn: async (body: string) => {
      if (USE_MOCKS) return mockApi.sendMessage(conversationId, body);

      const { data } = await api.post<Message>(`/conversations/${conversationId}/messages`, { body });

      return data;
    },

    onMutate: async (body) => {
      await client.cancelQueries({ queryKey: queryKeys.messages(conversationId) });

      const previous = client.getQueryData<Message[]>(queryKeys.messages(conversationId));

      const optimistic: Message = {
        id: `optimistic-${Date.now()}`,
        conversation_id: conversationId,
        sender_id: 'me',
        type: 'text',
        body,
        delivery_status: 'sending',
        is_mine: true,
        created_at: new Date().toISOString(),
        pending: true,
      };

      client.setQueryData<Message[]>(queryKeys.messages(conversationId), (old) => [...(old ?? []), optimistic]);

      return { previous };
    },

    onError: (_error, _body, context) => {
      if (context?.previous) client.setQueryData(queryKeys.messages(conversationId), context.previous);
    },

    onSuccess: () => {
      void client.invalidateQueries({ queryKey: queryKeys.messages(conversationId) });
      void client.invalidateQueries({ queryKey: queryKeys.conversations });
    },
  });
}

export function useMarkRead() {
  const client = useQueryClient();

  return useMutation({
    mutationFn: async (conversationId: ID) => {
      if (USE_MOCKS) return mockApi.markRead(conversationId);

      const { data } = await api.post<{ marked_read: number }>(`/conversations/${conversationId}/read`);

      return data;
    },
    onSuccess: () => {
      void client.invalidateQueries({ queryKey: queryKeys.conversations });
    },
  });
}

export function useTypingIndicator(conversationId: ID) {
  return useMutation({
    mutationFn: async (typing: boolean) => {
      if (USE_MOCKS) return mockApi.setTyping(conversationId, typing);

      const { data } = await api.post<{ typing: boolean }>(`/conversations/${conversationId}/typing`, { typing });

      return data;
    },
  });
}

// -------------------------------------------------------------------- people --

export function useProfile(username: string) {
  return useQuery({
    queryKey: queryKeys.profile(username),
    enabled: Boolean(username),
    queryFn: async () => {
      if (USE_MOCKS) return mockApi.profile(username);

      const { data } = await api.get<{
        profile: Profile;
        friends_preview: User[];
        mutual_friends: User[];
      }>(`/profile/${username}`);

      return data;
    },
  });
}

export function useFriends() {
  return useQuery({
    queryKey: queryKeys.friends,
    queryFn: async () => {
      if (USE_MOCKS) return mockApi.friends();

      const { data } = await api.get<User[]>('/friends');

      return data;
    },
  });
}

export function useFriendRequests() {
  return useQuery({
    queryKey: queryKeys.requests,
    queryFn: async () => {
      if (USE_MOCKS) return mockApi.receivedRequests();

      const { data } = await api.get<FriendRequest[]>('/friends/requests');

      return data;
    },
  });
}

export function useAcceptRequest() {
  const client = useQueryClient();

  return useMutation({
    mutationFn: async (id: ID) => {
      if (USE_MOCKS) return mockApi.acceptRequest(id);

      const { data } = await api.put<{ is_friend: boolean }>(`/friends/request/${id}/accept`);

      return data;
    },
    onSuccess: () => {
      void client.invalidateQueries({ queryKey: queryKeys.requests });
      void client.invalidateQueries({ queryKey: queryKeys.friends });
      void client.invalidateQueries({ queryKey: queryKeys.suggestions });
    },
  });
}

export function useSuggestions() {
  return useQuery({
    queryKey: queryKeys.suggestions,
    queryFn: async () => {
      if (USE_MOCKS) return mockApi.suggestions();

      const { data } = await api.get<User[]>('/friends/suggestions');

      return data;
    },
  });
}

export function useOnlineFriends() {
  return useQuery({
    queryKey: queryKeys.onlineFriends,
    queryFn: async () => {
      if (USE_MOCKS) return mockApi.onlineFriends();

      const { data } = await api.get<User[]>('/presence/online-friends');

      return data;
    },
    refetchInterval: USE_MOCKS ? 30_000 : 60_000,
  });
}

// ---------------------------------------------------------------- notifications --

export function useNotifications() {
  return useQuery({
    queryKey: queryKeys.notifications,
    queryFn: async () => {
      if (USE_MOCKS) return mockApi.notifications();

      const { data } = await api.get<Paginated<Notification>>('/notifications');

      return data;
    },
    refetchInterval: USE_MOCKS ? 30_000 : 60_000,
  });
}

export function useMarkAllNotificationsRead() {
  const client = useQueryClient();

  return useMutation({
    mutationFn: async () => {
      if (USE_MOCKS) return mockApi.markAllRead();

      const { data } = await api.put<{ marked_read: number }>('/notifications/read-all');

      return data;
    },
    onSuccess: () => {
      void client.invalidateQueries({ queryKey: queryKeys.notifications });
    },
  });
}

export function useSavedPosts() {
  return useQuery({
    queryKey: queryKeys.saved,
    queryFn: async () => {
      if (USE_MOCKS) return mockApi.saved();

      const { data } = await api.get<Paginated<Post>>('/saved');

      return data;
    },
  });
}

// -------------------------------------------------------------------- stories --

export function useStoryTrays() {
  return useQuery({
    queryKey: queryKeys.stories,
    queryFn: async () => {
      if (USE_MOCKS) return mockApi.stories();

      const { data } = await api.get<StoryTray[]>('/stories/feed');

      return data;
    },
  });
}

export function useViewStory() {
  const client = useQueryClient();

  return useMutation({
    mutationFn: async (storyId: ID) => {
      if (USE_MOCKS) return mockApi.viewStory(storyId);

      const { data } = await api.post<{ viewed: boolean }>(`/stories/${storyId}/view`);

      return data;
    },
    onSuccess: () => {
      void client.invalidateQueries({ queryKey: queryKeys.stories });
    },
  });
}

export function useReactToStory() {
  return useMutation({
    mutationFn: async (variables: { storyId: ID; reaction: string }) => {
      if (USE_MOCKS) return mockApi.reactToStory(variables.storyId, variables.reaction);

      const { data } = await api.post<{ reacted: boolean }>(`/stories/${variables.storyId}/react`, {
        reaction: variables.reaction,
      });

      return data;
    },
  });
}

export function useReplyToStory() {
  return useMutation({
    mutationFn: async (variables: { storyId: ID; body: string }) => {
      if (USE_MOCKS) return mockApi.replyToStory(variables.storyId, variables.body);

      const { data } = await api.post<{ replied: boolean }>(`/stories/${variables.storyId}/reply`, {
        body: variables.body,
      });

      return data;
    },
  });
}

// ------------------------------------------------------------------- reels --

export function useReelsFeed() {
  return useInfiniteQuery({
    queryKey: queryKeys.reels,
    initialPageParam: 1,
    queryFn: async ({ pageParam }) => {
      if (USE_MOCKS) return mockApi.reelsFeed(pageParam, 5);

      const { data } = await api.get<Paginated<Reel>>('/reels/feed', {
        params: { cursor: pageParam === 1 ? undefined : String(pageParam), limit: 10 },
      });

      return data;
    },
    getNextPageParam: (lastPage, pages) => {
      const loaded = pages.reduce((sum, page) => sum + page.data.length, 0);

      return lastPage.meta?.has_more && loaded < (lastPage.meta?.total ?? 0) ? pages.length + 1 : undefined;
    },
  });
}

/**
 * Reels are marked watched when the player leaves them, not when it enters:
 * `watched_seconds` is what the ranking service uses to compute completion
 * rate, so a reel that is scrolled past instantly must not count as a view.
 */
export function useViewReel() {
  return useMutation({
    mutationFn: async (variables: { reelId: ID; watchedSeconds: number }) => {
      if (USE_MOCKS) return mockApi.viewReel(variables.reelId, variables.watchedSeconds);

      const { data } = await api.post<{ views_count: number }>(`/reels/${variables.reelId}/view`, {
        watched_seconds: Math.round(variables.watchedSeconds),
      });

      return data;
    },
  });
}

export function useReactToReel() {
  const client = useQueryClient();

  return useMutation({
    mutationFn: async (variables: { reelId: ID; reaction: ReactionType | null }) => {
      if (USE_MOCKS) return mockApi.reactToReel(variables.reelId, variables.reaction);

      const { data } = await api.post<{ likes_count: number; viewer_reaction: ReactionType | null }>(
        `/reels/${variables.reelId}/react`,
        { reaction: variables.reaction },
      );

      return data;
    },
    onMutate: async (variables) => {
      await client.cancelQueries({ queryKey: queryKeys.reels });

      const previous = client.getQueryData<InfiniteData<Paginated<Reel>>>(queryKeys.reels);

      client.setQueryData<InfiniteData<Paginated<Reel>>>(queryKeys.reels, (old) => {
        if (!old) return old;

        return {
          ...old,
          pages: old.pages.map((page) => ({
            ...page,
            data: page.data.map((reel) =>
              reel.id === variables.reelId
                ? {
                    ...reel,
                    viewer_reaction: variables.reaction,
                    likes_count:
                      reel.viewer_reaction === null && variables.reaction !== null
                        ? reel.likes_count + 1
                        : reel.viewer_reaction !== null && variables.reaction === null
                          ? Math.max(0, reel.likes_count - 1)
                          : reel.likes_count,
                  }
                : reel,
            ),
          })),
        };
      });

      return { previous };
    },
    onError: (_error, _variables, context) => {
      if (context?.previous) client.setQueryData(queryKeys.reels, context.previous);
    },
  });
}

export function useShareReel() {
  const client = useQueryClient();

  return useMutation({
    mutationFn: async (reelId: ID) => {
      if (USE_MOCKS) return mockApi.shareReel(reelId);

      const { data } = await api.post<{ shares_count: number }>(`/reels/${reelId}/share`, {
        destination: 'feed',
      });

      return data;
    },
    onSuccess: (result) => {
      void client.invalidateQueries({ queryKey: queryKeys.reels });
      void client.invalidateQueries({ queryKey: queryKeys.feed });

      return result;
    },
  });
}

export function useReelComments(reelId: ID | null) {
  return useQuery({
    queryKey: queryKeys.reelComments(reelId ?? ''),
    enabled: Boolean(reelId),
    queryFn: async () => {
      if (USE_MOCKS) return mockApi.reelComments(reelId!);

      const { data } = await api.get<Paginated<Comment>>(`/reels/${reelId}/comments`);

      return data;
    },
  });
}

export function useCreateReelComment(reelId: ID) {
  const client = useQueryClient();

  return useMutation({
    mutationFn: async (body: string) => {
      if (USE_MOCKS) return mockApi.createReelComment(reelId, body);

      const { data } = await api.post<{ data: Comment }>(`/reels/${reelId}/comments`, { body });

      return data.data ?? (data as unknown as Comment);
    },
    onSuccess: (comment) => {
      client.setQueryData<Paginated<Comment>>(queryKeys.reelComments(reelId), (old) => ({
        data: [...(old?.data ?? []), comment],
        meta: old?.meta
          ? { ...old.meta, total: (old.meta.total ?? 0) + 1 }
          : { total: 1, current_page: 1, per_page: 20, has_more: false },
      }));

      client.setQueryData<InfiniteData<Paginated<Reel>>>(queryKeys.reels, (old) => {
        if (!old) return old;

        return {
          ...old,
          pages: old.pages.map((page) => ({
            ...page,
            data: page.data.map((reel) =>
              reel.id === reelId ? { ...reel, comments_count: reel.comments_count + 1 } : reel,
            ),
          })),
        };
      });
    },
  });
}

export function useFollowUser() {
  const client = useQueryClient();

  return useMutation({
    mutationFn: async (variables: { userId: ID; following: boolean }) => {
      if (USE_MOCKS) return mockApi.setFollowing(variables.userId, variables.following);

      const { data } = await api.post<{ is_following: boolean }>(`/friends/${variables.userId}/follow`, {
        following: variables.following,
      });

      return data;
    },
    onSuccess: (_result, variables) => {
      client.setQueryData<InfiniteData<Paginated<Reel>>>(queryKeys.reels, (old) => {
        if (!old) return old;

        return {
          ...old,
          pages: old.pages.map((page) => ({
            ...page,
            data: page.data.map((reel) =>
              reel.creator.id === variables.userId
                ? { ...reel, creator: { ...reel.creator, is_following: variables.following } }
                : reel,
            ),
          })),
        };
      });
    },
  });
}

// ------------------------------------------------------------------ groups --

export function useGroups() {
  return useQuery({
    queryKey: queryKeys.groups,
    queryFn: async () => {
      if (USE_MOCKS) return mockApi.groups();

      const { data } = await api.get<Paginated<Group>>('/groups/discover');

      return data;
    },
  });
}

export function useMyGroups() {
  return useQuery({
    queryKey: queryKeys.myGroups,
    queryFn: async () => {
      if (USE_MOCKS) return mockApi.myGroups();

      const { data } = await api.get<Group[]>('/groups/my-groups');

      return data;
    },
  });
}

export function useGroup(idOrSlug: ID) {
  return useQuery({
    queryKey: queryKeys.group(idOrSlug),
    enabled: Boolean(idOrSlug),
    queryFn: async () => {
      if (USE_MOCKS) return mockApi.group(idOrSlug);

      const { data } = await api.get<GroupDetail>(`/groups/${idOrSlug}`);

      return data;
    },
  });
}

export function useGroupPosts(groupId: ID) {
  return useInfiniteQuery({
    queryKey: queryKeys.groupPosts(groupId),
    enabled: Boolean(groupId),
    initialPageParam: 1,
    queryFn: async ({ pageParam }) => {
      if (USE_MOCKS) return mockApi.groupPosts(groupId, pageParam);

      const { data } = await api.get<Paginated<Post>>(`/groups/${groupId}/posts`, {
        params: { page: pageParam },
      });

      return data;
    },
    getNextPageParam: (lastPage, pages) =>
      lastPage.meta?.has_more ? pages.length + 1 : undefined,
  });
}

export function usePendingGroupPosts(groupId: ID) {
  return useQuery({
    queryKey: queryKeys.pendingGroupPosts(groupId),
    enabled: Boolean(groupId),
    queryFn: async () => {
      if (USE_MOCKS) return mockApi.pendingGroupPosts(groupId);

      const { data } = await api.get<Paginated<Post>>(`/groups/${groupId}/pending-posts`);

      return data;
    },
  });
}

export function useCreateGroupPost(groupId: ID) {
  const client = useQueryClient();

  return useMutation({
    mutationFn: async (body: string) => {
      if (USE_MOCKS) return mockApi.createGroupPost(groupId, body);

      const { data } = await api.post<Post>(`/groups/${groupId}/posts`, { body, type: 'text' });

      return data;
    },
    onSuccess: (post) => {
      // A member's post waits for approval; an admin's lands in the feed. Both
      // caches are refreshed so the queue and the discussion stay truthful.
      void client.invalidateQueries({ queryKey: queryKeys.groupPosts(groupId) });
      void client.invalidateQueries({ queryKey: queryKeys.pendingGroupPosts(groupId) });
      void client.invalidateQueries({ queryKey: queryKeys.group(groupId) });

      return post;
    },
  });
}

export function useModerateGroupPost(groupId: ID) {
  const client = useQueryClient();

  return useMutation({
    mutationFn: async (variables: { postId: ID; decision: 'approve' | 'decline' }) => {
      if (USE_MOCKS) {
        return variables.decision === 'approve'
          ? mockApi.approveGroupPost(groupId, variables.postId)
          : mockApi.declineGroupPost(groupId, variables.postId);
      }

      if (variables.decision === 'approve') {
        const { data } = await api.post<{ message: string }>(
          `/groups/${groupId}/posts/${variables.postId}/approve`,
        );

        return data;
      }

      const { data } = await api.delete<{ message: string }>(`/groups/${groupId}/posts/${variables.postId}`);

      return data;
    },
    onSuccess: () => {
      void client.invalidateQueries({ queryKey: queryKeys.groupPosts(groupId) });
      void client.invalidateQueries({ queryKey: queryKeys.pendingGroupPosts(groupId) });
    },
  });
}

export function useGroupMembers(groupId: ID, status?: string) {
  return useQuery({
    queryKey: [...queryKeys.groupMembers(groupId), status ?? 'all'],
    enabled: Boolean(groupId),
    queryFn: async () => {
      if (USE_MOCKS) return mockApi.groupMembers(groupId, status);

      const { data } = await api.get<Paginated<GroupMember>>(`/groups/${groupId}/members`, {
        params: { status },
      });

      return data;
    },
  });
}

export function useJoinGroup() {
  const client = useQueryClient();

  return useMutation({
    mutationFn: async (groupId: ID) => {
      if (USE_MOCKS) return mockApi.joinGroup(groupId);

      const { data } = await api.post<{ status: 'active' | 'pending' }>(`/groups/${groupId}/join`);

      return data;
    },
    onSuccess: (_result, groupId) => {
      void client.invalidateQueries({ queryKey: queryKeys.groups });
      void client.invalidateQueries({ queryKey: queryKeys.myGroups });
      void client.invalidateQueries({ queryKey: queryKeys.group(groupId) });
    },
  });
}

export function useLeaveGroup() {
  const client = useQueryClient();

  return useMutation({
    mutationFn: async (groupId: ID) => {
      if (USE_MOCKS) return mockApi.leaveGroup(groupId);

      const { data } = await api.delete<{ message: string }>(`/groups/${groupId}/leave`);

      return data;
    },
    onSuccess: (_result, groupId) => {
      void client.invalidateQueries({ queryKey: queryKeys.groups });
      void client.invalidateQueries({ queryKey: queryKeys.myGroups });
      void client.invalidateQueries({ queryKey: queryKeys.group(groupId) });
    },
  });
}

export function useCreateGroup() {
  const client = useQueryClient();

  return useMutation({
    mutationFn: async (input: { name: string; description?: string; privacy: GroupPrivacy; type: GroupType }) => {
      if (USE_MOCKS) return mockApi.createGroup(input);

      const { data } = await api.post<Group>('/groups', input);

      return data;
    },
    onSuccess: (group) => {
      void client.invalidateQueries({ queryKey: queryKeys.groups });
      void client.invalidateQueries({ queryKey: queryKeys.myGroups });

      return group;
    },
  });
}

/** Admin actions on a member: promote, demote or remove. */
export function useGroupMemberAction() {
  const client = useQueryClient();

  return useMutation({
    mutationFn: async (variables: {
      groupId: ID;
      userId: ID;
      action: 'promote' | 'demote' | 'remove';
    }) => {
      if (USE_MOCKS) {
        if (variables.action === 'remove') return mockApi.removeGroupMember(variables.groupId, variables.userId);

        return mockApi.setGroupMemberRole(
          variables.groupId,
          variables.userId,
          variables.action === 'promote' ? 'moderator' : 'member',
        );
      }

      if (variables.action === 'remove') {
        const { data } = await api.delete<{ message: string }>(
          `/groups/${variables.groupId}/members/${variables.userId}`,
        );

        return data;
      }

      const { data } = await api.put<{ role: GroupRole }>(
        `/groups/${variables.groupId}/members/${variables.userId}/role`,
        { role: variables.action === 'promote' ? 'moderator' : 'member' },
      );

      return data;
    },
    onSuccess: () => {
      void client.invalidateQueries({ queryKey: queryKeys.groups });
    },
  });
}

// --------------------------------------------------------------------- search --

/** Type-ahead for the header dropdown. */
export function useSearchSuggestions(term: string) {
  return useQuery({
    queryKey: queryKeys.searchSuggestions(term),
    enabled: term.trim().length > 1,
    queryFn: async () => {
      if (USE_MOCKS) return mockApi.searchSuggestions(term);

      const { data } = await api.get<{ suggestions: SearchSuggestion[] }>('/search/suggestions', {
        params: { q: term },
      });

      return data.suggestions ?? [];
    },
  });
}

/**
 * The results page. Each tab is a separate request — the backend narrows by
 * `type` rather than sending every bucket at once, so switching tabs is cheap.
 */
export function useSearchResults(term: string, tab: SearchTab = 'all') {
  return useQuery({
    queryKey: queryKeys.search(term, tab),
    enabled: term.trim().length > 0,
    queryFn: async () => {
      if (USE_MOCKS) return mockApi.searchAll(term, tab);

      const { data } = await api.get<{ query: string; results: Record<string, { data: unknown[] }> }>('/search', {
        params: { q: term, type: tab },
      });

      const bucket = (key: string): never[] =>
        (data.results?.[key]?.data as never[] | undefined) ?? [];

      return {
        people: bucket('people') as SearchResults['people'],
        posts: bucket('posts') as SearchResults['posts'],
        groups: bucket('groups') as SearchResults['groups'],
      };
    },
  });
}

export function useRecentSearches() {
  return useQuery({
    queryKey: queryKeys.recentSearches,
    queryFn: async () => {
      if (USE_MOCKS) return mockApi.recentSearches();

      const { data } = await api.get<{ data: SearchHistoryItem[] }>('/search/recent');

      return data.data ?? [];
    },
  });
}

export function useClearRecentSearches() {
  const client = useQueryClient();

  return useMutation({
    mutationFn: async (id?: ID) => {
      if (USE_MOCKS) return mockApi.clearRecentSearches(id);

      const { data } = await api.delete<{ deleted: number }>(id ? `/search/recent/${id}` : '/search/recent');

      return data;
    },
    onSuccess: () => {
      void client.invalidateQueries({ queryKey: queryKeys.recentSearches });
    },
  });
}

export function useTrending() {
  return useQuery({
    queryKey: queryKeys.trending,
    queryFn: async () => {
      if (USE_MOCKS) return mockApi.trending();

      const { data } = await api.get<Array<{ id: string; name: string; posts: number }>>('/search/trending');

      return data;
    },
  });
}
