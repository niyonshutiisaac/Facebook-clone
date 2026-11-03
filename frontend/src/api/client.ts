import axios, { AxiosError } from 'axios';
import { mockApi } from './mock';
import { useAuthStore } from '@/store/auth';

/**
 * Single point of contact with the backend.
 *
 * Two implementations sit behind the same interface:
 *  - `http` : axios against the Laravel API (production, and dev with Docker)
 *  - `mock` : an in-browser stand-in used when no backend is reachable
 *
 * The mock keeps the product explorable end-to-end — including in this sandbox,
 * where there is no PHP runtime — while every call site stays written against
 * the real API contract.
 */

export const USE_MOCKS = import.meta.env.VITE_USE_MOCKS !== 'false';

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? '/api',
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
});

// Attach the Sanctum token on the way out; most mutations need it.
api.interceptors.request.use((config) => {
  const token = useAuthStore.getState().token;

  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }

  return config;
});

// Normalise every failure into an Error with a useful message, and clear the
// session when the token has expired.
api.interceptors.response.use(
  (response) => response,
  (error: AxiosError<{ message?: string; errors?: Record<string, string[]> }>) => {
    if (error.response?.status === 401) {
      useAuthStore.getState().clear();
    }

    const message =
      error.response?.data?.message ??
      (error.code === 'ECONNABORTED' ? 'The request timed out.' : 'Something went wrong. Please try again.');

    return Promise.reject(new Error(message));
  },
);

/** Unwraps `response.data` so call sites read like plain function calls. */
export async function get<T>(url: string, params?: Record<string, unknown>): Promise<T> {
  if (USE_MOCKS) throw new Error('Mock mode: use the mockApi methods directly.');

  const { data } = await api.get<T>(url, { params });

  return data;
}

export async function post<T>(url: string, body?: unknown): Promise<T> {
  if (USE_MOCKS) throw new Error('Mock mode: use the mockApi methods directly.');

  const { data } = await api.post<T>(url, body);

  return data;
}

export async function put<T>(url: string, body?: unknown): Promise<T> {
  if (USE_MOCKS) throw new Error('Mock mode: use the mockApi methods directly.');

  const { data } = await api.put<T>(url, body);

  return data;
}

export async function del<T>(url: string): Promise<T> {
  if (USE_MOCKS) throw new Error('Mock mode: use the mockApi methods directly.');

  const { data } = await api.delete<T>(url);

  return data;
}

export { mockApi };
