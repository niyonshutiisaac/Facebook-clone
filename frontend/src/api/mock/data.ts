import type {
  Comment,
  Conversation,
  FriendRequest,
  Group,
  GroupMember,
  GroupRule,
  ID,
  Media,
  Message,
  Notification,
  Post,
  Profile,
  Reel,
  StoryTray,
  User,
} from '@/types/api';

/**
 * Deterministic demo data.
 *
 * The backend (Laravel) is the source of truth in production, but the UI must
 * be explorable and reviewable on its own — including in this sandbox, where no
 * PHP runtime exists. Everything below is seeded from a fixed RNG so two reloads
 * produce the same world, and the shapes are byte-for-byte identical to what
 * `docs/API.md` and the Laravel resources return.
 */

/** Mulberry32 — tiny, fast, deterministic. */
function rng(seed: number): () => number {
  let a = seed;

  return () => {
    a |= 0;
    a = (a + 0x6d2b79f5) | 0;

    let t = Math.imul(a ^ (a >>> 15), 1 | a);
    t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;

    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}

const random = rng(20240915);

const pick = <T>(items: readonly T[]): T => items[Math.floor(random() * items.length)]!;
const between = (min: number, max: number): number => Math.floor(random() * (max - min + 1)) + min;
const chance = (probability: number): boolean => random() < probability;

export const HOUR = 60 * 60 * 1000;
export const MINUTE = 60 * 1000;

const agoIso = (ms: number): string => new Date(Date.now() - ms).toISOString();

/**
 * Unsplash-style placeholder imagery. Each entry is a stable, cacheable URL so
 * the feed looks like real photography without shipping binary assets.
 */
function photo(seed: string, w = 1600, h = 1067): string {
  return `https://picsum.photos/seed/${encodeURIComponent(seed)}/${w}/${h}`;
}

// ------------------------------------------------------------------ people --

interface PersonSpec {
  first: string;
  last: string;
  username: string;
  headline?: string;
  about?: string;
  city?: string;
  hometown?: string;
  work?: string;
  position?: string;
  school?: string;
  online?: boolean;
  verified?: boolean;
}

const PEOPLE: PersonSpec[] = [
  {
    first: 'Ava',
    last: 'Chen',
    username: 'avachen',
    headline: 'Designing small things that matter.',
    about: 'Product designer. Coffee scientist. Slow runner of fast marathons.',
    city: 'Brooklyn, New York',
    hometown: 'Columbus, Ohio',
    work: 'Northwind Studio',
    position: 'Principal Product Designer',
    school: 'Carnegie Mellon University',
    online: true,
    verified: true,
  },
  {
    first: 'Marcus',
    last: 'Okafor',
    username: 'marcusok',
    headline: 'Backend engineer · liking things that scale',
    about: 'Distributed systems, terrible puns, excellent jollof.',
    city: 'Austin, Texas',
    hometown: 'Lagos, Nigeria',
    work: 'Avimoney',
    position: 'Staff Engineer',
    school: 'University of Lagos',
    online: true,
  },
  {
    first: 'Priya',
    last: 'Raman',
    username: 'priyar',
    headline: 'Photographer & educator',
    about: 'Teaching light, one frame at a time.',
    city: 'Toronto, Ontario',
    hometown: 'Chennai, India',
    work: 'Light Room',
    position: 'Founder',
    school: 'OCAD University',
    online: true,
    verified: true,
  },
  {
    first: 'Sofia',
    last: 'Marchetti',
    username: 'sofiam',
    headline: 'Pasta is a personality',
    about: 'Cookbook author. Currently obsessed with focaccia hydration.',
    city: 'Bologna, Italy',
    hometown: 'Bologna, Italy',
    work: 'Osteria Sofia',
    position: 'Chef & Owner',
    school: 'ALMA',
  },
  {
    first: 'Jonas',
    last: 'Weber',
    username: 'jonasw',
    headline: 'Cyclist. Sometimes triathlete.',
    about: 'I ride bikes and complain about cobblestones.',
    city: 'Berlin, Germany',
    hometown: 'Leipzig, Germany',
    work: 'Velodrom Berlin',
    position: 'Coach',
    school: 'TU Berlin',
    online: true,
  },
  {
    first: 'Amara',
    last: 'Diallo',
    username: 'amarad',
    headline: 'Clinical researcher',
    about: "Infectious disease epidemiology. Tea over coffee, don't @ me.",
    city: 'Atlanta, Georgia',
    hometown: 'Dakar, Senegal',
    work: 'Emory University',
    position: 'Research Lead',
    school: 'Johns Hopkins',
  },
  {
    first: 'Ravi',
    last: 'Shankar',
    username: 'ravish',
    headline: 'Building things for the web',
    about: 'Frontend. Framer Motion apologist.',
    city: 'Bengaluru, India',
    hometown: 'Pune, India',
    work: 'Avimoney',
    position: 'Senior Frontend Engineer',
    school: 'IIT Bombay',
  },
  {
    first: 'Elena',
    last: 'Novak',
    username: 'elenan',
    headline: 'Architect · drawing sustainable futures',
    about: 'Passive houses and loud opinions about concrete.',
    city: 'Prague, Czechia',
    hometown: 'Brno, Czechia',
    work: 'Novak Atelier',
    position: 'Founding Partner',
    school: 'ČVUT',
  },
  {
    first: 'Theo',
    last: 'Laurent',
    username: 'theolaurent',
    headline: 'Sommelier in recovery',
    about: 'Ask me about natural wine. Actually, please do not.',
    city: 'Lyon, France',
    hometown: 'Bordeaux, France',
    work: 'Cave Laurent',
    position: 'Sommelier',
    school: 'Université de Bordeaux',
  },
  {
    first: 'Yuki',
    last: 'Tanaka',
    username: 'yukit',
    headline: 'Illustrator and animator',
    about: 'Drawing tiny worlds since 2011.',
    city: 'Tokyo, Japan',
    hometown: 'Kanazawa, Japan',
    work: 'Studio Yuki',
    position: 'Illustrator',
    school: 'Tama Art University',
    online: true,
  },
  {
    first: 'Nadia',
    last: 'Haddad',
    username: 'nadhaddad',
    headline: 'Documentary filmmaker',
    about: 'Stories about home, and the roads between.',
    city: 'Beirut, Lebanon',
    hometown: 'Saida, Lebanon',
    work: 'Haddad Films',
    position: 'Director',
    school: 'ALBA',
  },
  {
    first: 'Sam',
    last: 'Whitfield',
    username: 'samw',
    headline: 'High school teacher · history',
    about: 'Teaching the 20th century to people born in the 21st.',
    city: 'Manchester, United Kingdom',
    hometown: 'Leeds, United Kingdom',
    work: 'Whitworth Academy',
    position: 'Head of History',
    school: 'University of Leeds',
  },
];

function userFrom(index: number, spec: PersonSpec): User {
  return {
    id: `u${index + 1}`,
    name: `${spec.first} ${spec.last}`,
    username: spec.username,
    first_name: spec.first,
    last_name: spec.last,
    email: `${spec.username}@example.com`,
    avatar_url: photo(`avatar-${spec.username}`, 200, 200),
    cover_url: photo(`cover-${spec.username}`, 1200, 400),
    is_verified: spec.verified ?? false,
    is_online: spec.online ?? false,
    last_seen_at: spec.online ? new Date().toISOString() : agoIso(between(2, 900) * MINUTE),
  };
}

export const users: User[] = PEOPLE.map((spec, index) => userFrom(index, spec));

/** The signed-in demo account. */
export const currentUser: User = users[0]!;

export const profileFor = (user: User): Profile => {
  const spec = PEOPLE[users.findIndex((u) => u.id === user.id)]!;
  const friends = between(180, 1400);

  return {
    ...user,
    headline: spec.headline,
    bio: spec.headline,
    about: spec.about,
    current_city: spec.city,
    hometown: spec.hometown,
    relationship_status: chance(0.4) ? 'in_a_relationship' : 'single',
    work: [{ company: spec.work ?? 'Independent', position: spec.position ?? 'Member', from: '2019' }],
    education: [{ school: spec.school ?? 'State University', degree: 'B.S.', to: '2015' }],
    places_lived: [
      { city: spec.hometown ?? 'Unknown', type: 'hometown' },
      { city: spec.city ?? 'Unknown', type: 'current' },
    ],
    contact_info: { email: user.email, phone: chance(0.6) ? '+1 555 018 2299' : null, website: null },
    basic_info: {
      birth_date: `199${between(0, 8)}-0${between(1, 9)}-1${between(0, 9)}`,
      gender: chance(0.5) ? 'female' : 'male',
      pronouns: chance(0.5) ? 'she/her' : 'he/him',
      languages: pick([['English'], ['English', 'French'], ['English', 'Japanese'], ['English', 'Hindi']]),
    },
    favorite_quotes: 'Shipping beats perfect.',
    friends_count: friends,
    followers_count: friends * between(2, 5),
    photos_count: between(40, 480),
    posts_count: between(60, 900),
    can_edit: user.id === currentUser.id,
  };
};

export const profiles: Profile[] = users.map(profileFor);

// ------------------------------------------------------------ friend graph --

/** Symmetric adjacency, so mutuals work in both directions. */
const friendIds: Record<ID, ID[]> = (() => {
  const map: Record<ID, ID[]> = Object.fromEntries(users.map((u) => [u.id, []]));

  for (const a of users) {
    for (const b of users) {
      if (a.id === b.id) continue;

      const close = a.id === currentUser.id || b.id === currentUser.id;
      const isMutual = map[a.id]!.includes(b.id);

      if (!isMutual && (close || chance(0.55))) {
        map[a.id]!.push(b.id);
        map[b.id]!.push(a.id);
      }
    }
  }

  return map;
})();

export const friendIdsFor = (id: ID): ID[] => friendIds[id] ?? [];

export const userById = (id: ID): User => users.find((u) => u.id === id) ?? currentUser;

export const friendsOf = (id: ID): User[] => friendIdsFor(id).map(userById);

export const mutualFriendCount = (a: ID, b: ID): number =>
  friendIdsFor(a).filter((id) => friendIdsFor(b).includes(id)).length;

// ------------------------------------------------------------------ posts --

const BODIES: string[] = [
  "Finally finished the thing I've been squeezing into evenings for three months. Shipping beats perfect.",
  'Golden hour on the way home. No filter, no crop, just lucky.',
  'Six hours of footage, ninety seconds of film. Worth every dropped frame.',
  'This explanation of how feed ranking actually works is the best short write-up I have read this year.',
  'Reminder that "we have always done it this way" is not a design system.',
  "Sunday market haul. The tomatoes are unreasonably good this year. 🍅",
  'Fifteen kilometres along the canal and not one goose incident. A personal best.',
  'New job, new city, same terrible coffee order. Starting Monday. ☕️',
  'Someone asked me what the hardest part of the job is. Honestly? Naming things.',
  'Three years of practice and I have finally managed a loaf with an open crumb. I am unreasonably proud.',
  'Spent the morning photographing fog on the river and the afternoon deleting all of it. Art is suffering.',
  'Hot take: most "performance problems" are just N+1 queries wearing a trench coat.',
  'Made the 40 minute focaccia. The dough fought back and won, but we are both better for it.',
  'The best thing about cycling home is that it is impossible to check email while doing it.',
  'A reminder to myself: you cannot think your way into a new behaviour. You have to behave your way into new thinking.',
  'Shot this on expired film and honestly the mistakes are my favourite part. 🎞️',
  'My grandmother made this recipe for six people. I have tripled it and it still somehow only feeds four.',
  'If your design system needs a 40 page doc to explain spacing, the spacing scale is wrong.',
  'Currently reading three books and finishing none of them. Balance.',
  'Watched the sunset from the studio roof with the whole team. Best meeting of the quarter.',
];

const POLL_QUESTIONS = [
  'What is the correct amount of garlic?',
  'Tabs or spaces?',
  'Best season to run a marathon?',
  'Light mode or dark mode?',
];

const FEELINGS = [
  { emoji: '🙏', label: 'grateful' },
  { emoji: '🎉', label: 'excited' },
  { emoji: '😴', label: 'tired' },
  { emoji: '🤔', label: 'thoughtful' },
  { emoji: '💪', label: 'motivated' },
  { emoji: '☕️', label: 'caffeinated' },
];

const LINKS = [
  {
    url: 'https://engineering.fb.com/2021/01/26/production-ml/facebook-ranking/',
    domain: 'engineering.fb.com',
    title: 'How Facebook ranks your feed',
    description:
      'A look at the two-stage system — retrieval and ranking — that decides which 500 of the 1,500 candidate stories you actually see.',
    site_name: 'Facebook Engineering',
  },
  {
    url: 'https://www.nngroup.com/articles/progressive-disclosure/',
    domain: 'nngroup.com',
    title: 'Progressive Disclosure: Definitions and Best Practices',
    description:
      'Defer advanced or rarely used features to a secondary screen to make applications easier to learn and less error-prone.',
    site_name: 'Nielsen Norman Group',
  },
  {
    url: 'https://web.dev/articles/vitals',
    domain: 'web.dev',
    title: 'Web Vitals: essential metrics for a healthy site',
    description:
      'Largest Contentful Paint, Interaction to Next Paint and Cumulative Layout Shift — what they measure and why they matter.',
    site_name: 'web.dev',
  },
];

const REACTION_WEIGHTS: Array<[keyof Post['reaction_summary'], number]> = [
  ['like', 0.6],
  ['love', 0.16],
  ['care', 0.06],
  ['wow', 0.06],
  ['haha', 0.07],
  ['sad', 0.02],
  ['angry', 0.03],
];

function weightedReaction(): keyof Post['reaction_summary'] {
  let roll = random();

  for (const [type, weight] of REACTION_WEIGHTS) {
    roll -= weight;
    if (roll <= 0) return type;
  }

  return 'like';
}

function mediaFor(postIndex: number, count: number, kind: 'image' | 'video'): Media[] {
  return Array.from({ length: count }, (_, i) => ({
    id: `m${postIndex}-${i}`,
    type: kind,
    url: photo(`post-${postIndex}-${i}`, 1600, kind === 'video' ? 900 : between(900, 1400)),
    thumbnail_url: photo(`post-${postIndex}-${i}`, 800, 450),
    poster_url: kind === 'video' ? photo(`post-${postIndex}-${i}`, 1600, 900) : undefined,
    width: 1600,
    height: 900,
    duration: kind === 'video' ? between(14, 180) : undefined,
    alt_text: 'Photo shared by the author',
  }));
}

let postCounter = 0;

function makePost(author: User, createdAt: number): Post {
  const index = postCounter++;

  const roll = random();
  let type: Post['type'] = 'text';

  if (roll < 0.3) type = 'photo';
  else if (roll < 0.42) type = 'video';
  else if (roll < 0.52) type = 'link';
  else if (roll < 0.6) type = 'poll';
  else if (roll < 0.67) type = 'feeling';
  else if (roll < 0.72) type = 'life_event';

  const body = pick(BODIES);

  const media =
    type === 'photo'
      ? mediaFor(index, chance(0.25) ? between(2, 5) : 1, 'image')
      : type === 'video'
        ? mediaFor(index, 1, 'video')
        : [];

  const link = type === 'link' ? pick(LINKS) : null;

  const poll =
    type === 'poll'
      ? (() => {
          const count = between(2, 4);
          const options = Array.from({ length: count }, (_, i) => ({
            id: `po${index}-${i}`,
            label: pick([
              'One clove',
              'The whole head',
              'Yes',
              'Tabs',
              'Spaces',
              'Autumn',
              'Spring',
              'Dark',
              'Light',
              'Whatever the recipe says',
            ]),
            votes_count: between(3, 240),
            percentage: 0,
            voted_by_viewer: chance(0.2),
          }));

          const total = options.reduce((sum, o) => sum + o.votes_count, 0);

          return {
            id: `p${index}`,
            question: pick(POLL_QUESTIONS),
            options: options.map((o) => ({ ...o, percentage: Math.round((o.votes_count / total) * 100) })),
            total_votes: total,
            voters_count: total,
            allows_multiple: chance(0.2),
            closes_at: chance(0.3) ? new Date(Date.now() + between(1, 8) * 24 * HOUR).toISOString() : null,
          };
        })()
      : null;

  const reactions = between(0, 64);
  const summary: Post['reaction_summary'] = {};

  for (let i = 0; i < reactions; i++) {
    const type_ = weightedReaction();
    summary[type_] = (summary[type_] ?? 0) + 1;
  }

  const top = Object.entries(summary)
    .sort((a, b) => (b[1] ?? 0) - (a[1] ?? 0))
    .slice(0, 3)
    .map(([t]) => t as keyof Post['reaction_summary']);

  return {
    id: `p${index}`,
    type,
    body: type === 'poll' ? null : body,
    audience: chance(0.35) ? 'public' : 'friends',
    author,
    media,
    link: link ? { url: link.url, domain: link.domain, preview: { ...link, image_url: photo(`link-${index}`, 1200, 630) } } : null,
    poll,
    feelings: type === 'feeling' ? [{ id: `f${index}`, ...pick(FEELINGS) }] : null,
    shared_post: null,
    location: chance(0.12) ? pick(['Brooklyn, New York', 'Berlin, Germany', 'Tokyo, Japan', 'Bologna, Italy']) : null,
    reactions_count: reactions,
    comments_count: between(0, 24),
    shares_count: between(0, 40),
    reaction_summary: summary,
    top_reactions: top,
    viewer_reaction: null,
    is_saved: chance(0.08),
    is_pinned: false,
    is_edited: chance(0.05),
    created_at: new Date(createdAt).toISOString(),
    updated_at: new Date(createdAt).toISOString(),
    can: { edit: author.id === currentUser.id, delete: author.id === currentUser.id, comment: true, share: true },
  };
}

/** The feed: posts from the viewer's graph, newest first. */
export const posts: Post[] = (() => {
  const sources = [...friendsOf(currentUser.id), currentUser];
  const generated: Post[] = [];

  // Spread posts across the last four days with a recency bias.
  for (let i = 0; i < 42; i++) {
    const age = Math.floor(Math.pow(random(), 1.7) * 4 * 24 * HOUR) + between(1, 20) * MINUTE;
    generated.push(makePost(pick(sources), Date.now() - age));
  }

  return generated.sort((a, b) => +new Date(b.created_at) - +new Date(a.created_at));
})();

export const postsByAuthor = (id: ID): Post[] => posts.filter((p) => p.author.id === id);

export const postById = (id: ID): Post | undefined => posts.find((p) => p.id === id);

// --------------------------------------------------------------- comments --

const COMMENT_BODIES: string[] = [
  'This is excellent.',
  'Okay but where is this?',
  'Saving this for later, thank you.',
  'I have never related to anything more.',
  'Wait, this changes everything. 🙌',
  'Do you have a recipe?',
  'Congratulations!! 🎉',
  'The lighting in this is unreal.',
  'I read this twice and I think you are right.',
  'Sending this to my whole team.',
  'Genuinely the best thing on my feed today.',
  'How long did this take you?',
];

let commentCounter = 0;

function makeComment(postId: ID, depth: number, parentId: ID | null, createdAt: number): Comment {
  const id = `c${commentCounter++}`;

  return {
    id,
    body: pick(COMMENT_BODIES),
    author: pick(users),
    post_id: postId,
    parent_id: parentId,
    depth,
    likes_count: between(0, 18),
    replies_count: 0,
    viewer_reaction: chance(0.15) ? 'like' : null,
    created_at: new Date(createdAt).toISOString(),
  };
}

export const commentsByPost = new Map<ID, Comment[]>();

for (const post of posts) {
  const total = post.comments_count;
  const thread: Comment[] = [];

  for (let i = 0; i < Math.min(total, 6); i++) {
    const parent = makeComment(post.id, 0, null, +new Date(post.created_at) + (i + 1) * between(3, 90) * MINUTE);

    if (chance(0.45)) {
      const reply = makeComment(post.id, 1, parent.id, +new Date(parent.created_at) + between(2, 40) * MINUTE);
      parent.replies_count = 1;

      if (chance(0.3)) {
        const deep = makeComment(post.id, 2, reply.id, +new Date(reply.created_at) + between(2, 30) * MINUTE);
        reply.replies_count = 1;
        thread.push(reply, deep);
      } else {
        thread.push(reply);
      }
    }

    thread.push(parent);
  }

  commentsByPost.set(post.id, thread.sort((a, b) => +new Date(a.created_at) - +new Date(b.created_at)));
}

// ------------------------------------------------------------- messenger --

const CONVERSATION_SCRIPTS: Array<{ with: string; lines: string[] }> = [
  {
    with: 'marcusok',
    lines: [
      'Hey! Are you coming to the thing on Saturday?',
      'Which thing? The one with the cake or the one with the spreadsheet?',
      'The cake. Definitely the cake.',
      'Then yes. Obviously yes.',
      'Perfect. Bringing the good coffee?',
      'Always. ☕️',
    ],
  },
  {
    with: 'priyar',
    lines: [
      'Did you see the new design system drop?',
      'Just skimmed it. The spacing scale finally makes sense.',
      'Right?! Took them long enough.',
      'I know. I have been manually nudging 4px for two years. 😅',
    ],
  },
  {
    with: 'yukit',
    lines: [
      'Sending you the photos from the trip 📷',
      'Oh these are SO good. That light!',
      'Golden hour does 90% of the work honestly',
      "Don't undersell it — you framed that beautifully",
      '🥹 thank you',
    ],
  },
  {
    with: 'elenan',
    lines: [
      'Are we still on for the run tomorrow?',
      '6am?',
      'I was thinking a civilised 7.',
      'Coward. 6:30 and we call it even.',
      'Deal. See you at the bridge.',
    ],
  },
  {
    with: 'sofiam',
    lines: [
      'How much garlic for six people',
      'Yes.',
      'This is not an answer Sofia',
      'It is the only correct answer. 🧄',
    ],
  },
];

let messageCounter = 0;

function makeMessage(conversationId: ID, sender: User, body: string, createdAt: number, status: Message['delivery_status']): Message {
  return {
    id: `msg${messageCounter++}`,
    conversation_id: conversationId,
    sender_id: sender.id,
    sender,
    type: 'text',
    body,
    delivery_status: status,
    is_mine: sender.id === currentUser.id,
    is_emoji_only: /^[\p{Emoji}\s]+$/u.test(body) && body.trim().length <= 4,
    reactions: [],
    created_at: new Date(createdAt).toISOString(),
  };
}

export const conversations: Conversation[] = CONVERSATION_SCRIPTS.map((script, index) => {
  const other = users.find((u) => u.username === script.with)!;
  const conversationId = `conv${index + 1}`;

  const startedAt = Date.now() - between(3, 60) * HOUR;

  const messages: Message[] = script.lines.map((line, i) => {
    const sender = i % 2 === 0 ? other : currentUser;
    // The first thread has two unread messages so the badge shows a count.
    const read = index === 0 ? i < script.lines.length - 2 : true;

    return makeMessage(
      conversationId,
      sender,
      line,
      startedAt + i * between(2, 25) * MINUTE,
      sender.id === currentUser.id ? (read ? 'read' : 'delivered') : 'read',
    );
  });

  return {
    id: conversationId,
    type: 'private',
    participants: [currentUser, other],
    display_name: other.name,
    display_avatar: other.avatar_url,
    last_message: messages[messages.length - 1] ?? null,
    unread_count: index === 0 ? 2 : 0,
    is_muted: false,
    is_pinned: index === 0,
    is_archived: false,
    typing_user_ids: [],
    updated_at: messages[messages.length - 1]?.created_at ?? new Date(startedAt).toISOString(),
  };
});

// Group chat
{
  const members = [currentUser, ...users.slice(1, 5)];
  const conversationId = 'conv-group';
  const startedAt = Date.now() - 5 * HOUR;

  const lines: Array<[User, string]> = [
    [currentUser, 'Okay, Saturday. What are we actually doing?'],
    [users[1]!, 'Market in the morning, then the park?'],
    [users[3]!, 'I am in. Bringing bread.'],
    [users[2]!, 'I will bring the thing that goes with bread.'],
    [users[4]!, 'Can someone bring a blanket? I have exactly zero blankets.'],
    [currentUser, 'Perfect. See you all there. 🧺'],
  ];

  const messages = lines.map(([sender, body], i) =>
    makeMessage(conversationId, sender, body, startedAt + i * between(3, 30) * MINUTE, 'read'),
  );

  conversations.push({
    id: conversationId,
    type: 'group',
    title: 'Weekend plans 🗓️',
    avatar_url: photo('group-weekend', 200, 200),
    participants: members,
    display_name: 'Weekend plans 🗓️',
    display_avatar: photo('group-weekend', 200, 200),
    last_message: messages[messages.length - 1] ?? null,
    unread_count: 0,
    typing_user_ids: [],
    updated_at: messages[messages.length - 1]?.created_at ?? new Date(startedAt).toISOString(),
  });
}

const messagesByConversation = new Map<ID, Message[]>();

for (const conversation of conversations) {
  const last = conversation.last_message;
  const members = conversation.participants;
  const script = CONVERSATION_SCRIPTS.find((s) => `conv${CONVERSATION_SCRIPTS.indexOf(s) + 1}` === conversation.id);

  if (script) {
    const other = users.find((u) => u.username === script.with)!;
    const startedAt = +new Date(conversation.updated_at) - script.lines.length * 12 * MINUTE;

    messagesByConversation.set(
      conversation.id,
      script.lines.map((line, i) => {
        const sender = i % 2 === 0 ? other : currentUser;
        const read = conversation.unread_count > 0 ? i < script.lines.length - conversation.unread_count : true;

        return makeMessage(
          conversation.id,
          sender,
          line,
          startedAt + i * between(2, 25) * MINUTE,
          sender.id === currentUser.id ? (read ? 'read' : 'delivered') : 'read',
        );
      }),
    );
  } else {
    const startedAt = +new Date(conversation.updated_at) - 5 * HOUR;

    messagesByConversation.set(
      conversation.id,
      [
        [currentUser, 'Okay, Saturday. What are we actually doing?'],
        [members[1]!, 'Market in the morning, then the park?'],
        [members[3]!, 'I am in. Bringing bread.'],
        [members[2]!, 'I will bring the thing that goes with bread.'],
        [members[4]!, 'Can someone bring a blanket? I have exactly zero blankets.'],
        [currentUser, 'Perfect. See you all there. 🧺'],
      ].map(([sender, body], i) =>
        makeMessage(conversation.id, sender as User, body as string, startedAt + i * 22 * MINUTE, 'read'),
      ),
    );
  }

  if (last && conversation.last_message === null) {
    conversation.last_message = last;
  }
}

export const messagesFor = (conversationId: ID): Message[] =>
  messagesByConversation.get(conversationId) ?? [];

// --------------------------------------------------------- notifications --

const NOTIFICATION_TEMPLATES: Array<{ type: string; text: string; icon: string }> = [
  { type: 'post_liked', text: 'reacted to your post.', icon: 'like' },
  { type: 'post_commented', text: 'commented on your post.', icon: 'comment' },
  { type: 'friend_request_received', text: 'sent you a friend request.', icon: 'friend' },
  { type: 'friend_request_accepted', text: 'accepted your friend request.', icon: 'friend' },
  { type: 'post_tagged', text: 'tagged you in a photo.', icon: 'tag' },
  { type: 'post_shared', text: 'shared your post.', icon: 'share' },
  { type: 'birthday', text: 'It is someone’s birthday today.', icon: 'birthday' },
  { type: 'group_invite', text: 'invited you to join Bread Appreciation Society.', icon: 'group' },
];

export const notifications: Notification[] = Array.from({ length: 14 }, (_, i) => {
  const template = pick(NOTIFICATION_TEMPLATES);
  const actor = pick(users.filter((u) => u.id !== currentUser.id));

  return {
    id: `n${i}`,
    type: template.type,
    text: `${actor.name.split(' ')[0]} ${template.text}`,
    icon: template.icon,
    actor,
    actors: [actor],
    subject_preview: chance(0.5) ? 'Shipping beats perfect.' : null,
    target_url: chance(0.6) ? `/post/${pick(posts).id}` : `/profile/${actor.username}`,
    is_read: i > 4,
    group_count: 1,
    created_at: agoIso(between(5, 60 * 72) * MINUTE),
  };
});

// ----------------------------------------------- friend requests & suggests --

export const friendRequests: FriendRequest[] = users
  .filter((u) => u.id !== currentUser.id && !friendIdsFor(currentUser.id).includes(u.id))
  .slice(0, 3)
  .map((user, i) => ({
    id: `fr${i}`,
    sender: user,
    recipient: currentUser,
    message: chance(0.5) ? 'Hey! Long time no see — how have you been?' : null,
    status: 'pending',
    mutual_friends_count: mutualFriendCount(currentUser.id, user.id),
    created_at: agoIso(between(2, 96) * HOUR),
  }));

export const suggestions: User[] = users
  .filter((u) => u.id !== currentUser.id && !friendIdsFor(currentUser.id).includes(u.id))
  .slice(3, 9);

// ---------------------------------------------------------------- stories --

export const storyTrays: StoryTray[] = [
  { user: currentUser, has_unseen: false, latest_at: agoIso(2 * HOUR) },
  ...friendsOf(currentUser.id).slice(0, 8).map((user, i) => ({ user, has_unseen: i < 5, latest_at: agoIso((i + 1) * 3 * HOUR) })),
].map((tray) => ({
  ...tray,
  stories: Array.from({ length: between(1, 3) }, (_, i) => ({
    id: `s-${tray.user.id}-${i}`,
    author: tray.user,
    type: 'photo' as const,
    body: chance(0.3) ? pick(['Sundays like this. ☕️', 'Five minutes of glory.', 'Morning light.']) : null,
    media: {
      id: `sm-${tray.user.id}-${i}`,
      type: 'image' as const,
      url: photo(`story-${tray.user.username}-${i}`, 1080, 1920),
      width: 1080,
      height: 1920,
    },
    has_unseen: tray.has_unseen,
    views_count: between(3, 120),
    created_at: agoIso(between(1, 20) * HOUR),
    expires_at: new Date(Date.now() + between(1, 20) * HOUR).toISOString(),
  })),
}));

// ----------------------------------------------------------------- groups --

interface GroupSpec {
  name: string;
  slug: string;
  description: string;
  privacy: Group['privacy'];
  type: Group['type'];
  topics: string[];
  members: number;
  /** How the demo account relates to this group. */
  membership: 'admin' | 'member' | 'none' | 'pending';
}

const GROUP_SPECS: GroupSpec[] = [
  {
    name: 'Kigali Founders',
    slug: 'kigali-founders',
    description:
      'A working group for people building companies in Kigali. Weekly threads on hiring, fundraising and the parts nobody warns you about.',
    privacy: 'public',
    type: 'general',
    topics: ['Startups', 'Kigali', 'Hiring'],
    members: 4_812,
    membership: 'admin',
  },
  {
    name: 'Design Systems Guild',
    slug: 'design-systems-guild',
    description:
      'Tokens, components, documentation and the politics of getting teams to adopt them. Critique threads on Wednesdays.',
    privacy: 'public',
    type: 'social_learning',
    topics: ['Design systems', 'Frontend'],
    members: 12_940,
    membership: 'member',
  },
  {
    name: 'Trail Runners Rwanda',
    slug: 'trail-runners-rwanda',
    description: 'Weekend long runs, route sharing and the annual Nyungwe climb. All paces welcome.',
    privacy: 'public',
    type: 'general',
    topics: ['Running', 'Outdoors'],
    members: 3_207,
    membership: 'member',
  },
  {
    name: 'Laravel & PHP East Africa',
    slug: 'laravel-php-east-africa',
    description: 'Jobs, meetups and code review for PHP developers across the region.',
    privacy: 'private_visible',
    type: 'social_learning',
    topics: ['PHP', 'Laravel'],
    members: 2_184,
    membership: 'none',
  },
  {
    name: 'Film Photography Swap',
    slug: 'film-photography-swap',
    description: 'Buy, sell and trade analog gear. Post your rolls — we want the bad shots too.',
    privacy: 'private_visible',
    type: 'buy_and_sell',
    topics: ['Photography', 'Marketplace'],
    members: 8_655,
    membership: 'pending',
  },
  {
    name: 'Sunday Sourdough',
    slug: 'sunday-sourdough',
    description: 'One bake a week, posted with its crumb shot. Starter troubleshooting welcome.',
    privacy: 'private_hidden',
    type: 'general',
    topics: ['Baking'],
    members: 941,
    membership: 'none',
  },
  {
    name: 'Marathon Training 2026',
    slug: 'marathon-training-2026',
    description: 'Sixteen weeks to race day. Plans posted every Monday, accountability threads daily.',
    privacy: 'public',
    type: 'general',
    topics: ['Running', 'Marathon'],
    members: 6_430,
    membership: 'none',
  },
];

function membershipFor(spec: GroupSpec): Group['membership'] {
  if (spec.membership === 'admin') return { is_member: true, role: 'admin', is_admin: true };
  if (spec.membership === 'member') return { is_member: true, role: 'member', is_admin: false };
  if (spec.membership === 'pending') return { is_member: false, role: null, is_admin: false };

  return { is_member: false, role: null, is_admin: false };
}

export const groups: Group[] = GROUP_SPECS.map((spec, index) => ({
  id: `g${index + 1}`,
  name: spec.name,
  slug: spec.slug,
  description: spec.description,
  type: spec.type,
  privacy: spec.privacy,
  cover_url: photo(`group-cover-${spec.slug}`, 1200, 400),
  avatar_url: photo(`group-avatar-${spec.slug}`, 200, 200),
  topics: spec.topics,
  members_count: spec.members,
  posts_count: between(40, 2_600),
  membership: membershipFor(spec),
  created_at: agoIso(between(30, 900) * 24 * HOUR),
}));

export const groupSpecs: Record<ID, GroupSpec> = Object.fromEntries(
  groups.map((group, index) => [group.id, GROUP_SPECS[index]!]),
);

export const groupRules: Record<ID, GroupRule[]> = Object.fromEntries(
  groups.map((group, index) => [
    group.id,
    [
      { id: `gr-${index}-1`, title: 'Be kind and courteous', description: 'No personal attacks or hate speech.' },
      {
        id: `gr-${index}-2`,
        title: 'No spam or self-promotion',
        description: 'Share your work in the weekly thread, not the main feed.',
      },
      { id: `gr-${index}-3`, title: 'Keep it on topic', description: 'Off-topic posts may be removed without notice.' },
    ],
  ]),
);

/** Members, including a couple of pending requests for the admin queue. */
export const groupMembers: Record<ID, GroupMember[]> = Object.fromEntries(
  groups.map((group) => [
    group.id,
    users.slice(0, 9).map((user, position) => ({
      id: `gm-${group.id}-${position}`,
      user,
      role:
        position === 0
          ? 'admin'
          : position === 1 && group.id === 'g1'
            ? 'moderator'
            : 'member',
      status: position > 6 && group.id === 'g1' ? 'pending' : 'active',
      badge: position === 0 ? 'founding_member' : position === 1 ? 'new_member' : null,
      joined_at: agoIso(between(1, 400) * 24 * HOUR),
    })),
  ]),
);

/** Each group gets a slice of the shared post pool — real posts, reused. */
export const groupPosts: Record<ID, ID[]> = Object.fromEntries(
  groups.map((group) => {
    const offset = Number(group.id.replace('g', '')) % 4;

    return [group.id, posts.slice(offset, offset + 3).map((post) => post.id)];
  }),
);

/** Posts waiting on an admin decision, per group (the moderation queue). */
export const pendingGroupPosts: Record<ID, ID[]> = Object.fromEntries(
  groups.map((group) => {
    const offset = Number(group.id.replace('g', '')) % 3;

    return [group.id, posts.slice(offset + 6, offset + 8).map((post) => post.id)];
  }),
);

// ------------------------------------------------------------------ reels --

/**
 * Public-domain / Creative Commons sample clips (Google's test bucket). They
 * are short (≈15s) and small (≈2 MB), which matters: a reel feed mounts several
 * `<video>` elements at once, and the point of the surface is the interaction
 * design rather than the footage.
 */
const REEL_SOURCES: string[] = [
  'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4',
  'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerEscapes.mp4',
  'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerFun.mp4',
  'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerJoyrides.mp4',
  'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerMeltdowns.mp4',
  'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/SubaruOutbackOnStreetAndDirt.mp4',
  'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/VolkswagenGTIReview.mp4',
  'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/WeAreGoingOnBullrun.mp4',
];

const REEL_CAPTIONS: string[] = [
  'Six hours of footage, ninety seconds of film. Worth every dropped frame. 🎬',
  'The light did all the work here — I just held the camera steady.',
  'POV: you finally fixed the thing you broke last weekend. 🔧',
  'Test render from the new setup. Still pinching myself.',
  'A very serious field test of a very unscientific hypothesis.',
  'Tutorial part 3 is up. This is the one everyone asks about.',
  'Shot on a phone, graded on a laptop, finished at 2am.',
  'The outtakes were better than the take. Here is the take.',
];

const REEL_AUDIO: Array<{ title: string; artist: string }> = [
  { title: 'Midnight City', artist: 'M83' },
  { title: 'Original audio', artist: '' },
  { title: 'Sunset Drive', artist: 'Kavinsky' },
  { title: 'Weightless', artist: 'Marconi Union' },
  { title: 'Nightcall', artist: 'Kavinsky' },
  { title: 'Original audio', artist: '' },
];

export const reels: Reel[] = users.slice(1, 13).map((user, index) => {
  const audio = pick(REEL_AUDIO);

  return {
    id: `r${index + 1}`,
    url: REEL_SOURCES[index % REEL_SOURCES.length]!,
    poster_url: photo(`reel-${user.username}-${index}`, 720, 1280),
    caption: pick(REEL_CAPTIONS),
    duration: between(12, 45),
    width: 720,
    height: 1280,
    audio: chance(0.8) ? { id: `ra-${index + 1}`, title: audio.title, artist: audio.artist || null } : null,
    creator: { ...user, is_following: chance(0.35) },
    views_count: between(1_200, 486_000),
    likes_count: between(80, 42_000),
    comments_count: between(4, 900),
    shares_count: between(2, 4_200),
    viewer_reaction: null,
    allows_remix: chance(0.7),
    created_at: agoIso(between(1, 200) * HOUR),
  };
});

export const reelComments = new Map<ID, Comment[]>(
  reels.map((reel) => [
    reel.id,
    Array.from({ length: between(2, 6) }, (_, i) => ({
      id: `rc-${reel.id}-${i}`,
      body: pick(COMMENT_BODIES),
      author: pick(users),
      parent_id: null,
      depth: 0,
      likes_count: between(0, 240),
      replies_count: 0,
      viewer_reaction: chance(0.15) ? ('like' as const) : null,
      created_at: new Date(+new Date(reel.created_at) + (i + 1) * between(3, 90) * MINUTE).toISOString(),
    })),
  ]),
);

// ------------------------------------------------------------------ extras --

export const onlineFriends: User[] = friendsOf(currentUser.id).filter((u) => u.is_online).slice(0, 12);

export const trendingTopics = [
  { id: 't1', name: 'Design Systems', posts: 128_000 },
  { id: 't2', name: 'Climate Tech', posts: 92_400 },
  { id: 't3', name: 'Marathon Season', posts: 61_800 },
  { id: 't4', name: 'Sourdough', posts: 44_100 },
  { id: 't5', name: 'Web Performance', posts: 31_500 },
];

/**
 * Simulated live traffic: used by the mock adapter to push a new message or
 * notification into the UI the way Pusher/Soketi would in production.
 */
export function incomingMessage(conversationId: ID): Message | null {
  const conversation = conversations.find((c) => c.id === conversationId);

  if (!conversation) return null;

  const other = conversation.participants.find((p) => p.id !== currentUser.id);

  if (!other) return null;

  const message = makeMessage(
    conversationId,
    other,
    pick(['Did you see this? 👀', 'Okay that works for me.', 'Sending it over now.', '😂😂😂', 'One sec — reading.']),
    Date.now(),
    'delivered',
  );

  messagesByConversation.get(conversationId)?.push(message);

  conversation.last_message = message;
  conversation.updated_at = message.created_at;
  conversation.unread_count += 1;

  return message;
}
