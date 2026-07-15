import { useDeferredValue, useState } from 'react';
import { ActivityIndicator, FlatList, Image, Pressable, RefreshControl, StyleSheet, Text, TextInput, View } from 'react-native';
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
  const [search, setSearch] = useState('');
  const [role, setRole] = useState('');
  const query = useDeferredValue(search.trim());

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
      <View style={styles.header}><BrandMark /><Text style={styles.headerTitle}>Discover</Text></View>
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
        ListHeaderComponent={<View><FlatList horizontal data={roles} keyExtractor={item => item.value || 'all'} showsHorizontalScrollIndicator={false} contentContainerStyle={styles.filters} renderItem={({ item }) => <Pressable accessibilityRole="button" accessibilityState={{ selected: role === item.value }} onPress={() => setRole(item.value)} style={[styles.filter, role === item.value && styles.filterActive]}><Text style={[styles.filterText, role === item.value && styles.filterTextActive]}>{item.label}</Text></Pressable>} />{profiles.data ? <Text style={styles.resultCount}>{profiles.data.pages[0]?.meta.total ?? 0} profiles</Text> : null}</View>}
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
    <Pressable accessibilityRole="button" onPress={() => router.push(`/profile/${profile.slug}` as never)} style={({ pressed }) => [styles.card, pressed && styles.cardPressed]}>
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
  searchWrap: { height: 50, margin: spacing.md, marginBottom: 4, paddingHorizontal: 14, flexDirection: 'row', alignItems: 'center', gap: 10, borderRadius: radius.md, borderWidth: 1, borderColor: colors.line, backgroundColor: colors.surface },
  search: { flex: 1, height: '100%', color: colors.white, fontSize: 15 },
  list: { flexGrow: 1, paddingHorizontal: spacing.md },
  filters: { gap: 8, paddingTop: 10, paddingBottom: 12 },
  filter: { paddingHorizontal: 15, paddingVertical: 9, borderRadius: radius.pill, borderWidth: 1, borderColor: colors.line, backgroundColor: colors.surface },
  filterActive: { borderColor: colors.blue, backgroundColor: colors.blue },
  filterText: { color: colors.muted, fontSize: 12, fontWeight: '800' },
  filterTextActive: { color: '#fff' },
  resultCount: { color: colors.muted, fontSize: 12, fontWeight: '700', marginBottom: 10 },
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
