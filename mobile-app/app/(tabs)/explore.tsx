import { useDeferredValue, useState } from 'react';
import { ActivityIndicator, Alert, FlatList, Image, Pressable, RefreshControl, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { router } from 'expo-router';
import { useInfiniteQuery, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { BrandMark } from '../../src/components/BrandMark';
import { PrimaryButton } from '../../src/components/PrimaryButton';
import { ScreenMessage } from '../../src/components/ScreenMessage';
import { api } from '../../src/api/client';
import { useAuthStore } from '../../src/stores/auth';
import { colors, radius, spacing } from '../../src/theme';
import type { PaginatedResponse, Profile } from '../../src/types/api';
import { absoluteMediaUrl } from '../../src/utils/url';

const roles = [
  { value: '', label: 'All' },
  { value: 'athlete', label: 'Athletes' },
  { value: 'coach', label: 'Coaches' },
  { value: 'scout', label: 'Scouts' },
  { value: 'club', label: 'Clubs' },
];

export default function ExploreScreen() {
  const user = useAuthStore(state => state.user);
  const client = useQueryClient();
  const [search, setSearch] = useState('');
  const [role, setRole] = useState('');
  const query = useDeferredValue(search.trim());
  const history = useQuery({ queryKey: ['search-history'], enabled: Boolean(user), queryFn: async () => (await api.get<{ data: { query: string; searched_at: string }[] }>('/search/history')).data.data });
  const saved = useQuery({ queryKey: ['saved-searches'], enabled: Boolean(user), queryFn: async () => (await api.get<{ data: { id: number; name: string; query?: string | null; filters?: { role?: string } }[] }>('/saved-searches')).data.data });
  const saveSearch = useMutation({ mutationFn: () => api.post('/saved-searches', { name: search.trim(), query: search.trim(), filters: { role } }), onSuccess: () => { client.invalidateQueries({ queryKey: ['saved-searches'] }); Alert.alert('Search saved', 'You can run it again from Explore.'); }, onError: (error: any) => Alert.alert('Search not saved', error?.response?.data?.message || 'Please try again.') });
  const removeSearch = useMutation({ mutationFn: (id: number) => api.delete('/saved-searches/' + id), onSuccess: () => client.invalidateQueries({ queryKey: ['saved-searches'] }) });

  const profiles = useInfiniteQuery({
    queryKey: ['profiles', 'search', query, role],
    enabled: Boolean(user),
    initialPageParam: 1,
    queryFn: async ({ pageParam }) => (await api.get<PaginatedResponse<Profile>>('/search/profiles', {
      params: { q: query || undefined, role: role || undefined, page: pageParam, per_page: 20 },
    })).data,
    getNextPageParam: page => page.meta.current_page < page.meta.last_page ? page.meta.current_page + 1 : undefined,
  });

  const items = profiles.data?.pages.flatMap(page => page.data) ?? [];

  if (!user) {
    return <SafeAreaView style={styles.safe}><View style={styles.header}><BrandMark /></View><ScreenMessage icon="search" title="Discover the next generation" message="Sign in to search athletes, coaches, scouts and sports organisations." action={<PrimaryButton label="Sign in to discover" onPress={() => router.push('/(auth)/login')} />} /></SafeAreaView>;
  }

  return (
    <SafeAreaView edges={['top']} style={styles.safe}>
      <View style={styles.header}><BrandMark /><View style={styles.headerButtons}><Pressable accessibilityLabel="Women in Sports" onPress={() => router.push('/explore/women')} style={styles.headerAction}><Ionicons name="female-outline" size={20} color={colors.pink} /></Pressable><Text style={styles.headerTitle}>Discover</Text></View></View>
      <View style={styles.searchWrap}>
        <Ionicons name="search" size={20} color={colors.muted} />
        <TextInput
          accessibilityLabel="Search talent"
          autoCapitalize="none"
          autoCorrect={false}
          onChangeText={setSearch}
          placeholder="Search name, sport, city or club"
          placeholderTextColor="#71849B"
          returnKeyType="search"
          style={styles.search}
          value={search}
        />
        {search ? <Pressable accessibilityLabel="Clear search" hitSlop={10} onPress={() => setSearch('')}><Ionicons name="close-circle" size={20} color={colors.muted} /></Pressable> : null}
      </View>
      <FlatList
        data={items}
        keyExtractor={item => String(item.id)}
        keyboardShouldPersistTaps="handled"
        contentContainerStyle={styles.list}
        refreshControl={<RefreshControl refreshing={profiles.isRefetching && !profiles.isFetchingNextPage} onRefresh={() => profiles.refetch()} tintColor={colors.blue} />}
        ListHeaderComponent={<View>{!search && (saved.data?.length || history.data?.length) ? <View style={styles.library}>{saved.data?.length ? <><Text style={styles.libraryTitle}>Saved searches</Text><ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.libraryRow}>{saved.data.map(item => <View key={item.id} style={styles.savedChip}><Pressable onPress={() => { setSearch(item.query || ''); setRole(item.filters?.role || ''); }}><Text style={styles.savedText}>{item.name}</Text></Pressable><Pressable accessibilityLabel={`Delete ${item.name}`} onPress={() => removeSearch.mutate(item.id)}><Ionicons name="close" size={15} color={colors.muted} /></Pressable></View>)}</ScrollView></> : null}{history.data?.length ? <><View style={styles.libraryHeading}><Text style={styles.libraryTitle}>Recent</Text><Pressable onPress={() => api.delete('/search/history').then(() => client.invalidateQueries({ queryKey: ['search-history'] }))}><Text style={styles.clearText}>Clear</Text></Pressable></View><ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.libraryRow}>{history.data.slice(0, 8).map(item => <Pressable key={item.query} onPress={() => setSearch(item.query)} style={styles.recentChip}><Ionicons name="time-outline" size={14} color={colors.muted} /><Text style={styles.savedText}>{item.query}</Text></Pressable>)}</ScrollView></> : null}</View> : null}<FlatList horizontal data={roles} keyExtractor={item => item.value || 'all'} showsHorizontalScrollIndicator={false} contentContainerStyle={styles.filters} renderItem={({ item }) => <Pressable accessibilityRole="button" accessibilityState={{ selected: role === item.value }} onPress={() => setRole(item.value)} style={[styles.filter, role === item.value && styles.filterActive]}><Text style={[styles.filterText, role === item.value && styles.filterTextActive]}>{item.label}</Text></Pressable>} /><View style={styles.resultRow}>{profiles.data ? <Text style={styles.resultCount}>{profiles.data.pages[0]?.meta.total ?? 0} profiles</Text> : <View />}{search.trim() ? <Pressable disabled={saveSearch.isPending} onPress={() => saveSearch.mutate()} style={styles.saveButton}><Ionicons name="bookmark-outline" size={14} color="#79A3FF" /><Text style={styles.saveButtonText}>Save search</Text></Pressable> : null}</View></View>}
        ListEmptyComponent={profiles.isLoading ? <ActivityIndicator style={styles.loader} color={colors.blue} /> : profiles.isError ? <ScreenMessage icon="cloud-offline-outline" title="Discovery is unavailable" message="Check your connection and try again." action={<PrimaryButton label="Try again" secondary onPress={() => profiles.refetch()} />} /> : <ScreenMessage icon="search-outline" title="No profiles found" message="Try a different name, sport, location or role." />}
        renderItem={({ item }) => <ProfileCard profile={item} />}
        ItemSeparatorComponent={() => <View style={{ height: 12 }} />}
        onEndReached={() => { if (profiles.hasNextPage && !profiles.isFetchingNextPage) profiles.fetchNextPage(); }}
        onEndReachedThreshold={0.4}
        ListFooterComponent={profiles.isFetchingNextPage ? <ActivityIndicator style={styles.footerLoader} color={colors.blue} /> : <View style={{ height: 24 }} />}
      />
    </SafeAreaView>
  );
}

