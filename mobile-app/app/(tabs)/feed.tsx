import { useCallback, useMemo, useRef, useState } from 'react';
import { ActivityIndicator, FlatList, Pressable, StyleSheet, Text, View, useWindowDimensions } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { router } from 'expo-router';
import { SafeAreaView, useSafeAreaInsets } from 'react-native-safe-area-context';
import { useInfiniteQuery, useQuery } from '@tanstack/react-query';
import { BrandMark } from '../../src/components/BrandMark';
import { FeedCard } from '../../src/components/FeedCard';
import { AuthGate } from '../../src/components/AuthGate';
import { api } from '../../src/api/client';
import { useAuthStore } from '../../src/stores/auth';
import { colors } from '../../src/theme';
import type { Video } from '../../src/types/api';

type FeedMode = 'for-you' | 'following' | 'saved';
type FeedPage = { data: Video[]; meta?: { next_cursor?: string | null; current_page?: number; last_page?: number } };
const modes: { key: FeedMode; label: string }[] = [{ key: 'for-you', label: 'For You' }, { key: 'following', label: 'Following' }, { key: 'saved', label: 'Saved' }];
const demos: Video[] = [
  { id: 'demo-1', creator: { id: 1, name: 'Thabo Mokoena', sport: 'Football', position: 'Midfielder', city: 'Johannesburg' }, caption: 'Turning pressure into possibility. One touch, one chance, one goal.', hashtags: ['Football', 'RisingTalent', 'SouthAfrica'], counts: { views: 12840, likes: 2840, comments: 196, shares: 84, saves: 310 } },
  { id: 'demo-2', creator: { id: 2, name: 'Naledi Dlamini', sport: 'Netball', position: 'Goal Attack', city: 'Pretoria' }, caption: 'Speed, vision and the courage to take the shot.', hashtags: ['Netball', 'WomenInSport', 'NextGeneration'], counts: { views: 9200, likes: 1840, comments: 122, shares: 64, saves: 205 } },
  { id: 'demo-3', creator: { id: 3, name: 'Lwazi Khumalo', sport: 'Athletics', position: 'Sprinter', city: 'Durban' }, caption: 'The work nobody sees creates the result everybody remembers.', hashtags: ['Sprinting', 'Training', 'RoadToGold'], counts: { views: 7400, likes: 1220, comments: 88, shares: 42, saves: 174 } },
];

export default function FeedScreen() {
  const user = useAuthStore(state => state.user);
  const [gate, setGate] = useState(false);
  const [mode, setMode] = useState<FeedMode>('for-you');
  const [activeId, setActiveId] = useState<string>();
  const { height } = useWindowDimensions();
  const insets = useSafeAreaInsets();
  const cardHeight = height - insets.top - 70 - 96;
  const query = useInfiniteQuery({
    queryKey: ['feed', mode],
    initialPageParam: undefined as string | number | undefined,
    queryFn: async ({ pageParam }) => {
      const endpoint = mode === 'saved' ? '/feed/saved' : '/feed/' + mode;
      const params = pageParam === undefined ? undefined : mode === 'saved' ? { page: pageParam } : { cursor: pageParam };
      return (await api.get(endpoint, { params })).data as FeedPage;
    },
    getNextPageParam: page => mode === 'saved' ? ((page.meta?.current_page ?? 1) < (page.meta?.last_page ?? 1) ? (page.meta?.current_page ?? 1) + 1 : undefined) : page.meta?.next_cursor || undefined,
    enabled: Boolean(user),
  });
  const unread = useQuery({ queryKey: ['notifications', 'unread-count'], queryFn: async () => Number((await api.get('/notifications/unread-count')).data.data.unread_count), enabled: Boolean(user), refetchInterval: 15000 });
  const feed = useMemo(() => user ? (query.data?.pages.flatMap(page => page.data) ?? []) : demos, [query.data, user]);
  const viewability = useRef({ itemVisiblePercentThreshold: 60 }).current;
  const changed = useCallback(({ viewableItems }: any) => { const item = viewableItems[0]; setActiveId(item?.item?.id); const index = item?.index ?? 0; if (!user && index >= 2) setGate(true); }, [user]);
  const protectedAction = useCallback(() => { if (!user) setGate(true); }, [user]);
  const chooseMode = (next: FeedMode) => { if (!user && next !== 'for-you') return setGate(true); setActiveId(undefined); setMode(next); };

  return <SafeAreaView edges={['top']} style={styles.safe}>
    <View style={styles.header}><View style={styles.headerTop}><BrandMark /><View style={styles.headerActions}><Pressable accessibilityLabel="Notifications" hitSlop={10} onPress={() => user ? router.push('/notifications') : setGate(true)} style={styles.headerIcon}><Ionicons name="notifications-outline" size={21} color="#fff" />{unread.data ? <View style={styles.badge}><Text style={styles.badgeText}>{Math.min(unread.data, 99)}</Text></View> : null}</Pressable><Pressable accessibilityLabel="Upload post" hitSlop={10} onPress={() => user ? router.push('/upload') : setGate(true)} style={styles.upload}><Ionicons name="add" size={22} color="#fff" /></Pressable></View></View><View style={styles.switcher}>{modes.map(item => <Pressable accessibilityRole="tab" accessibilityState={{ selected: mode === item.key }} key={item.key} onPress={() => chooseMode(item.key)} style={[styles.mode, mode === item.key && styles.modeActive]}><Text style={[styles.modeText, mode === item.key && styles.modeTextActive]}>{item.label}</Text></Pressable>)}</View></View>
    {query.isLoading && user ? <ActivityIndicator style={styles.flex} color={colors.blue} /> : <FlatList key={mode} data={feed} keyExtractor={item => item.id} renderItem={({ item, index }) => <FeedCard video={item} index={index} height={cardHeight} active={activeId === item.id} authenticated={Boolean(user)} onProtectedAction={protectedAction} />} pagingEnabled showsVerticalScrollIndicator={false} scrollEnabled={!gate} onViewableItemsChanged={changed} viewabilityConfig={viewability} snapToAlignment="start" decelerationRate="fast" refreshing={Boolean(user && query.isRefetching && !query.isFetchingNextPage)} onRefresh={user ? () => query.refetch() : undefined} onEndReached={() => { if (query.hasNextPage && !query.isFetchingNextPage) query.fetchNextPage(); }} onEndReachedThreshold={0.6} ListFooterComponent={query.isFetchingNextPage ? <ActivityIndicator style={styles.footer} color={colors.blue} /> : null} ListEmptyComponent={user ? <EmptyFeed mode={mode} onExplore={() => mode === 'saved' ? chooseMode('for-you') : router.push('/explore')} /> : null} />}
    <AuthGate visible={gate} onBack={() => setGate(false)} />
  </SafeAreaView>;
}

