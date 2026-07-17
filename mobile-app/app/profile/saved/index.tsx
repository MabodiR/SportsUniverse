import { ActivityIndicator, Alert, FlatList, Image, Pressable, StyleSheet, Text, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { router } from 'expo-router';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '../../../src/api/client';
import { ScreenMessage } from '../../../src/components/ScreenMessage';
import { colors, radius, spacing } from '../../../src/theme';
import type { ApiResponse, Profile } from '../../../src/types/api';
import { absoluteMediaUrl } from '../../../src/utils/url';

export default function SavedProfilesScreen() {
  const client = useQueryClient();
  const profiles = useQuery({ queryKey: ['saved-profiles'], queryFn: async () => (await api.get<ApiResponse<Profile[]>>('/saved-profiles')).data.data });
  const remove = useMutation({
    mutationFn: (id: number) => api.delete('/saved-profiles/' + id),
    onSuccess: () => { client.invalidateQueries({ queryKey: ['saved-profiles'] }); client.invalidateQueries({ queryKey: ['profile'] }); },
    onError: (error: any) => Alert.alert('Profile not removed', error?.response?.data?.message || 'Please try again.'),
  });
  return <SafeAreaView edges={['top']} style={styles.safe}><View style={styles.header}><Pressable accessibilityLabel="Go back" onPress={() => router.back()}><Ionicons name="chevron-back" size={26} color="#fff" /></Pressable><Text style={styles.headerTitle}>Saved talent</Text><Pressable accessibilityLabel="Discover talent" onPress={() => router.push('/(tabs)/explore')}><Ionicons name="search-outline" size={24} color="#fff" /></Pressable></View><FlatList data={profiles.data ?? []} keyExtractor={item => String(item.id)} contentContainerStyle={profiles.data?.length ? styles.list : styles.empty} refreshing={profiles.isRefetching} onRefresh={() => profiles.refetch()} ItemSeparatorComponent={() => <View style={{ height: 11 }} />} renderItem={({ item }) => <SavedRow item={item} busy={remove.isPending} onRemove={() => remove.mutate(item.id)} />} ListEmptyComponent={profiles.isLoading ? <ActivityIndicator style={{ marginTop: 100 }} color={colors.blue} /> : profiles.isError ? <ScreenMessage icon="cloud-offline-outline" title="Saved talent unavailable" message="Check your connection and try again." /> : <ScreenMessage icon="bookmark-outline" title="No saved talent yet" message="Bookmark athletes and sports professionals to find them quickly here." />} /></SafeAreaView>;
}

function SavedRow({ item, busy, onRemove }: { item: Profile; busy: boolean; onRemove: () => void }) {
  const photo = absoluteMediaUrl(item.images.profile);
  const initials = item.name.split(/\s+/).slice(0, 2).map(part => part[0]).join('').toUpperCase();
  const details = [item.athlete?.sport?.name || item.roles[0], item.athlete?.position?.name, item.location.city].filter(Boolean).join(' · ');
  return <Pressable onPress={() => router.push(`/profile/${item.slug}` as never)} style={styles.card}>{photo ? <Image source={{ uri: photo }} style={styles.avatar} /> : <View style={[styles.avatar, styles.fallback]}><Text style={styles.initials}>{initials}</Text></View>}<View style={styles.copy}><Text numberOfLines={1} style={styles.name}>{item.name}</Text><Text numberOfLines={1} style={styles.detail}>{details || 'SportUniverse member'}</Text>{item.is_available ? <Text style={styles.available}>Available for opportunities</Text> : null}</View><Pressable accessibilityLabel={`Remove ${item.name} from saved`} disabled={busy} hitSlop={10} onPress={onRemove} style={styles.remove}><Ionicons name="bookmark" size={22} color={colors.pink} /></Pressable></Pressable>;
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: colors.navy }, header: { height: 56, paddingHorizontal: spacing.md, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', borderBottomWidth: 1, borderBottomColor: colors.line }, headerTitle: { color: '#fff', fontSize: 18, fontWeight: '900' }, list: { padding: spacing.md, paddingBottom: 40 }, empty: { flexGrow: 1 },
  card: { minHeight: 92, padding: 12, flexDirection: 'row', alignItems: 'center', gap: 12, borderRadius: radius.lg, borderWidth: 1, borderColor: colors.line, backgroundColor: colors.surface }, avatar: { width: 62, height: 62, borderRadius: 31, backgroundColor: colors.surfaceRaised }, fallback: { alignItems: 'center', justifyContent: 'center', backgroundColor: colors.blue }, initials: { color: '#fff', fontSize: 17, fontWeight: '900' }, copy: { flex: 1, minWidth: 0 }, name: { color: '#fff', fontSize: 15, fontWeight: '900' }, detail: { color: colors.muted, fontSize: 11, marginTop: 4 }, available: { color: colors.green, fontSize: 10, fontWeight: '800', marginTop: 5 }, remove: { width: 40, height: 40, alignItems: 'center', justifyContent: 'center' },
});
