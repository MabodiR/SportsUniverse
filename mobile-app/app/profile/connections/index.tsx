import { useState } from 'react';
import { ActivityIndicator, FlatList, Image, Pressable, RefreshControl, StyleSheet, Text, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { router, useLocalSearchParams } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useInfiniteQuery } from '@tanstack/react-query';
import { api } from '../../../src/api/client';
import { ScreenMessage } from '../../../src/components/ScreenMessage';
import { colors, radius, spacing } from '../../../src/theme';
import type { PaginatedResponse, Profile } from '../../../src/types/api';
import { absoluteMediaUrl } from '../../../src/utils/url';

type Tab = 'followers' | 'following';

export default function ConnectionsScreen() {
  const params = useLocalSearchParams<{ userId: string; tab?: string; name?: string }>();
  const [tab, setTab] = useState<Tab>(params.tab === 'following' ? 'following' : 'followers');
  const connections = useInfiniteQuery({
    queryKey: ['profile-connections', params.userId, tab],
    enabled: Boolean(params.userId),
    initialPageParam: 1,
    queryFn: async ({ pageParam }) => (await api.get<PaginatedResponse<Profile>>(`/profiles/${params.userId}/${tab}`, { params: { page: pageParam, per_page: 30 } })).data,
    getNextPageParam: page => page.meta.current_page < page.meta.last_page ? page.meta.current_page + 1 : undefined,
  });
  const items = connections.data?.pages.flatMap(page => page.data) ?? [];
  return <SafeAreaView edges={['top']} style={styles.safe}><View style={styles.header}><Pressable accessibilityLabel="Go back" onPress={() => router.back()}><Ionicons name="chevron-back" size={26} color="#fff" /></Pressable><View style={styles.heading}><Text numberOfLines={1} style={styles.headerTitle}>{params.name || 'Connections'}</Text><Text style={styles.headerMeta}>Connections</Text></View><View style={{ width: 26 }} /></View><View style={styles.tabs}><TabButton label="Followers" active={tab === 'followers'} onPress={() => setTab('followers')} /><TabButton label="Following" active={tab === 'following'} onPress={() => setTab('following')} /></View><FlatList data={items} keyExtractor={item => String(item.id)} contentContainerStyle={items.length ? styles.list : styles.empty} refreshControl={<RefreshControl refreshing={connections.isRefetching && !connections.isFetchingNextPage} onRefresh={() => connections.refetch()} tintColor={colors.blue} />} renderItem={({ item }) => <ConnectionRow item={item} />} ItemSeparatorComponent={() => <View style={{ height: 9 }} />} onEndReached={() => { if (connections.hasNextPage && !connections.isFetchingNextPage) connections.fetchNextPage(); }} onEndReachedThreshold={.4} ListFooterComponent={connections.isFetchingNextPage ? <ActivityIndicator style={{ margin: 20 }} color={colors.blue} /> : null} ListEmptyComponent={connections.isLoading ? <ActivityIndicator style={{ marginTop: 100 }} color={colors.blue} /> : connections.isError ? <ScreenMessage icon="cloud-offline-outline" title="Connections unavailable" message="Check your connection and try again." /> : <ScreenMessage icon="people-outline" title={tab === 'followers' ? 'No followers yet' : 'Not following anyone yet'} message={tab === 'followers' ? 'New followers will appear here.' : 'Profiles followed by this user will appear here.'} />} /></SafeAreaView>;
}

function TabButton({ label, active, onPress }: { label: string; active: boolean; onPress: () => void }) { return <Pressable accessibilityRole="tab" accessibilityState={{ selected: active }} onPress={onPress} style={[styles.tab, active && styles.tabActive]}><Text style={[styles.tabText, active && styles.tabTextActive]}>{label}</Text></Pressable>; }
function ConnectionRow({ item }: { item: Profile }) {
  const photo = absoluteMediaUrl(item.images.profile);
  const initials = item.name.split(/\s+/).slice(0, 2).map(part => part[0]).join('').toUpperCase();
  const detail = [item.athlete?.sport?.name || item.roles[0], item.athlete?.position?.name, item.location.city].filter(Boolean).join(' · ');
  return <Pressable onPress={() => router.push(`/profile/${item.slug}` as never)} style={styles.card}>{photo ? <Image source={{ uri: photo }} style={styles.avatar} /> : <View style={[styles.avatar, styles.fallback]}><Text style={styles.initials}>{initials}</Text></View>}<View style={styles.copy}><Text numberOfLines={1} style={styles.name}>{item.name}</Text><Text numberOfLines={1} style={styles.detail}>{detail || 'SportsUniverse member'}</Text><Text style={styles.followers}>{item.connections?.followers ?? 0} followers</Text></View><Ionicons name="chevron-forward" size={20} color={colors.muted} /></Pressable>;
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: colors.navy }, header: { minHeight: 58, paddingHorizontal: spacing.md, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', borderBottomWidth: 1, borderBottomColor: colors.line }, heading: { alignItems: 'center', maxWidth: '70%' }, headerTitle: { color: '#fff', fontSize: 16, fontWeight: '900' }, headerMeta: { color: colors.muted, fontSize: 9, marginTop: 2 }, tabs: { height: 48, margin: spacing.md, marginBottom: 2, padding: 3, flexDirection: 'row', borderRadius: radius.md, backgroundColor: colors.surface }, tab: { flex: 1, alignItems: 'center', justifyContent: 'center', borderRadius: 11 }, tabActive: { backgroundColor: colors.blue }, tabText: { color: colors.muted, fontSize: 12, fontWeight: '800' }, tabTextActive: { color: '#fff' }, list: { padding: spacing.md, paddingBottom: 40 }, empty: { flexGrow: 1 },
  card: { minHeight: 82, padding: 11, flexDirection: 'row', alignItems: 'center', gap: 12, borderWidth: 1, borderColor: colors.line, borderRadius: radius.lg, backgroundColor: colors.surface }, avatar: { width: 58, height: 58, borderRadius: 29, backgroundColor: colors.surfaceRaised }, fallback: { alignItems: 'center', justifyContent: 'center', backgroundColor: colors.blue }, initials: { color: '#fff', fontSize: 16, fontWeight: '900' }, copy: { flex: 1, minWidth: 0 }, name: { color: '#fff', fontSize: 14, fontWeight: '900' }, detail: { color: colors.muted, fontSize: 10, marginTop: 4 }, followers: { color: '#79A3FF', fontSize: 10, fontWeight: '800', marginTop: 5 },
});
