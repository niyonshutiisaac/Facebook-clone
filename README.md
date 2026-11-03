# Avimoney

A production-grade Facebook core clone: the design system, the news feed with a
real ranking model, nested comments, reaction tray, profiles and real-time
messenger — built as a Laravel 11 API plus a React 18 + TypeScript SPA.

```
avimoney/
├── backend/     Laravel 11 · PHP 8.2 · MySQL 8 · Redis · Sanctum · Horizon
└── frontend/    React 18 · TypeScript · Vite · Tailwind · TanStack Query · Zustand
```

---

## Running the frontend

The SPA runs on its own and is fully explorable without the backend: when no API
is reachable it serves a deterministic in-browser mock of the same API contract
(see `frontend/src/api/mock.ts`).

```bash
cd frontend
npm install
npm run dev          # http://localhost:5173
```

| Script              | What it does                                              |
| ------------------- | --------------------------------------------------------- |
| `npm run dev`       | Vite dev server on `0.0.0.0:5173`                          |
| `npm run build`     | Type-check and build to `frontend/dist`                    |
| `npm run typecheck` | `tsc --noEmit`                                             |
| `npm run smoke`     | Boots the app in jsdom and asserts every route renders     |

`npm run smoke` is the reason this README can claim the UI works: there is no
browser in the build sandbox, so the app is mounted through Vite's SSR loader
inside jsdom and the resulting DOM is asserted.

```bash
npm run smoke                        # the feed
SMOKE_ROUTE=/messages npm run smoke  # messenger
SMOKE_ROUTE=/reels npm run smoke     # reels player
SMOKE_ROUTE=/groups npm run smoke    # group discovery
SMOKE_ROUTE='/search?q=marcus' npm run smoke
```

Each route has its own assertions (see `expectations` in
`frontend/scripts/smoke.mjs`) and every run also fails on any console error, so
a React warning that would become a blank screen in a browser fails the build.
`SMOKE_INTERACTION` dispatches a real click before asserting — `story-viewer`,
`open-comments`, `profile-about`, `open-reel-comments` and `group-about` are
wired today.

Demo account: any email address with a password of eight characters or more.

---

## Running the backend

The backend is real Laravel 11 source (migrations, models, services,
controllers, events, jobs, policies, seeders, tests). It needs PHP 8.2+,
Composer, MySQL 8 and Redis.

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed        # 12 demo users, friendships, posts, an inbox
php artisan serve
php artisan queue:work           # or: php artisan horizon
php artisan schedule:work        # feed scoring, story expiry, suggestions
```

Point the SPA at it by setting `VITE_USE_MOCKS=false` in `frontend/.env`.

### What the backend contains

| Layer | Highlights |
| --- | --- |
| **Schema** | 23 migrations · UUID primary keys · denormalised counters · named indexes · soft deletes on user content · deferred foreign keys for circular references |
| **Ranking** | `FeedRankingService` — 0.35 affinity + 0.25 quality + 0.25 engagement + 0.15 decay, exponential recency over a 72h horizon, author/type fatigue, session boost; two-tier cache (Redis + `newsfeed_cache`) behind a rebuild lock |
| **Auth** | Register/login/reset, TOTP + SMS two-factor, hashed single-use recovery codes, per-identity and per-IP lockout, device tracking, session management, optional email verification |
| **Messenger** | 1:1 deduplication via a deterministic `pair_key`, keyset pagination, read receipts per participant, delete-for-me vs delete-for-everyone, typing indicators, presence in Redis with self-expiring keys |
| **Privacy** | Per-field audience matrix, blocks, mutes, restrictions, friendship checks threaded through every query |
| **Real-time** | Private channels for conversations, notifications and feed updates; presence channel for the contacts rail |
| **Tests** | Feature tests for registration, lockout, post CRUD, reactions, comment depth, feed ranking and privacy, messenger delivery lifecycle, notification aggregation, TOTP; unit tests for cursors, enums and ranking inputs |

---

## Scope

The **vertical slice** is complete and taken to polished depth:

- ✅ Design system, three-column shell, responsive layouts, dark mode
- ✅ News feed with ranking, infinite scroll, story tray, composer
- ✅ Posts: text, photo/video grids, links, polls, feelings, shares, editing
- ✅ Reaction tray with hover intent, per-reaction counts and reactors
- ✅ Nested comments to three levels with lazy replies and tombstones
- ✅ Profiles: cover, avatar, tabs, intro, About, timeline
- ✅ Real-time messenger: thread, dock, typing, read receipts, emoji bubbles
- ✅ Friends: requests, accept/decline, suggestions, mutual friends
- ✅ Notifications with aggregation
- ✅ Stories: tray and full-screen viewer (segmented progress, hold to pause,
  tap zones, reactions, replies)
- ✅ Reels: vertical player, per-reel playback, action rail, comments drawer,
  follow, watch-time tracking for completion-rate ranking
- ✅ Groups: discovery, membership, discussion with approval rules, About,
  member management and an admin moderation queue
- ✅ Search: header type-ahead, tabbed results, recent searches, trending

Still to come (scaffolded, not polished): pages, events, marketplace and watch.
Their routes exist and say so honestly rather than faking a feature.

---

## Design system

Facebook's tokens live in `frontend/tailwind.config.ts` and
`frontend/src/styles/index.css`:

- Surfaces `#F0F2F5` / `#FFFFFF` (light) and `#18191A` / `#242526` / `#3A3B3C` (dark)
- Text `#050505` / `#65676B` / `#8A8D91` and their dark counterparts
- Brand `#1877F2`, primary action `#0866FF`, positive `#42B72A`, danger `#F02849`
- Facebook's own type scale — a 15px body with a 13px secondary label
- 8px cards, 6px controls, 18px chat bubbles, 56px header, 360px/350px rails
- Every colour is a CSS variable, so dark mode is a class flip rather than a
  second set of utilities
