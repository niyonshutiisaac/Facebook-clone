/**
 * Headless smoke test.
 *
 * There is no browser in this sandbox (Chromium cannot be downloaded), so the
 * app is booted inside jsdom through Vite's SSR module loader. That exercises
 * the real module graph — React tree, hooks, routers, query cache and mock
 * adapter — and fails loudly on any error React would otherwise swallow into a
 * blank screen.
 */
import { writeSync } from 'node:fs';
import { JSDOM } from 'jsdom';
import { createServer } from 'vite';

/**
 * `process.exit()` truncates buffered stdout when it is piped, so every line is
 * written synchronously instead of through console.log.
 */
const log = (line = '') => writeSync(1, `${line}\n`);

const route = process.env.SMOKE_ROUTE ?? '/';
const url = new URL(route, 'http://localhost:5173/').href;

const dom = new JSDOM('<!doctype html><html><body><div id="root"></div></body></html>', {
  url,
  pretendToBeVisual: true,
});

const { window } = dom;

// --- globals React, React Router and our hooks expect -----------------------
// Rather than enumerating every DOM global, copy the jsdom window onto the Node
// global object. Anything already defined in Node (process, fetch, …) wins.
globalThis.window = window;
globalThis.document = window.document;

for (const key of Object.getOwnPropertyNames(window)) {
  if (key in globalThis) continue;

  try {
    const value = window[key];
    Object.defineProperty(globalThis, key, {
      get: () => (typeof value === 'function' && !value.prototype ? value.bind(window) : value),
      configurable: true,
    });
  } catch {
    // Some properties are not configurable; skipping them is fine.
  }
}

for (const key of ['navigator', 'location', 'history', 'localStorage', 'sessionStorage']) {
  Object.defineProperty(globalThis, key, {
    value: window[key],
    configurable: true,
    writable: true,
  });
}

const define = (key, value) =>
  Object.defineProperty(globalThis, key, { value, configurable: true, writable: true });

define('getComputedStyle', window.getComputedStyle.bind(window));
define('requestAnimationFrame', window.requestAnimationFrame.bind(window));
define('cancelAnimationFrame', window.cancelAnimationFrame.bind(window));
globalThis.matchMedia = window.matchMedia = (query) => ({
  matches: false,
  media: query,
  onchange: null,
  addListener() {},
  removeListener() {},
  addEventListener() {},
  removeEventListener() {},
  dispatchEvent: () => false,
});

class NoopObserver {
  observe() {}
  unobserve() {}
  disconnect() {}
  takeRecords() {
    return [];
  }
}

const noop = new NoopObserver();

define('IntersectionObserver', NoopObserver);
define('ResizeObserver', NoopObserver);
window.IntersectionObserver = NoopObserver;
window.ResizeObserver = NoopObserver;
void noop;

// jsdom ships no media stack: play()/pause() raise "Not implemented" through
// the virtual console, which the reel player trips on mount. Stubbing them
// keeps the noise — and the console-error assertion — meaningful.
for (const method of ['play', 'pause', 'load']) {
  window.HTMLMediaElement.prototype[method] = function playStub() {
    return Promise.resolve();
  };
}

const errors = [];
const originalError = console.error;

console.error = (...args) => {
  const text = args.map(String).join(' ');

  // React logs anticipated warnings (act, unknown props) that are not failures.
  if (!text.includes('not wrapped in act')) {
    errors.push(text);
  }

  originalError(...args);
};

const server = await createServer({
  configFile: false,
  root: process.cwd(),
  logLevel: 'error',
  server: { middlewareMode: true },
  appType: 'custom',
  resolve: { alias: { '@': new URL('../src', import.meta.url).pathname } },
});

const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

