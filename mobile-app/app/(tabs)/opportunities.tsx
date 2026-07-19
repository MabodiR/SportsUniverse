import { useDeferredValue, useState } from 'react';
import { ActivityIndicator, FlatList, Pressable, RefreshControl, StyleSheet, Text, TextInput, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { router } from 'expo-router';
import { useInfiniteQuery } from '@tanstack/react-query';
import { BrandMark } from '../../src/components/BrandMark';
import { PrimaryButton } from '../../src/components/PrimaryButton';
import { ScreenMessage } from '../../src/components/ScreenMessage';
import { api } from '../../src/api/client';
import { useAuthStore } from '../../src/stores/auth';
import { colors, radius, spacing } from '../../src/theme';
import type { Opportunity, PaginatedResponse } from '../../src/types/api';

const types = [
  { value: '', label: 'All' },
  { value: 'trial', label: 'Trials' },
  { value: 'scholarship', label: 'Scholarships' },
  { value: 'job', label: 'Jobs' },
  { value: 'sponsorship', label: 'Sponsorships' },
];

export default function OpportunitiesScreen() {
  const user = useAuthStore(state => state.user);
  const [search, setSearch] = useState('');
  const [type, setType] = useState('');
  const query = useDeferredValue(search.trim());
  const opportunities = useInfiniteQuery({
    queryKey: ['opportunities', query, type],
    enabled: Boolean(user),
    initialPageParam: 1,
    queryFn: async ({ pageParam }) => (await api.get<PaginatedResponse<Opportunity>>('/opportunities', { params: { q: query || undefined, type: type || undefined, page: pageParam, per_page: 20 } })).data,
    getNextPageParam: page => page.meta.current_page < page.meta.last_page ? page.meta.current_page + 1 : undefined,
  });
  const items = opportunities.data?.pages.flatMap(page => page.data) ?? [];
  const canManage = user?.roles.some(role => ['club', 'academy', 'business', 'sponsor', 'admin'].includes(role));

  if (!user) return <SafeAreaView style={styles.safe}><View style={styles.header}><BrandMark /></View><ScreenMessage icon="briefcase-outline" title="Find your next opportunity" message="Sign in to browse trials, scholarships, jobs and sponsorships." action={<PrimaryButton label="Sign in to continue" onPress={() => router.push('/(auth)/login')} />} /></SafeAreaView>;

  return <SafeAreaView edges={['top']} style={styles.safe}>
    <View style={styles.header}><BrandMark /><View style={styles.headerActions}>{user.roles.includes('athlete') ? <Pressable accessibilityLabel="My applications" onPress={() => router.push('/applications')} style={styles.headerButton}><Ionicons name="document-text-outline" size={20} color="#fff" /></Pressable> : null}{canManage ? <Pressable accessibilityLabel="Manage opportunities" onPress={() => router.push('/opportunity/manage')} style={styles.headerButton}><Ionicons name="settings-outline" size={20} color="#fff" /></Pressable> : null}<Text style={styles.headerTitle}>Opportunities</Text></View></View>
    <View style={styles.searchWrap}><Ionicons name="search" size={20} color={colors.muted} /><TextInput accessibilityLabel="Search opportunities" value={search} onChangeText={setSearch} placeholder="Search opportunities" placeholderTextColor="#71849B" returnKeyType="search" style={styles.search} />{search ? <Pressable accessibilityLabel="Clear search" hitSlop={10} onPress={() => setSearch('')}><Ionicons name="close-circle" size={20} color={colors.muted} /></Pressable> : null}</View>
    <FlatList
      data={items}
      keyExtractor={item => item.id}
      contentContainerStyle={styles.list}
      keyboardShouldPersistTaps="handled"
      refreshControl={<RefreshControl refreshing={opportunities.isRefetching && !opportunities.isFetchingNextPage} onRefresh={() => opportunities.refetch()} tintColor={colors.blue} />}
      ListHeaderComponent={<View><FlatList horizontal data={types} keyExtractor={item => item.value || 'all'} showsHorizontalScrollIndicator={false} contentContainerStyle={styles.filters} renderItem={({ item }) => <Pressable accessibilityState={{ selected: type === item.value }} onPress={() => setType(item.value)} style={[styles.filter, type === item.value && styles.filterActive]}><Text style={[styles.filterText, type === item.value && styles.filterTextActive]}>{item.label}</Text></Pressable>} />{opportunities.data ? <Text style={styles.resultCount}>{opportunities.data.pages[0]?.meta.total ?? 0} open opportunities</Text> : null}</View>}
      ListEmptyComponent={opportunities.isLoading ? <ActivityIndicator style={styles.loader} color={colors.blue} /> : opportunities.isError ? <ScreenMessage icon="cloud-offline-outline" title="Opportunities unavailable" message="Check your connection and try again." action={<PrimaryButton label="Try again" secondary onPress={() => opportunities.refetch()} />} /> : <ScreenMessage icon="briefcase-outline" title="No opportunities found" message="Try another keyword or opportunity type." />}
      renderItem={({ item }) => <OpportunityCard item={item} />}
      ItemSeparatorComponent={() => <View style={{ height: 12 }} />}
      onEndReached={() => { if (opportunities.hasNextPage && !opportunities.isFetchingNextPage) opportunities.fetchNextPage(); }}
      onEndReachedThreshold={0.4}
      ListFooterComponent={opportunities.isFetchingNextPage ? <ActivityIndicator style={styles.footerLoader} color={colors.blue} /> : <View style={{ height: 24 }} />}
    />
  </SafeAreaView>;
}

function OpportunityCard({ item }: { item: Opportunity }) {
  const location = item.location.is_remote ? 'Remote' : [item.location.city, item.location.province].filter(Boolean).join(', ') || item.location.country || 'Location flexible';
  return <Pressable accessibilityRole="button" onPress={() => router.push(`/opportunity/${item.id}` as never)} style={({ pressed }) => [styles.card, pressed && styles.pressed]}>
    <View style={styles.cardTop}><View style={styles.typeBadge}><Text style={styles.typeText}>{item.type}</Text></View>{item.viewer.saved ? <Ionicons name="bookmark" size={19} color={colors.orange} /> : null}</View>
    <Text style={styles.title}>{item.title}</Text><Text style={styles.poster}>{item.poster.name}</Text>
    <View style={styles.meta}><Ionicons name={item.location.is_remote ? 'globe-outline' : 'location-outline'} size={15} color={colors.muted} /><Text style={styles.metaText}>{location}</Text></View>
    {item.sport ? <View style={styles.meta}><Ionicons name="football-outline" size={15} color={colors.muted} /><Text style={styles.metaText}>{[item.sport.name, item.position?.name].filter(Boolean).join(' · ')}</Text></View> : null}
    <View style={styles.cardBottom}><Text style={styles.deadline}>{deadlineLabel(item.deadline)}</Text>{item.viewer.applied ? <Text style={styles.applied}>Applied</Text> : <Ionicons name="arrow-forward" size={20} color="#79A3FF" />}</View>
  </Pressable>;
}

function deadlineLabel(value?: string | null) {
  if (!value) return 'Open until filled';
  const days = Math.ceil((new Date(value).getTime() - Date.now()) / 86400000);
  if (days <= 0) return 'Closing today';
  return days === 1 ? '1 day remaining' : `${days} days remaining`;
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: colors.navy }, header: { height: 58, paddingHorizontal: spacing.md, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', borderBottomWidth: 1, borderBottomColor: colors.line }, headerActions: { flexDirection: 'row', alignItems: 'center', gap: 7 }, headerButton: { width: 34, height: 34, borderRadius: 17, alignItems: 'center', justifyContent: 'center', backgroundColor: colors.surface }, headerTitle: { color: colors.white, fontSize: 13, fontWeight: '900' },
  searchWrap: { height: 50, margin: spacing.md, marginBottom: 4, paddingHorizontal: 14, flexDirection: 'row', alignItems: 'center', gap: 10, borderRadius: radius.md, borderWidth: 1, borderColor: colors.line, backgroundColor: colors.surface }, search: { flex: 1, height: '100%', color: colors.white, fontSize: 15 }, list: { flexGrow: 1, paddingHorizontal: spacing.md }, filters: { gap: 8, paddingTop: 10, paddingBottom: 12 }, filter: { paddingHorizontal: 15, paddingVertical: 9, borderRadius: radius.pill, borderWidth: 1, borderColor: colors.line, backgroundColor: colors.surface }, filterActive: { borderColor: colors.blue, backgroundColor: colors.blue }, filterText: { color: colors.muted, fontSize: 12, fontWeight: '800' }, filterTextActive: { color: '#fff' }, resultCount: { color: colors.muted, fontSize: 12, fontWeight: '700', marginBottom: 10 }, loader: { marginTop: 120 }, footerLoader: { marginVertical: 22 },
  card: { padding: 17, borderRadius: radius.lg, borderWidth: 1, borderColor: colors.line, backgroundColor: colors.surface }, pressed: { opacity: .78 }, cardTop: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }, typeBadge: { paddingHorizontal: 9, paddingVertical: 5, borderRadius: radius.pill, backgroundColor: 'rgba(71,111,234,.14)' }, typeText: { color: '#79A3FF', fontSize: 10, fontWeight: '900', textTransform: 'uppercase' }, title: { color: colors.white, fontSize: 18, lineHeight: 23, fontWeight: '900', marginTop: 12 }, poster: { color: '#C8D4E3', fontSize: 12, fontWeight: '700', marginTop: 4 }, meta: { flexDirection: 'row', alignItems: 'center', gap: 6, marginTop: 11 }, metaText: { color: colors.muted, fontSize: 12 }, cardBottom: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginTop: 16, paddingTop: 13, borderTopWidth: 1, borderTopColor: colors.line }, deadline: { color: colors.orange, fontSize: 11, fontWeight: '800' }, applied: { color: '#69DDA4', fontSize: 11, fontWeight: '900', textTransform: 'uppercase' },
});
