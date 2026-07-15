import { api } from '../api/client';

export function absoluteMediaUrl(value?: string | null): string | undefined {
  if (!value) return undefined;
  const apiBase = api.defaults.baseURL ?? '';
  const origin = apiBase.replace(/\/api\/v1\/?$/, '');
  if (/^https?:\/\//i.test(value)) {
    try {
      const parsed = new URL(value);
      if (['localhost', '127.0.0.1'].includes(parsed.hostname) && origin) return origin + parsed.pathname + parsed.search;
    } catch { /* Keep the original absolute URL. */ }
    return value;
  }
  return `${origin}/${value.replace(/^\//, '')}`;
}
