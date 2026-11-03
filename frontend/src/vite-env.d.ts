/// <reference types="vite/client" />

interface ImportMetaEnv {
  readonly VITE_API_URL?: string;
  readonly VITE_API_PROXY?: string;
  readonly VITE_BROADCAST_DRIVER?: 'pusher' | 'soketi' | 'null';
  readonly VITE_PUSHER_KEY?: string;
  readonly VITE_PUSHER_HOST?: string;
  readonly VITE_PUSHER_PORT?: string;
  readonly VITE_USE_MOCKS?: string;
}

interface ImportMeta {
  readonly env: ImportMetaEnv;
}
