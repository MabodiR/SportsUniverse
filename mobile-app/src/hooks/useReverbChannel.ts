import { useEffect, useRef, useState } from 'react';
import { api } from '../api/client';

type Activity = Record<string, any>;

function settings() {
  const apiBase = api.defaults.baseURL ?? '';
  const match = apiBase.match(/^https?:\/\/([^/:]+)/i);
  const host = process.env.EXPO_PUBLIC_REVERB_HOST || match?.[1] || 'localhost';
  const secure = (process.env.EXPO_PUBLIC_REVERB_SCHEME || 'http') === 'https';
  return { host, secure, port: Number(process.env.EXPO_PUBLIC_REVERB_PORT || (secure ? 443 : 8080)), key: process.env.EXPO_PUBLIC_REVERB_APP_KEY || '' };
}

export function useReverbChannel(channel: string, onActivity: (activity: Activity) => void, events: string[] = ['live.activity']) {
  const callback = useRef(onActivity);
  const [connected, setConnected] = useState(false);
  callback.current = onActivity;
  const eventNames = useRef(events);
  eventNames.current = events;

  useEffect(() => {
    const config = settings();
    if (!config.key || !channel) return;
    let socket: WebSocket | null = null;
    let stopped = false;
    let retry = 0;
    let timer: ReturnType<typeof setTimeout> | undefined;

    const connect = () => {
      const scheme = config.secure ? 'wss' : 'ws';
      socket = new WebSocket(`${scheme}://${config.host}:${config.port}/app/${config.key}?protocol=7&client=js&version=8.4.0&flash=false`);
      socket.onopen = () => { retry = 0; };
      socket.onmessage = event => {
        try {
          const message = JSON.parse(String(event.data));
          if (message.event === 'pusher:connection_established') {
            const connection = typeof message.data === 'string' ? JSON.parse(message.data) : message.data;
            if (channel.startsWith('private-')) {
              const origin = (api.defaults.baseURL ?? '').replace(/\/api\/v1\/?$/, '');
              api.post(origin + '/broadcasting/auth', { socket_id: connection.socket_id, channel_name: channel }).then(response => socket?.send(JSON.stringify({ event: 'pusher:subscribe', data: { channel, auth: response.data.auth, channel_data: response.data.channel_data } }))).catch(() => socket?.close());
            } else socket?.send(JSON.stringify({ event: 'pusher:subscribe', data: { channel } }));
          }
          if (message.event === 'pusher_internal:subscription_succeeded') setConnected(true);
          if (message.event === 'pusher:ping') socket?.send(JSON.stringify({ event: 'pusher:pong', data: {} }));
          if (eventNames.current.includes(message.event)) callback.current({ event: message.event, ...(typeof message.data === 'string' ? JSON.parse(message.data) : message.data) });
        } catch { /* Ignore malformed websocket frames. */ }
      };
      socket.onerror = () => socket?.close();
      socket.onclose = () => { setConnected(false); if (!stopped) timer = setTimeout(connect, Math.min(1000 * 2 ** retry++, 15000)); };
    };
    connect();
    return () => { stopped = true; if (timer) clearTimeout(timer); socket?.close(); };
  }, [channel]);

  return { connected, configured: Boolean(settings().key) };
}
