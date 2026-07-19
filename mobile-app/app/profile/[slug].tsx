import { useEffect, useState } from 'react';
import { ActivityIndicator, Alert, Image, KeyboardAvoidingView, Modal, Platform, Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { router, useLocalSearchParams } from 'expo-router';
import { useInfiniteQuery, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '../../src/api/client';
import { PrimaryButton } from '../../src/components/PrimaryButton';
import { ScreenMessage } from '../../src/components/ScreenMessage';
import { colors, radius, spacing } from '../../src/theme';
import type { ApiResponse, PaginatedResponse, Profile, Video } from '../../src/types/api';
import { absoluteMediaUrl } from '../../src/utils/url';
import { getAuthToken, useAuthStore } from '../../src/stores/auth';
import { shareLink } from '../../src/utils/share';

export default function PublicProfileScreen() {
  const { slug } = useLocalSearchParams<{ slug: string }>();
  const me = useAuthStore(state => state.user);
  const client = useQueryClient();
  const [requestOpen, setRequestOpen] = useState(false);
  const [requestMessage, setRequestMessage] = useState('');
  const [token, setToken] = useState<string>();
  const profile = useQuery({
    queryKey: ['profile', slug],
    enabled: Boolean(slug),
    queryFn: async () => (await api.get<ApiResponse<Profile>>(`/profiles/${encodeURIComponent(slug!)}`)).data.data,
  });
  const posts = useInfiniteQuery({ queryKey: ['profile-posts', slug], enabled: Boolean(slug), initialPageParam: 1, queryFn: async ({ pageParam }) => (await api.get<PaginatedResponse<Video>>(`/profiles/${encodeURIComponent(slug!)}/videos`, { params: { page: pageParam } })).data, getNextPageParam: page => page.meta.current_page < page.meta.last_page ? page.meta.current_page + 1 : undefined });
  useEffect(() => { getAuthToken().then(value => setToken(value ?? undefined)); }, []);
  const block = useMutation({ mutationFn: () => profile.data?.viewer?.blocked ? api.delete('/profiles/' + profile.data.id + '/block') : api.post('/profiles/' + profile.data!.id + '/block'), onSuccess: async () => { await client.invalidateQueries({ queryKey: ['profile', slug] }); client.invalidateQueries({ queryKey: ['blocked-users'] }); }, onError: (error: any) => Alert.alert('Safety action failed', error?.response?.data?.message || 'Please try again.') });
  const save = useMutation({ mutationFn: () => profile.data?.viewer?.saved ? api.delete('/saved-profiles/' + profile.data.id) : api.post('/saved-profiles/' + profile.data!.id), onSuccess: async () => { await client.invalidateQueries({ queryKey: ['profile', slug] }); client.invalidateQueries({ queryKey: ['saved-profiles'] }); }, onError: (error: any) => Alert.alert('Profile not saved', error?.response?.data?.message || 'Please try again.') });
  const follow = useMutation({ mutationFn: () => profile.data?.viewer?.following ? api.delete('/profiles/' + profile.data.id + '/follow') : api.post('/profiles/' + profile.data!.id + '/follow'), onSuccess: async () => { await client.invalidateQueries({ queryKey: ['profile', slug] }); client.invalidateQueries({ queryKey: ['feed'] }); }, onError: (error: any) => Alert.alert('Follow action failed', error?.response?.data?.message || 'Please try again.') });
  const messageContext = useMutation({
    mutationFn: async () => (await api.get<ApiResponse<{ mode: 'conversation' | 'request'; conversation?: { id: string } | null }>>('/profiles/' + profile.data!.id + '/messaging-context')).data.data,
    onSuccess: data => data.mode === 'conversation' && data.conversation ? router.push(`/conversation/${data.conversation.id}` as never) : setRequestOpen(true),
    onError: (error: any) => Alert.alert('Messaging unavailable', error?.response?.data?.message || 'Please try again.'),
  });
  const sendRequest = useMutation({
    mutationFn: () => api.post('/message-requests', { recipient_id: profile.data!.id, message: requestMessage.trim() }),
    onSuccess: () => { setRequestOpen(false); setRequestMessage(''); client.invalidateQueries({ queryKey: ['message-requests'] }); Alert.alert('Request sent', `${profile.data!.name} can accept your request to start chatting.`); },
    onError: (error: any) => Alert.alert('Request not sent', error?.response?.data?.message || Object.values(error?.response?.data?.errors || {}).flat()[0] as string || 'Please try again.'),
  });

  if (profile.isLoading) return <SafeAreaView style={styles.safe}><TopBar /><ActivityIndicator style={{ flex: 1 }} color={colors.blue} /></SafeAreaView>;
  if (profile.isError || !profile.data) return <SafeAreaView style={styles.safe}><TopBar /><ScreenMessage icon="alert-circle-outline" title="Profile unavailable" message="This profile may be private or no longer available." action={<PrimaryButton label="Go back" secondary onPress={() => router.back()} />} /></SafeAreaView>;

  const item = profile.data;
  const toggleBlock = () => item.viewer?.blocked ? block.mutate() : Alert.alert(`Block ${item.name}?`, 'You will unfollow each other. They will not be able to send you new message requests.', [{ text: 'Cancel', style: 'cancel' }, { text: 'Block', style: 'destructive', onPress: () => block.mutate() }]);
  const cover = absoluteMediaUrl(item.images.cover);
  const photo = absoluteMediaUrl(item.images.profile);
  const initials = item.name.split(/\s+/).slice(0, 2).map(part => part[0]).join('').toUpperCase();
  const location = [item.location.city, item.location.province, item.location.country].filter(Boolean).join(', ');
  const facts = [
    item.athlete?.sport?.name && { icon: 'football-outline', label: 'Sport', value: item.athlete.sport.name },
    item.athlete?.position?.name && { icon: 'shirt-outline', label: 'Position', value: item.athlete.position.name },
    item.athlete?.club_name && { icon: 'people-outline', label: 'Club', value: item.athlete.club_name },
    item.athlete?.playing_level && { icon: 'trophy-outline', label: 'Level', value: item.athlete.playing_level },
  ].filter(Boolean) as { icon: keyof typeof Ionicons.glyphMap; label: string; value: string }[];

  return (
    <SafeAreaView edges={['top']} style={styles.safe}>
      <TopBar share={() => shareLink(item.name, `View ${item.name} on SportsUniverse`, `/@${item.slug}`)} save={me?.id !== item.id ? () => save.mutate() : undefined} saved={item.viewer?.saved} report={me?.id !== item.id ? () => router.push({ pathname: '/report', params: { type: 'user', id: String(item.id), label: item.name } }) : undefined} block={me?.id !== item.id ? toggleBlock : undefined} blocked={item.viewer?.blocked} />
      <ScrollView contentContainerStyle={styles.content}>
        <View style={styles.hero}>
          {cover ? <Image source={{ uri: cover }} style={styles.cover} /> : <View style={styles.coverFallback} />}
          {photo ? <Image source={{ uri: photo }} style={styles.photo} /> : <View style={[styles.photo, styles.photoFallback]}><Text style={styles.initials}>{initials}</Text></View>}
        </View>
        <View style={styles.identity}>
          <View style={styles.nameRow}><Text style={styles.name}>{item.name}</Text>{item.is_available ? <View style={styles.availability}><View style={styles.dot} /><Text style={styles.availableText}>Available</Text></View> : null}</View>
          <Text style={styles.role}>{item.roles.join(' · ')}</Text>
          {location ? <View style={styles.location}><Ionicons name="location-outline" size={15} color={colors.muted} /><Text style={styles.locationText}>{location}</Text></View> : null}
          <View style={styles.publicStats}><Pressable onPress={() => router.push({ pathname: '/profile/connections', params: { userId: String(item.id), tab: 'following', name: item.name } })}><Text style={styles.publicStat}><Text style={styles.publicStatValue}>{item.connections?.following ?? 0}</Text> Following</Text></Pressable><Pressable onPress={() => router.push({ pathname: '/profile/connections', params: { userId: String(item.id), tab: 'followers', name: item.name } })}><Text style={styles.publicStat}><Text style={styles.publicStatValue}>{item.connections?.followers ?? 0}</Text> Followers</Text></Pressable></View>
          {item.bio ? <Text style={styles.bio}>{item.bio}</Text> : null}
        </View>
        {me?.id !== item.id && !item.viewer?.blocked ? <View style={styles.connectionActions}><PrimaryButton label={item.viewer?.following ? 'Following' : 'Follow'} secondary={item.viewer?.following} loading={follow.isPending} onPress={() => follow.mutate()} style={styles.connectionButton} /><PrimaryButton label="Message" secondary loading={messageContext.isPending} onPress={() => messageContext.mutate()} style={styles.connectionButton} /></View> : null}
        {facts.length ? <View style={styles.facts}>{facts.map(fact => <View key={fact.label} style={styles.fact}><View style={styles.factIcon}><Ionicons name={fact.icon} size={19} color="#79A3FF" /></View><View><Text style={styles.factLabel}>{fact.label}</Text><Text style={styles.factValue}>{fact.value}</Text></View></View>)}</View> : null}
        {item.career ? <CareerPortfolio career={item.career} /> : null}
        <ProfileGallery posts={posts.data?.pages.flatMap(page => page.data) ?? []} token={token} loading={posts.isLoading} loadingMore={posts.isFetchingNextPage} hasMore={posts.hasNextPage} loadMore={() => posts.fetchNextPage()} />
      </ScrollView>
      <Modal visible={requestOpen} transparent animationType="fade" onRequestClose={() => setRequestOpen(false)}><KeyboardAvoidingView behavior={Platform.OS === 'ios' ? 'padding' : undefined} style={styles.modalOverlay}><Pressable style={StyleSheet.absoluteFill} onPress={() => setRequestOpen(false)} /><View style={styles.requestSheet}><View style={styles.requestHeader}><View><Text style={styles.requestTitle}>Message {item.name}</Text><Text style={styles.requestHelp}>Introduce yourself before starting a conversation.</Text></View><Pressable accessibilityLabel="Close" onPress={() => setRequestOpen(false)}><Ionicons name="close" size={23} color={colors.muted} /></Pressable></View><TextInput value={requestMessage} onChangeText={setRequestMessage} multiline maxLength={2000} autoFocus placeholder="Write a short introduction…" placeholderTextColor={colors.muted} style={styles.requestInput} /><Text style={styles.counter}>{requestMessage.length}/2000</Text><PrimaryButton label="Send message request" loading={sendRequest.isPending} disabled={!requestMessage.trim()} onPress={() => sendRequest.mutate()} /></View></KeyboardAvoidingView></Modal>
    </SafeAreaView>
  );
}

function CareerPortfolio({ career }: { career: NonNullable<Profile['career']> }) {
  if (!career.history.length && !career.achievements.length && !career.statistics.length) return null;
  return <View style={styles.portfolio}><Text style={styles.portfolioTitle}>Career portfolio</Text>{career.statistics.length ? <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.stats}>{career.statistics.map(stat => <View key={stat.id} style={styles.stat}><Text style={styles.statValue}>{stat.value}{stat.unit ? ` ${stat.unit}` : ''}</Text><Text style={styles.statName}>{stat.name}</Text><Text style={styles.statSeason}>{stat.season}</Text></View>)}</ScrollView> : null}{career.history.length ? <View style={styles.portfolioSection}><Text style={styles.portfolioLabel}>Experience</Text>{career.history.map(entry => <View key={entry.id} style={styles.careerRow}><View style={styles.timelineDot} /><View style={styles.careerCopy}><Text style={styles.careerTitle}>{entry.team_name}</Text><Text style={styles.careerMeta}>{[entry.role, entry.level, entry.is_current ? 'Current' : null].filter(Boolean).join(' · ')}</Text>{entry.description ? <Text style={styles.careerDescription}>{entry.description}</Text> : null}</View></View>)}</View> : null}{career.achievements.length ? <View style={styles.portfolioSection}><Text style={styles.portfolioLabel}>Achievements</Text>{career.achievements.map(award => <View key={award.id} style={styles.award}><Ionicons name="trophy" size={20} color={colors.orange} /><View style={styles.careerCopy}><Text style={styles.careerTitle}>{award.title}</Text><Text style={styles.careerMeta}>{[award.issuer, award.achieved_on ? new Date(award.achieved_on).getFullYear() : null].filter(Boolean).join(' · ')}</Text></View></View>)}</View> : null}</View>;
}

function ProfileGallery({ posts, token, loading, loadingMore, hasMore, loadMore }: { posts: Video[]; token?: string; loading: boolean; loadingMore: boolean; hasMore: boolean; loadMore: () => void }) {
  return <View style={styles.gallery}><View style={styles.galleryHeading}><View><Text style={styles.portfolioTitle}>Highlights</Text><Text style={styles.galleryCount}>{posts.length} published {posts.length === 1 ? 'post' : 'posts'}</Text></View><Ionicons name="grid-outline" size={21} color="#79A3FF" /></View>{loading ? <ActivityIndicator color={colors.blue} style={{ marginVertical: 30 }} /> : posts.length ? <View style={styles.galleryGrid}>{posts.map(post => { const cover = post.images?.find(image => image.is_cover) ?? post.images?.[0]; const image = absoluteMediaUrl(cover?.download_url); return <Pressable accessibilityLabel={`Open post ${post.caption || ''}`} key={post.id} onPress={() => router.push(`/post/${post.id}` as never)} style={styles.galleryItem}>{image ? <Image source={{ uri: image, headers: token ? { Authorization: `Bearer ${token}` } : undefined }} style={styles.galleryImage} /> : <View style={styles.galleryVideo}><Ionicons name="play" size={28} color="#fff" /></View>}<View style={styles.galleryShade} /><View style={styles.galleryMeta}><Ionicons name={post.type === 'images' ? 'images-outline' : 'play-outline'} size={14} color="#fff" /><Text style={styles.galleryViews}>{compact(post.counts.views)}</Text></View></Pressable>; })}</View> : <View style={styles.galleryEmpty}><Ionicons name="images-outline" size={31} color={colors.muted} /><Text style={styles.galleryEmptyTitle}>No public highlights yet</Text><Text style={styles.galleryEmptyCopy}>Published videos and photos will appear here.</Text></View>}{hasMore ? <PrimaryButton label={loadingMore ? 'Loading…' : 'Load more highlights'} secondary loading={loadingMore} onPress={loadMore} style={{ marginTop: 13 }} /> : null}</View>;
}

function compact(value: number) { return Intl.NumberFormat('en', { notation: 'compact', maximumFractionDigits: 1 }).format(value || 0); }

function TopBar({ share, save, saved, report, block, blocked }: { share?: () => void; save?: () => void; saved?: boolean; report?: () => void; block?: () => void; blocked?: boolean }) {
  return <View style={styles.topBar}><Pressable accessibilityLabel="Go back" hitSlop={12} onPress={() => router.back()}><Ionicons name="arrow-back" size={24} color={colors.white} /></Pressable><Text style={styles.topTitle}>Profile</Text><View style={styles.safetyActions}>{share ? <Pressable accessibilityLabel="Share profile" hitSlop={10} onPress={share}><Ionicons name="share-social-outline" size={21} color={colors.muted} /></Pressable> : null}{save ? <Pressable accessibilityLabel={saved ? 'Remove saved profile' : 'Save profile'} hitSlop={10} onPress={save}><Ionicons name={saved ? 'bookmark' : 'bookmark-outline'} size={21} color={saved ? colors.pink : colors.muted} /></Pressable> : null}{report ? <Pressable accessibilityLabel="Report profile" hitSlop={10} onPress={report}><Ionicons name="flag-outline" size={21} color={colors.muted} /></Pressable> : null}{block ? <Pressable accessibilityLabel={blocked ? 'Unblock user' : 'Block user'} hitSlop={10} onPress={block}><Ionicons name={blocked ? 'person-add-outline' : 'person-remove-outline'} size={22} color={blocked ? colors.green : colors.danger} /></Pressable> : null}</View></View>;
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: colors.navy },
  topBar: { height: 56, paddingHorizontal: spacing.md, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', borderBottomWidth: 1, borderBottomColor: colors.line },
  topTitle: { color: colors.white, fontSize: 15, fontWeight: '900' },
  safetyActions: { flexDirection: 'row', alignItems: 'center', gap: 16 },
  content: { paddingBottom: 40 },
  hero: { height: 210, backgroundColor: colors.surface },
  cover: { width: '100%', height: 170 },
  coverFallback: { width: '100%', height: 170, backgroundColor: colors.navyLight },
  photo: { position: 'absolute', left: spacing.lg, bottom: 0, width: 92, height: 92, borderRadius: 46, borderWidth: 4, borderColor: colors.navy, backgroundColor: colors.surfaceRaised },
  photoFallback: { alignItems: 'center', justifyContent: 'center', backgroundColor: colors.blue },
  initials: { color: '#fff', fontSize: 26, fontWeight: '900' },
  identity: { paddingHorizontal: spacing.lg, paddingTop: 14 },
  nameRow: { flexDirection: 'row', flexWrap: 'wrap', alignItems: 'center', gap: 10 },
  name: { color: colors.white, fontSize: 27, fontWeight: '900', letterSpacing: -0.7 },
  availability: { flexDirection: 'row', alignItems: 'center', gap: 5, paddingHorizontal: 9, paddingVertical: 5, borderRadius: radius.pill, backgroundColor: 'rgba(24,178,107,.14)' },
  dot: { width: 7, height: 7, borderRadius: 4, backgroundColor: colors.green },
  availableText: { color: '#69DDA4', fontSize: 10, fontWeight: '900' },
  role: { color: '#79A3FF', fontSize: 13, fontWeight: '800', textTransform: 'capitalize', marginTop: 4 },
  location: { flexDirection: 'row', alignItems: 'center', gap: 4, marginTop: 9 },
  locationText: { color: colors.muted, fontSize: 13 },
  bio: { color: '#D8E2EE', fontSize: 14, lineHeight: 21, marginTop: 18 },
  publicStats: { flexDirection: 'row', gap: 22, marginTop: 15 },
  publicStat: { color: colors.muted, fontSize: 12 },
  publicStatValue: { color: '#fff', fontWeight: '900' },
  connectionActions: { flexDirection: 'row', gap: 10, paddingHorizontal: spacing.lg, marginTop: 20 },
  connectionButton: { flex: 1 },
  facts: { margin: spacing.lg, marginTop: 26, padding: 4, borderWidth: 1, borderColor: colors.line, borderRadius: radius.lg, backgroundColor: colors.surface },
  fact: { minHeight: 66, flexDirection: 'row', alignItems: 'center', gap: 12, paddingHorizontal: 14, borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: colors.line },
  factIcon: { width: 38, height: 38, borderRadius: 19, alignItems: 'center', justifyContent: 'center', backgroundColor: 'rgba(27,99,243,.12)' },
  factLabel: { color: colors.muted, fontSize: 10, fontWeight: '800', textTransform: 'uppercase' },
  factValue: { color: colors.white, fontSize: 14, fontWeight: '800', textTransform: 'capitalize', marginTop: 2 },
  portfolio: { marginHorizontal: spacing.lg, marginBottom: 30 },
  portfolioTitle: { color: colors.white, fontSize: 21, fontWeight: '900', marginBottom: 14 },
  stats: { gap: 9, paddingBottom: 8 },
  stat: { minWidth: 118, padding: 14, borderRadius: radius.md, backgroundColor: colors.surface },
  statValue: { color: '#79A3FF', fontSize: 20, fontWeight: '900' },
  statName: { color: colors.white, fontSize: 12, fontWeight: '800', marginTop: 4 },
  statSeason: { color: colors.muted, fontSize: 10, marginTop: 3 },
  portfolioSection: { marginTop: 18, padding: spacing.md, borderRadius: radius.lg, borderWidth: 1, borderColor: colors.line, backgroundColor: colors.surface },
  portfolioLabel: { color: colors.muted, fontSize: 10, fontWeight: '900', textTransform: 'uppercase', letterSpacing: .7, marginBottom: 12 },
  careerRow: { flexDirection: 'row', gap: 11, marginBottom: 15 },
  timelineDot: { width: 10, height: 10, marginTop: 5, borderRadius: 5, backgroundColor: colors.blue },
  careerCopy: { flex: 1 },
  careerTitle: { color: colors.white, fontSize: 14, fontWeight: '900' },
  careerMeta: { color: '#79A3FF', fontSize: 11, marginTop: 3 },
  careerDescription: { color: colors.muted, fontSize: 11, lineHeight: 16, marginTop: 6 },
  award: { flexDirection: 'row', gap: 11, marginBottom: 14 },
  gallery: { marginHorizontal: spacing.lg, marginBottom: 35 }, galleryHeading: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginBottom: 13 }, galleryCount: { color: colors.muted, fontSize: 10, marginTop: -8 }, galleryGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: 4 }, galleryItem: { width: '32.5%', aspectRatio: .78, overflow: 'hidden', borderRadius: 8, backgroundColor: '#03080E' }, galleryImage: { width: '100%', height: '100%' }, galleryVideo: { flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: '#102944' }, galleryShade: { position: 'absolute', top: 0, right: 0, bottom: 0, left: 0, backgroundColor: 'rgba(0,0,0,.08)' }, galleryMeta: { position: 'absolute', left: 7, bottom: 7, flexDirection: 'row', alignItems: 'center', gap: 4 }, galleryViews: { color: '#fff', fontSize: 9, fontWeight: '900' }, galleryEmpty: { alignItems: 'center', padding: 28, borderRadius: radius.lg, borderWidth: 1, borderColor: colors.line, borderStyle: 'dashed' }, galleryEmptyTitle: { color: '#fff', fontSize: 14, fontWeight: '900', marginTop: 9 }, galleryEmptyCopy: { color: colors.muted, fontSize: 10, marginTop: 4 },
  modalOverlay: { flex: 1, justifyContent: 'flex-end', backgroundColor: 'rgba(0,0,0,.66)' },
  requestSheet: { padding: spacing.lg, paddingBottom: 34, borderTopLeftRadius: radius.lg, borderTopRightRadius: radius.lg, backgroundColor: colors.navyLight },
  requestHeader: { flexDirection: 'row', alignItems: 'flex-start', justifyContent: 'space-between', gap: 15 },
  requestTitle: { color: '#fff', fontSize: 20, fontWeight: '900' },
  requestHelp: { color: colors.muted, fontSize: 12, marginTop: 5 },
  requestInput: { minHeight: 130, marginTop: 18, padding: 14, color: '#fff', textAlignVertical: 'top', borderRadius: radius.md, borderWidth: 1, borderColor: colors.line, backgroundColor: colors.surface },
  counter: { color: colors.muted, fontSize: 10, textAlign: 'right', marginTop: 5, marginBottom: 14 },
});