function ProfileCard({ profile }: { profile: Profile }) {
  const image = absoluteMediaUrl(profile.images.profile);
  const initials = profile.name.split(/\s+/).slice(0, 2).map(part => part[0]).join('').toUpperCase();
  const role = profile.athlete?.sport?.name ?? profile.roles[0] ?? 'Member';
  const detail = [profile.athlete?.position?.name, profile.location.city].filter(Boolean).join(' · ');

  return (
    <Pressable accessibilityRole="button" onPress={() => router.push(profile.club?.slug ? `/club/${profile.club.slug}` as never : `/profile/${profile.slug}` as never)} style={({ pressed }) => [styles.card, pressed && styles.cardPressed]}>
      {image ? <Image source={{ uri: image }} style={styles.avatar} /> : <View style={styles.avatarFallback}><Text style={styles.initials}>{initials}</Text></View>}
      <View style={styles.cardContent}>
        <View style={styles.nameRow}><Text numberOfLines={1} style={styles.name}>{profile.name}</Text>{profile.is_available ? <View accessibilityLabel="Available" style={styles.available} /> : null}</View>
        <Text numberOfLines={1} style={styles.role}>{role}</Text>
        {detail ? <Text numberOfLines={1} style={styles.detail}>{detail}</Text> : null}
        {profile.bio ? <Text numberOfLines={2} style={styles.bio}>{profile.bio}</Text> : null}
      </View>
      <Ionicons name="chevron-forward" size={20} color="#60758E" />
    </Pressable>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: colors.navy },
  header: { height: 58, paddingHorizontal: spacing.md, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', borderBottomWidth: 1, borderBottomColor: colors.line },
  headerTitle: { color: colors.white, fontSize: 15, fontWeight: '900' },
  headerButtons: { flexDirection: 'row', alignItems: 'center', gap: 12 },
  headerAction: { width: 36, height: 36, borderRadius: 18, alignItems: 'center', justifyContent: 'center', backgroundColor: 'rgba(230,70,162,.12)' },
  searchWrap: { height: 50, margin: spacing.md, marginBottom: 4, paddingHorizontal: 14, flexDirection: 'row', alignItems: 'center', gap: 10, borderRadius: radius.md, borderWidth: 1, borderColor: colors.line, backgroundColor: colors.surface },
  search: { flex: 1, height: '100%', color: colors.white, fontSize: 15 },
  list: { flexGrow: 1, paddingHorizontal: spacing.md },
  filters: { gap: 8, paddingTop: 10, paddingBottom: 12 },
  filter: { paddingHorizontal: 15, paddingVertical: 9, borderRadius: radius.pill, borderWidth: 1, borderColor: colors.line, backgroundColor: colors.surface },
  filterActive: { borderColor: colors.blue, backgroundColor: colors.blue },
  filterText: { color: colors.muted, fontSize: 12, fontWeight: '800' },
  filterTextActive: { color: '#fff' },
  library: { paddingTop: 10 }, libraryTitle: { color: '#fff', fontSize: 12, fontWeight: '900', marginBottom: 8 }, libraryHeading: { marginTop: 13, flexDirection: 'row', justifyContent: 'space-between' }, libraryRow: { gap: 8, paddingBottom: 3 }, savedChip: { minHeight: 34, paddingHorizontal: 12, flexDirection: 'row', alignItems: 'center', gap: 8, borderRadius: radius.pill, backgroundColor: colors.surfaceRaised }, recentChip: { minHeight: 34, paddingHorizontal: 12, flexDirection: 'row', alignItems: 'center', gap: 6, borderRadius: radius.pill, borderWidth: 1, borderColor: colors.line }, savedText: { color: '#DCE7F5', fontSize: 11, fontWeight: '700' }, clearText: { color: colors.danger, fontSize: 10, fontWeight: '800' },
  resultRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginBottom: 10 }, resultCount: { color: colors.muted, fontSize: 12, fontWeight: '700' }, saveButton: { flexDirection: 'row', alignItems: 'center', gap: 5 }, saveButtonText: { color: '#79A3FF', fontSize: 11, fontWeight: '900' },
  loader: { marginTop: 120 },
  footerLoader: { marginVertical: 22 },
  card: { minHeight: 110, flexDirection: 'row', alignItems: 'center', gap: 13, padding: 14, borderRadius: radius.lg, borderWidth: 1, borderColor: colors.line, backgroundColor: colors.surface },
  cardPressed: { opacity: 0.78, transform: [{ scale: 0.995 }] },
  avatar: { width: 68, height: 68, borderRadius: 34, backgroundColor: colors.navyLight },
  avatarFallback: { width: 68, height: 68, borderRadius: 34, alignItems: 'center', justifyContent: 'center', backgroundColor: colors.blue },
  initials: { color: '#fff', fontSize: 19, fontWeight: '900' },
  cardContent: { flex: 1, minWidth: 0 },
  nameRow: { flexDirection: 'row', alignItems: 'center', gap: 7 },
  name: { flexShrink: 1, color: colors.white, fontSize: 16, fontWeight: '900' },
  available: { width: 8, height: 8, borderRadius: 4, backgroundColor: colors.green },
  role: { color: '#79A3FF', fontSize: 12, fontWeight: '800', textTransform: 'capitalize', marginTop: 3 },
  detail: { color: colors.muted, fontSize: 12, marginTop: 3 },
  bio: { color: '#C8D4E3', fontSize: 12, lineHeight: 17, marginTop: 7 },
});
