import { Share } from 'react-native';
import { api } from '../api/client';

export function webUrl(path: string): string {
  const configured = process.env.EXPO_PUBLIC_WEB_URL?.replace(/\/$/, '');
  const origin = configured || (api.defaults.baseURL ?? '').replace(/\/api\/v1\/?$/, '');
  return `${origin}/${path.replace(/^\//, '')}`;
}

export async function shareLink(title: string, message: string, path: string) {
  const url = webUrl(path);
  return Share.share({ title, message: `${message}\n${url}`, url });
}