try {
  await server.ssrLoadModule('/src/main.tsx');

  // Let the query cache, mock latency and React effects settle.
  await wait(3000);

  // ------------------------------------------------------------------
  // Interactions. jsdom has no layout, so clicks are dispatched directly
  // rather than simulated through coordinates.
  // ------------------------------------------------------------------
  const interactions = {
    'story-viewer': () => {
      const tray = window.document.querySelector('[aria-label^="View "]');
      if (!tray) return 'no story tray found';
      tray.dispatchEvent(new window.MouseEvent('click', { bubbles: true }));
      return null;
    },
    'profile-about': () => {
      const button = [...window.document.querySelectorAll('button')].find(
        (node) => node.textContent?.trim() === 'About',
      );
      if (!button) return 'no About tab found';
      button.dispatchEvent(new window.MouseEvent('click', { bubbles: true }));
      return null;
    },
    'group-about': () => {
      const button = [...window.document.querySelectorAll('button')].find(
        (node) => node.textContent?.trim() === 'About',
      );
      if (!button) return 'no About tab found';
      button.dispatchEvent(new window.MouseEvent('click', { bubbles: true }));
      return null;
    },
    'open-reel-comments': () => {
      const button = window.document.querySelector('[aria-label^="Comments ("]');
      if (!button) return 'no reel comments button found';
      button.dispatchEvent(new window.MouseEvent('click', { bubbles: true }));
      return null;
    },
    'open-comments': () => {
      const button = [...window.document.querySelectorAll('button')].find((node) =>
        node.textContent?.trim() === 'Comment',
      );
      if (!button) return 'no comment button found';
      button.dispatchEvent(new window.MouseEvent('click', { bubbles: true }));
      return null;
    },
  };

  const interaction = process.env.SMOKE_INTERACTION;

  if (interaction && interactions[interaction]) {
    const error = interactions[interaction]();

    if (error) {
      log(`  FAIL  interaction: ${error}`);
      process.exit(1);
    }

    await wait(1500);
  }

  const root = window.document.getElementById('root');
  const html = root?.innerHTML ?? '';

  // Route-specific expectations. Anything outside the vertical slice is
  // allowed to be a placeholder; the core surfaces must actually render.
  const expectations = {
    '/': [
      ['renders the header', html.includes('Search Avimoney')],
      ['renders a story tray', html.includes('Create story')],
      ['renders the composer', html.includes("What's on your mind") || html.includes('What&#x27;s on your mind')],
      ['renders a post author', html.includes('Ava Chen') || html.includes('Marcus')],
      ['renders reaction buttons', html.includes('Like')],
      ['renders the contacts rail', html.includes('Contacts')],
    ],
    '/messages': [
      ['renders the chat list', html.includes('Messenger') || html.includes('Chats')],
      ['renders a conversation', html.includes('Marcus') || html.includes('Priya')],
    ],
    '/profile/marcusok': [
      ['renders the profile name', html.includes('Marcus Okafor')],
      ['renders the profile tabs', html.includes('About') && html.includes('Friends')],
      ['renders the intro card', html.includes('Intro')],
    ],
    '/friends': [
      ['renders friend requests', html.includes('Friend requests')],
    ],
    '/notifications': [
      ['renders notifications', html.includes('Notifications')],
    ],
    '/post/p0': [
      ['renders the permalink', html.includes('Back to feed')],
      ['opens the thread', html.includes('Write a comment')],
    ],
    '/search': [
      ['renders the search field', html.includes('Search Avimoney')],
      ['renders recent searches', html.includes('Recent searches')],
      ['renders trending', html.includes('Trending')],
    ],
    '/search?q=marcus': [
      ['renders the result tabs', html.includes('People') && html.includes('Groups')],
      ['renders people results', html.includes('Marcus')],
    ],
    '/groups': [
      ['renders the groups header', html.includes('Create group')],
      ['renders group cards', html.includes('Join group') || html.includes('Joined')],
      ['renders privacy labels', html.includes('members')],
    ],
    '/groups/kigali-founders': [
      ['renders the group name', html.includes('Kigali Founders')],
      ['renders the membership state', html.includes('Joined') || html.includes('Join group')],
      ['renders the sections', html.includes('Discussion') && html.includes('About')],
      ['renders the admin queue', html.includes('Pending posts')],
    ],
    '/groups/laravel-php-east-africa': [
      // `&` is escaped in the rendered HTML, so match on the words around it.
      ['renders a group the viewer has not joined', html.includes('Laravel') && html.includes('East Africa')],
      ['offers a join action', html.includes('Join group')],
      ['hides the admin queue', !html.includes('Pending posts')],
    ],
    '/reels': [
      ['renders the reels header', html.includes('Reels')],
      ['renders a reel surface', html.includes('aria-roledescription="video"')],
      ['renders the action rail', html.includes('More options')],
      ['renders the creator row', html.includes('Follow') || html.includes('Following')],
    ],
    '/profile/avachen': [
      ['renders the owner profile', html.includes('Ava Chen')],
      ['renders owner actions', html.includes('Edit profile')],
    ],
  };

  if (interaction === 'story-viewer') {
    expectations['/'] = [
      ...(expectations['/'] ?? []),
      ['opens the story viewer', html.includes('Reply to')],
    ];
  }

  if (interaction === 'profile-about') {
    expectations['/profile/marcusok'] = [
      ['renders the about sections', html.includes('Work and education')],
      ['renders the overview', html.includes('Overview')],
    ];
  }

  if (interaction === 'open-reel-comments') {
    expectations['/reels'] = [
      ...(expectations['/reels'] ?? []),
      ['opens the comments drawer', html.includes('Comments on')],
      ['renders the comment composer', html.includes('Add a comment')],
    ];
  }

  if (interaction === 'group-about') {
    expectations['/groups/kigali-founders'] = [
      ...(expectations['/groups/kigali-founders'] ?? []),
      ['renders the rules', html.includes('Group rules')],
      ['renders the admins', html.includes('Admins and moderators')],
    ];
  }

  if (interaction === 'open-comments') {
    expectations['/'] = [
      ...(expectations['/'] ?? []),
      ['expands the comment thread', html.includes('Write a comment')],
    ];
  }

  const checks = [
    ['renders something', html.length > 500],
    ...(expectations[route] ?? []),
    ['no console errors', errors.length === 0],
  ];

  let failed = 0;

  for (const [label, ok] of checks) {
    log(`${ok ? '  PASS' : '  FAIL'}  ${label}`);
    if (!ok) failed += 1;
  }

  if (errors.length > 0) {
    log('\n--- console output ---');
    errors.slice(0, 8).forEach((error) => log(error.slice(0, 400)));
  }

  log(`\n${failed === 0 ? 'SMOKE OK' : `SMOKE FAILED (${failed})`} — ${route} — ${html.length} bytes of DOM`);

  // jsdom timers keep the loop alive; exit explicitly once we have the verdict.
  process.exit(failed === 0 ? 0 : 1);
} catch (error) {
  log('SMOKE CRASHED: ' + String(error));
  process.exit(1);
} finally {
  await server.close();
  dom.window.close();
}
