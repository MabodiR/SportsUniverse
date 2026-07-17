export function redirectSystemPath({ path }: { path: string; initial: boolean }): string {
  try {
    const url = new URL(path, 'sportuniverse://app');
    const rawPath = url.protocol === 'sportuniverse:' && url.hostname ? `/${url.hostname}${url.pathname}` : url.pathname;
    const pathname = rawPath.replace(/\/$/, '') || '/';

    if (/^\/@[^/]+$/.test(pathname)) return `/profile/${encodeURIComponent(pathname.slice(2))}`;
    if (/^\/clubs\/[^/]+$/.test(pathname)) return `/club/${encodeURIComponent(pathname.split('/')[2])}`;
    if (/^\/opportunities\/[^/]+$/.test(pathname)) return `/opportunity/${encodeURIComponent(pathname.split('/')[2])}`;
    if (/^\/live\/[^/]+$/.test(pathname)) return `/live/${encodeURIComponent(pathname.split('/')[2])}`;
    if (/^\/posts\/[^/]+/.test(pathname)) return `/post/${encodeURIComponent(pathname.split('/')[2])}`;
    if (/^\/password\/reset\/[^/]+$/.test(pathname)) return `/(auth)/reset-password?token=${encodeURIComponent(pathname.split('/')[3])}&email=${encodeURIComponent(url.searchParams.get('email') ?? '')}`;
    if (/^\/api\/v1\/auth\/email\/verify\/[^/]+\/[^/]+$/.test(pathname)) {
      const parts = pathname.split('/');
      return `/settings/verify-email?id=${encodeURIComponent(parts[6])}&hash=${encodeURIComponent(parts[7])}&expires=${encodeURIComponent(url.searchParams.get('expires') ?? '')}&signature=${encodeURIComponent(url.searchParams.get('signature') ?? '')}`;
    }
    if (pathname === '/feed' && url.hash.length > 1) return `/post/${encodeURIComponent(url.hash.slice(1))}`;
    if (pathname === '/feed') return '/(tabs)/feed';
    if (pathname === '/opportunities') return '/(tabs)/opportunities';
    if (pathname === '/live') return '/(tabs)/live';
    if (pathname === '/explore') return '/(tabs)/explore';
    return pathname;
  } catch {
    return '/(tabs)/feed';
  }
}
