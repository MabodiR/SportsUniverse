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
  const recentSearches = Array.from(new Map((history.data ?? []).map(item => [item.query.trim().toLowerCase(), item])).values()).slice(0, 8);

  if (!user) {
    return <SafeAreaView style={styles.safe}><View style={styles.header}><BrandMark /></View><ScreenMessage icon="search" title="Discover the next generation" message="Sign in to search athletes, coaches, scouts and sports organisations." action={<PrimaryButton label="Sign in to discover" onPress={() => router.push('/(auth)/login')} />} /></SafeAreaView>;
  }

  return (
    <SafeAreaView edges={['top']} style={styles.safe}>
      <View style={styles.header}><BrandMark darkText /><Pressable accessibilityLabel="Women in Sports" onPress={() => router.push('/explore/women')} style={styles.headerAction}><Ionicons name="female-outline" size={20} color={colors.pink} /><Text style={styles.headerActionText}>Women in sport</Text></Pressable></View>
      <FlatList
        data={items}
        keyExtractor={item => String(item.id)}
        keyboardShouldPersistTaps="handled"
        contentContainerStyle={styles.list}
        refreshControl={<RefreshControl refreshing={profiles.isRefetching && !profiles.isFetchingNextPage} onRefresh={() => profiles.refetch()} tintColor={colors.blue} />}
        ListHeaderComponent={<View><View style={styles.intro}><Text style={styles.eyebrow}>FIND YOUR NEXT CONNECTION</Text><Text style={styles.title}>Discover SportsUniverse</Text><Text style={styles.subtitle}>Find athletes, coaches, scouts and clubs across South Africa.</Text></View><View style={styles.searchWrap}><Ionicons name="search" size={20} color="#728299" /><TextInput accessibilityLabel="Search talent" autoCapitalize="none" autoCorrect={false} onChangeText={setSearch} placeholder="Name, sport, city or club" placeholderTextColor="#8794A7" returnKeyType="search" style={styles.search} value={search} />{search ? <Pressable accessibilityLabel="Clear search" hitSlop={10} onPress={() => setSearch('')}><Ionicons name="close" size={20} color="#728299" /></Pressable> : null}</View><View style={styles.filterHeading}><Text>Browse by profile</Text>{role ? <Pressable onPress={() => setRole('')}><Text style={styles.resetText}>Reset</Text></Pressable> : null}</View><FlatList horizontal data={roles} keyExtractor={item => item.value || 'all'} showsHorizontalScrollIndicator={false} contentContainerStyle={styles.filters} renderItem={({ item }) => <Pressable accessibilityRole="button" accessibilityState={{ selected: role === item.value }} onPress={() => setRole(item.value)} style={[styles.filter, role === item.value && styles.filterActive]}>{role === item.value ? <Ionicons name="checkmark-circle" size={15} color="#fff" /> : null}<Text style={[styles.filterText, role === item.value && styles.filterTextActive]}>{item.label}</Text></Pressable>} />{!search && (saved.data?.length || recentSearches.length) ? <View style={styles.library}>{saved.data?.length ? <><View style={styles.libraryHeading}><View><Text style={styles.libraryTitle}>Saved searches</Text><Text style={styles.librarySubtitle}>Jump back into a search</Text></View></View><ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.libraryRow}>{saved.data.map(item => <View key={item.id} style={styles.savedChip}><Pressable onPress={() => { setSearch(item.query || ''); setRole(item.filters?.role || ''); }}><Text style={styles.savedText}>{item.name}</Text></Pressable><Pressable accessibilityLabel={`Delete ${item.name}`} onPress={() => removeSearch.mutate(item.id)}><Ionicons name="close" size={15} color="#728299" /></Pressable></View>)}</ScrollView></> : null}{recentSearches.length ? <><View style={styles.libraryHeading}><View><Text style={styles.libraryTitle}>Recent searches</Text><Text style={styles.librarySubtitle}>Continue where you left off</Text></View><Pressable onPress={() => api.delete('/search/history').then(() => client.invalidateQueries({ queryKey: ['search-history'] }))} style={styles.clearButton}><Text style={styles.clearText}>Clear</Text></Pressable></View><View style={styles.recentWrap}>{recentSearches.map(item => <Pressable key={item.query.toLowerCase()} onPress={() => setSearch(item.query)} style={styles.recentChip}><Ionicons name="time-outline" size={13} color="#728299" /><Text style={styles.savedText}>{item.query}</Text></Pressable>)}</View></> : null}</View> : null}<View style={styles.resultRow}>{profiles.data ? <View><Text style={styles.resultCount}>{profiles.data.pages[0]?.meta.total ?? 0} {profiles.data.pages[0]?.meta.total === 1 ? 'profile' : 'profiles'} found</Text>{query ? <Text style={styles.resultQuery}>for “{query}”</Text> : null}</View> : <View />}{search.trim() ? <Pressable disabled={saveSearch.isPending} onPress={() => saveSearch.mutate()} style={styles.saveButton}><Ionicons name="bookmark-outline" size={16} color={colors.blue} /><Text style={styles.saveButtonText}>{saveSearch.isPending ? 'Saving…' : 'Save search'}</Text></Pressable> : null}</View></View>}
        ListEmptyComponent={profiles.isLoading ? <ActivityIndicator style={styles.loader} color={colors.blue} /> : profiles.isError ? <View style={styles.empty}><View style={styles.emptyIcon}><Ionicons name="cloud-offline-outline" size={28} color={colors.blue} /></View><Text style={styles.emptyTitle}>Discovery is unavailable</Text><Text style={styles.emptyCopy}>Check your connection and try again.</Text><PrimaryButton label="Try again" secondary onPress={() => profiles.refetch()} style={styles.emptyAction} /></View> : <View style={styles.empty}><View style={styles.emptyIcon}><Ionicons name={query ? 'search-outline' : 'people-outline'} size={30} color={colors.blue} /></View><Text style={styles.emptyTitle}>{query ? 'No profiles found' : 'Start discovering talent'}</Text><Text style={styles.emptyCopy}>{query ? 'Try another name, city, sport or profile type.' : 'Search by name, sport, location or club to find your next connection.'}</Text>{query ? <Pressable onPress={() => { setSearch(''); setRole(''); }} style={styles.emptyReset}><Text style={styles.emptyResetText}>Clear search</Text></Pressable> : null}</View>}
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
  safe: { flex: 1, backgroundColor: '#F4F7FB' },
  header: { height: 68, paddingHorizontal: spacing.md, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', borderBottomWidth: 1, borderBottomColor: '#E4E9F1', backgroundColor: '#fff' },
  headerAction: { minHeight: 36, paddingHorizontal: 11, borderRadius: 18, flexDirection: 'row', gap: 6, alignItems: 'center', justifyContent: 'center', backgroundColor: '#FFF0F7' }, headerActionText: { color: '#B82B70', fontSize: 10, fontWeight: '800' },
  intro: { paddingTop: 26, paddingBottom: 18 }, eyebrow: { color: colors.blue, fontSize: 9, fontWeight: '900', letterSpacing: 1.25 }, title: { color: '#142034', fontSize: 28, fontWeight: '900', letterSpacing: -.8, marginTop: 6 }, subtitle: { color: '#6C7B90', fontSize: 13, lineHeight: 19, marginTop: 5 },
  searchWrap: { height: 56, paddingHorizontal: 16, flexDirection: 'row', alignItems: 'center', gap: 11, borderRadius: 18, borderWidth: 1, borderColor: '#DCE3ED', backgroundColor: '#fff', shadowColor: '#20385A', shadowOpacity: .06, shadowRadius: 12, shadowOffset: { width: 0, height: 5 }, elevation: 2 },
  search: { flex: 1, height: '100%', color: '#142034', fontSize: 15 },
  list: { flexGrow: 1, paddingHorizontal: spacing.md },
  filterHeading: { flexDirection: 'row', justifyContent: 'space-between', marginTop: 20 }, filters: { gap: 8, paddingTop: 10, paddingBottom: 18 },
  filter: { minHeight: 38, flexDirection: 'row', alignItems: 'center', gap: 6, paddingHorizontal: 15, borderRadius: radius.pill, borderWidth: 1, borderColor: '#DCE3ED', backgroundColor: '#fff' },
  filterActive: { borderColor: colors.blue, backgroundColor: colors.blue },
  filterText: { color: '#58697F', fontSize: 11, fontWeight: '800' },
  filterTextActive: { color: '#fff' },
  resetText: { color: colors.blue, fontSize: 11, fontWeight: '800' }, library: { padding: 16, borderRadius: 20, borderWidth: 1, borderColor: '#E0E6EF', backgroundColor: '#fff', marginBottom: 18 }, libraryTitle: { color: '#172338', fontSize: 13, fontWeight: '900' }, librarySubtitle: { color: '#8390A2', fontSize: 10, marginTop: 2 }, libraryHeading: { marginTop: 4, marginBottom: 11, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }, libraryRow: { gap: 8, paddingBottom: 10 }, savedChip: { minHeight: 34, paddingHorizontal: 12, flexDirection: 'row', alignItems: 'center', gap: 8, borderRadius: radius.pill, backgroundColor: '#EAF1FF' }, recentWrap: { flexDirection: 'row', flexWrap: 'wrap', gap: 7, marginBottom: 3 }, recentChip: { minHeight: 32, paddingHorizontal: 11, flexDirection: 'row', alignItems: 'center', gap: 5, borderRadius: radius.pill, backgroundColor: '#F2F5F9' }, savedText: { color: '#43536A', fontSize: 10, fontWeight: '700' }, clearButton: { paddingHorizontal: 11, paddingVertical: 7, borderRadius: radius.pill, backgroundColor: '#EEF4FF' }, clearText: { color: colors.blue, fontSize: 10, fontWeight: '800' },
  resultRow: { minHeight: 48, flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', borderTopWidth: 1, borderTopColor: '#E4E9F1', marginBottom: 10 }, resultCount: { color: '#182338', fontSize: 12, fontWeight: '900' }, resultQuery: { color: '#7A8798', fontSize: 10, marginTop: 2 }, saveButton: { minHeight: 34, paddingHorizontal: 11, borderRadius: radius.pill, flexDirection: 'row', alignItems: 'center', gap: 5, backgroundColor: '#EAF1FF' }, saveButtonText: { color: colors.blue, fontSize: 10, fontWeight: '900' },
  loader: { marginTop: 120 },
  footerLoader: { marginVertical: 22 },
  empty: { minHeight: 300, alignItems: 'center', justifyContent: 'center', padding: 28 }, emptyIcon: { width: 66, height: 66, borderRadius: 33, alignItems: 'center', justifyContent: 'center', backgroundColor: '#EAF1FF' }, emptyTitle: { color: '#172338', fontSize: 18, fontWeight: '900', marginTop: 15 }, emptyCopy: { maxWidth: 280, color: '#78869A', fontSize: 12, lineHeight: 18, textAlign: 'center', marginTop: 6 }, emptyReset: { marginTop: 16, paddingHorizontal: 18, paddingVertical: 10, borderRadius: radius.pill, backgroundColor: colors.blue }, emptyResetText: { color: '#fff', fontSize: 11, fontWeight: '900' }, emptyAction: { width: 180, marginTop: 18 },
  card: { minHeight: 110, flexDirection: 'row', alignItems: 'center', gap: 13, padding: 14, borderRadius: radius.lg, borderWidth: 1, borderColor: '#E0E6EF', backgroundColor: '#fff' },
  cardPressed: { opacity: 0.78, transform: [{ scale: 0.995 }] },
  avatar: { width: 68, height: 68, borderRadius: 34, backgroundColor: '#EAF1FF' },
  avatarFallback: { width: 68, height: 68, borderRadius: 34, alignItems: 'center', justifyContent: 'center', backgroundColor: colors.blue },
  initials: { color: '#fff', fontSize: 19, fontWeight: '900' },
  cardContent: { flex: 1, minWidth: 0 },
  nameRow: { flexDirection: 'row', alignItems: 'center', gap: 7 },
  name: { flexShrink: 1, color: '#172338', fontSize: 16, fontWeight: '900' },
  available: { width: 8, height: 8, borderRadius: 4, backgroundColor: colors.green },
  role: { color: '#79A3FF', fontSize: 12, fontWeight: '800', textTransform: 'capitalize', marginTop: 3 },
  detail: { color: '#748297', fontSize: 12, marginTop: 3 },
  bio: { color: '#52647B', fontSize: 12, lineHeight: 17, marginTop: 7 },
});