function EmptyFeed({ mode, onExplore }: { mode: FeedMode; onExplore: () => void }) { const saved = mode === 'saved'; return <View style={styles.empty}><Ionicons name={saved ? 'bookmark-outline' : 'people-outline'} size={48} color={colors.muted} /><Text style={styles.emptyTitle}>{saved ? 'No saved posts yet' : mode === 'following' ? 'Your following feed is empty' : 'No posts available'}</Text><Text style={styles.emptyCopy}>{saved ? 'Save highlights to watch them again here.' : mode === 'following' ? 'Discover athletes and follow the ones who inspire you.' : 'New sporting moments will appear here.'}</Text>{mode !== 'for-you' ? <Pressable onPress={onExplore} style={styles.discover}><Text style={styles.discoverText}>{saved ? 'Browse For You' : 'Discover athletes'}</Text></Pressable> : null}</View>; }

const styles = StyleSheet.create({ safe: { flex: 1, backgroundColor: colors.navy }, flex: { flex: 1 }, header: { height: 96, paddingHorizontal: 14, backgroundColor: 'rgba(9,23,37,.98)', borderBottomWidth: 1, borderBottomColor: colors.line }, headerTop: { height: 50, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }, headerActions: { flexDirection: 'row', alignItems: 'center', gap: 9 }, headerIcon: { width: 34, height: 34, borderRadius: 17, alignItems: 'center', justifyContent: 'center', backgroundColor: colors.surface }, badge: { position: 'absolute', right: -2, top: -3, minWidth: 17, height: 17, paddingHorizontal: 4, borderRadius: 9, alignItems: 'center', justifyContent: 'center', backgroundColor: colors.pink }, badgeText: { color: '#fff', fontSize: 8, fontWeight: '900' }, upload: { width: 34, height: 34, borderRadius: 17, alignItems: 'center', justifyContent: 'center', backgroundColor: colors.blue }, switcher: { height: 38, flexDirection: 'row', padding: 3, borderRadius: 12, backgroundColor: colors.surface }, mode: { flex: 1, alignItems: 'center', justifyContent: 'center', borderRadius: 9 }, modeActive: { backgroundColor: colors.blue }, modeText: { color: colors.muted, fontSize: 12, fontWeight: '800' }, modeTextActive: { color: '#fff' }, footer: { paddingVertical: 18 }, empty: { height: 420, paddingHorizontal: 36, alignItems: 'center', justifyContent: 'center' }, emptyTitle: { marginTop: 14, color: '#fff', fontSize: 18, fontWeight: '900', textAlign: 'center' }, emptyCopy: { marginTop: 7, color: colors.muted, lineHeight: 20, textAlign: 'center' }, discover: { marginTop: 20, paddingHorizontal: 18, paddingVertical: 11, borderRadius: 12, backgroundColor: colors.blue }, discoverText: { color: '#fff', fontWeight: '800' } });
