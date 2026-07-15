import { ActivityIndicator, Alert, Image, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { router, useLocalSearchParams } from 'expo-router';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api } from '../../src/api/client';
import { PrimaryButton } from '../../src/components/PrimaryButton';
import { ScreenMessage } from '../../src/components/ScreenMessage';
import { colors, radius, spacing } from '../../src/theme';
import type { ApiResponse, Profile } from '../../src/types/api';
import { absoluteMediaUrl } from '../../src/utils/url';
import { useAuthStore } from '../../src/stores/auth';

export default function PublicProfileScreen() {
  const { slug } = useLocalSearchParams<{ slug: string }>();
  const me = useAuthStore(state => state.user);
  const client = useQueryClient();
  const profile = useQuery({
    queryKey: ['profile', slug],
    enabled: Boolean(slug),
    queryFn: async () => (await api.get<ApiResponse<Profile>>(`/profiles/${encodeURIComponent(slug!)}`)).data.data,
  });
  const block = useMutation({ mutationFn: () => profile.data?.viewer?.blocked ? api.delete('/profiles/' + profile.data.id + '/block') : api.post('/profiles/' + profile.data!.id + '/block'), onSuccess: async () => { await client.invalidateQueries({ queryKey: ['profile', slug] }); client.invalidateQueries({ queryKey: ['blocked-users'] }); }, onError: (error: any) => Alert.alert('Safety action failed', error?.response?.data?.message || 'Please try again.') });

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
      <TopBar report={me?.id !== item.id ? () => router.push({ pathname: '/report', params: { type: 'user', id: String(item.id), label: item.name } }) : undefined} block={me?.id !== item.id ? toggleBlock : undefined} blocked={item.viewer?.blocked} />
      <ScrollView contentContainerStyle={styles.content}>
        <View style={styles.hero}>
          {cover ? <Image source={{ uri: cover }} style={styles.cover} /> : <View style={styles.coverFallback} />}
          {photo ? <Image source={{ uri: photo }} style={styles.photo} /> : <View style={[styles.photo, styles.photoFallback]}><Text style={styles.initials}>{initials}</Text></View>}
        </View>
        <View style={styles.identity}>
          <View style={styles.nameRow}><Text style={styles.name}>{item.name}</Text>{item.is_available ? <View style={styles.availability}><View style={styles.dot} /><Text style={styles.availableText}>Available</Text></View> : null}</View>
          <Text style={styles.role}>{item.roles.join(' · ')}</Text>
          {location ? <View style={styles.location}><Ionicons name="location-outline" size={15} color={colors.muted} /><Text style={styles.locationText}>{location}</Text></View> : null}
          {item.bio ? <Text style={styles.bio}>{item.bio}</Text> : null}
        </View>
        {facts.length ? <View style={styles.facts}>{facts.map(fact => <View key={fact.label} style={styles.fact}><View style={styles.factIcon}><Ionicons name={fact.icon} size={19} color="#79A3FF" /></View><View><Text style={styles.factLabel}>{fact.label}</Text><Text style={styles.factValue}>{fact.value}</Text></View></View>)}</View> : null}
        {item.career ? <CareerPortfolio career={item.career} /> : null}
      </ScrollView>
    </SafeAreaView>
  );
}

function CareerPortfolio({ career }: { career: NonNullable<Profile['career']> }) {
  if (!career.history.length && !career.achievements.length && !career.statistics.length) return null;
  return <View style={styles.portfolio}><Text style={styles.portfolioTitle}>Career portfolio</Text>{career.statistics.length ? <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.stats}>{career.statistics.map(stat => <View key={stat.id} style={styles.stat}><Text style={styles.statValue}>{stat.value}{stat.unit ? ` ${stat.unit}` : ''}</Text><Text style={styles.statName}>{stat.name}</Text><Text style={styles.statSeason}>{stat.season}</Text></View>)}</ScrollView> : null}{career.history.length ? <View style={styles.portfolioSection}><Text style={styles.portfolioLabel}>Experience</Text>{career.history.map(entry => <View key={entry.id} style={styles.careerRow}><View style={styles.timelineDot} /><View style={styles.careerCopy}><Text style={styles.careerTitle}>{entry.team_name}</Text><Text style={styles.careerMeta}>{[entry.role, entry.level, entry.is_current ? 'Current' : null].filter(Boolean).join(' · ')}</Text>{entry.description ? <Text style={styles.careerDescription}>{entry.description}</Text> : null}</View></View>)}</View> : null}{career.achievements.length ? <View style={styles.portfolioSection}><Text style={styles.portfolioLabel}>Achievements</Text>{career.achievements.map(award => <View key={award.id} style={styles.award}><Ionicons name="trophy" size={20} color={colors.orange} /><View style={styles.careerCopy}><Text style={styles.careerTitle}>{award.title}</Text><Text style={styles.careerMeta}>{[award.issuer, award.achieved_on ? new Date(award.achieved_on).getFullYear() : null].filter(Boolean).join(' · ')}</Text></View></View>)}</View> : null}</View>;
}

function TopBar({ report, block, blocked }: { report?: () => void; block?: () => void; blocked?: boolean }) {
  return <View style={styles.topBar}><Pressable accessibilityLabel="Go back" hitSlop={12} onPress={() => router.back()}><Ionicons name="arrow-back" size={24} color={colors.white} /></Pressable><Text style={styles.topTitle}>Profile</Text>{report ? <View style={styles.safetyActions}><Pressable accessibilityLabel="Report profile" hitSlop={10} onPress={report}><Ionicons name="flag-outline" size={21} color={colors.muted} /></Pressable><Pressable accessibilityLabel={blocked ? 'Unblock user' : 'Block user'} hitSlop={10} onPress={block}><Ionicons name={blocked ? 'person-add-outline' : 'person-remove-outline'} size={22} color={blocked ? colors.green : colors.danger} /></Pressable></View> : <View style={{ width: 24 }} />}</View>;
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
});
