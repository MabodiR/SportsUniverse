import { ActivityIndicator, Image, Linking, Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { router, useLocalSearchParams } from 'expo-router';
import { useQuery } from '@tanstack/react-query';
import { api } from '../../src/api/client';
import { PrimaryButton } from '../../src/components/PrimaryButton';
import { ScreenMessage } from '../../src/components/ScreenMessage';
import { colors, radius, spacing } from '../../src/theme';
import type { ApiResponse, PublicClub } from '../../src/types/api';
import { absoluteMediaUrl } from '../../src/utils/url';
import { shareLink } from '../../src/utils/share';

export default function PublicClubScreen() {
  const { slug } = useLocalSearchParams<{ slug: string }>();
  const club = useQuery({ queryKey: ['public-club', slug], enabled: Boolean(slug), queryFn: async () => (await api.get<ApiResponse<PublicClub>>(`/clubs/${encodeURIComponent(slug!)}`)).data.data });

  if (club.isLoading) return <SafeAreaView style={styles.safe}><TopBar /><ActivityIndicator style={{ flex: 1 }} color={colors.blue} /></SafeAreaView>;
  if (club.isError || !club.data) return <SafeAreaView style={styles.safe}><TopBar /><ScreenMessage icon="business-outline" title="Club unavailable" message="This club page may be private or no longer available." action={<PrimaryButton label="Go back" secondary onPress={() => router.back()} />} /></SafeAreaView>;

  const item = club.data;
  const logo = absoluteMediaUrl(item.image);
  const cover = absoluteMediaUrl(item.cover_image);
  const location = [item.location?.city ?? item.city, item.location?.province ?? item.province, item.location?.country].filter(Boolean).join(', ');
  const website = item.website ? (/^https?:\/\//i.test(item.website) ? item.website : `https://${item.website}`) : null;

  return <SafeAreaView edges={['top']} style={styles.safe}>
    <TopBar share={() => shareLink(item.name, `View ${item.name} on SportsUniverse`, `/clubs/${item.slug}`)} />
    <ScrollView refreshControl={<RefreshControl refreshing={club.isRefetching} onRefresh={() => club.refetch()} tintColor={colors.blue} />} contentContainerStyle={styles.content}>
      <View style={styles.hero}>
        {cover ? <Image source={{ uri: cover }} style={styles.cover} /> : <View style={styles.coverFallback}><Ionicons name="business" size={68} color="rgba(121,163,255,.28)" /></View>}
        <View style={styles.heroBody}>
          {logo ? <Image source={{ uri: logo }} style={styles.logo} /> : <View style={[styles.logo, styles.logoFallback]}><Text style={styles.initials}>{initials(item.name)}</Text></View>}
          <Text style={styles.eyebrow}>SPORTSUNIVERSE CLUB</Text>
          <Text style={styles.title}>{item.name}</Text>
          {location ? <Text style={styles.location}><Ionicons name="location-outline" size={14} /> {location}</Text> : null}
          <View style={styles.metrics}><Metric value={item.staff_count} label="Staff" /><Metric value={item.opportunities_count} label="Open opportunities" /></View>
          {website ? <PrimaryButton label="Visit website" secondary onPress={() => Linking.openURL(website)} style={{ marginTop: 16 }} /> : null}
        </View>
      </View>

      <Section title="About the club" icon="information-circle-outline">
        <Text style={styles.body}>{item.bio || 'This club has not added its story yet.'}</Text>
      </Section>

      <Section title="Open opportunities" icon="briefcase-outline">
        {item.opportunities?.length ? item.opportunities.map(opportunity => <Pressable key={opportunity.id} onPress={() => router.push(`/opportunity/${opportunity.id}` as never)} style={({ pressed }) => [styles.rowCard, pressed && styles.pressed]}>
          <View style={styles.rowIcon}><Ionicons name="trophy-outline" size={20} color="#79A3FF" /></View>
          <View style={styles.rowContent}><Text style={styles.rowTitle}>{opportunity.title}</Text><Text style={styles.rowMeta}>{[opportunity.sport, opportunity.type, opportunity.is_remote ? 'Remote' : opportunity.city].filter(Boolean).join(' · ')}</Text>{opportunity.deadline ? <Text style={styles.deadline}>Closes {new Date(opportunity.deadline).toLocaleDateString()}</Text> : null}</View>
          <Ionicons name="chevron-forward" size={19} color={colors.muted} />
        </Pressable>) : <Empty icon="calendar-clear-outline" text="No public opportunities right now." />}
      </Section>

      <Section title="Club staff" icon="people-outline">
        {item.staff?.length ? item.staff.map(member => <Pressable key={member.id} disabled={!member.slug} onPress={() => member.slug && router.push(`/profile/${member.slug}` as never)} style={({ pressed }) => [styles.rowCard, pressed && styles.pressed]}>
          {absoluteMediaUrl(member.image) ? <Image source={{ uri: absoluteMediaUrl(member.image)! }} style={styles.staffImage} /> : <View style={[styles.staffImage, styles.staffFallback]}><Text style={styles.staffInitials}>{initials(member.name)}</Text></View>}
          <View style={styles.rowContent}><Text style={styles.rowTitle}>{member.name}</Text><Text style={[styles.rowMeta, styles.capitalize]}>{member.role}</Text></View>
          {member.slug ? <Ionicons name="chevron-forward" size={19} color={colors.muted} /> : null}
        </Pressable>) : <Empty icon="people-outline" text="Staff profiles will appear here." />}
      </Section>
    </ScrollView>
  </SafeAreaView>;
}

function TopBar({ share }: { share?: () => void }) { return <View style={styles.topBar}><Pressable accessibilityLabel="Go back" hitSlop={12} onPress={() => router.back()}><Ionicons name="arrow-back" size={24} color={colors.white} /></Pressable><Text style={styles.topTitle}>Club</Text>{share ? <Pressable accessibilityLabel="Share club" hitSlop={12} onPress={share}><Ionicons name="share-social-outline" size={22} color={colors.white} /></Pressable> : <View style={{ width: 24 }} />}</View>; }
function Metric({ value, label }: { value: number; label: string }) { return <View style={styles.metric}><Text style={styles.metricValue}>{value}</Text><Text style={styles.metricLabel}>{label}</Text></View>; }
function Section({ title, icon, children }: { title: string; icon: keyof typeof Ionicons.glyphMap; children: React.ReactNode }) { return <View style={styles.section}><View style={styles.sectionHeading}><Ionicons name={icon} size={20} color="#79A3FF" /><Text style={styles.sectionTitle}>{title}</Text></View>{children}</View>; }
function Empty({ icon, text }: { icon: keyof typeof Ionicons.glyphMap; text: string }) { return <View style={styles.empty}><Ionicons name={icon} size={28} color={colors.muted} /><Text style={styles.emptyText}>{text}</Text></View>; }
function initials(name: string) { return name.split(/\s+/).slice(0, 2).map(part => part[0]).join('').toUpperCase(); }

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: colors.navy }, topBar: { height: 56, paddingHorizontal: spacing.md, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', borderBottomWidth: 1, borderBottomColor: colors.line }, topTitle: { color: colors.white, fontSize: 15, fontWeight: '900' }, content: { paddingBottom: 40 },
  hero: { borderBottomWidth: 1, borderBottomColor: colors.line }, cover: { width: '100%', height: 170, backgroundColor: colors.navyLight }, coverFallback: { height: 170, alignItems: 'center', justifyContent: 'center', backgroundColor: colors.surface }, heroBody: { alignItems: 'center', paddingHorizontal: spacing.lg, paddingBottom: 25 }, logo: { width: 92, height: 92, marginTop: -46, borderRadius: 46, borderWidth: 4, borderColor: colors.navy, backgroundColor: colors.surfaceRaised }, logoFallback: { alignItems: 'center', justifyContent: 'center', backgroundColor: colors.blue }, initials: { color: '#fff', fontSize: 25, fontWeight: '900' }, eyebrow: { color: '#79A3FF', fontSize: 10, fontWeight: '900', letterSpacing: 1.2, marginTop: 12 }, title: { color: colors.white, fontSize: 28, lineHeight: 33, fontWeight: '900', textAlign: 'center', marginTop: 5 }, location: { color: colors.muted, fontSize: 12, marginTop: 8 }, metrics: { flexDirection: 'row', marginTop: 17, borderRadius: radius.md, backgroundColor: colors.surface }, metric: { minWidth: 122, alignItems: 'center', paddingHorizontal: 14, paddingVertical: 12 }, metricValue: { color: colors.white, fontSize: 18, fontWeight: '900' }, metricLabel: { color: colors.muted, fontSize: 10, marginTop: 2 },
  section: { paddingHorizontal: spacing.md, marginTop: 28 }, sectionHeading: { flexDirection: 'row', alignItems: 'center', gap: 8, marginBottom: 12 }, sectionTitle: { color: colors.white, fontSize: 18, fontWeight: '900' }, body: { color: '#D2DEEB', fontSize: 14, lineHeight: 22 }, rowCard: { minHeight: 76, flexDirection: 'row', alignItems: 'center', gap: 12, padding: 12, marginBottom: 9, borderRadius: radius.md, borderWidth: 1, borderColor: colors.line, backgroundColor: colors.surface }, pressed: { opacity: .75 }, rowIcon: { width: 42, height: 42, borderRadius: 21, alignItems: 'center', justifyContent: 'center', backgroundColor: 'rgba(71,111,234,.13)' }, rowContent: { flex: 1 }, rowTitle: { color: colors.white, fontSize: 14, fontWeight: '900' }, rowMeta: { color: colors.muted, fontSize: 11, marginTop: 4 }, deadline: { color: '#79A3FF', fontSize: 10, fontWeight: '700', marginTop: 4 }, staffImage: { width: 46, height: 46, borderRadius: 23, backgroundColor: colors.navyLight }, staffFallback: { alignItems: 'center', justifyContent: 'center', backgroundColor: colors.blue }, staffInitials: { color: '#fff', fontSize: 13, fontWeight: '900' }, capitalize: { textTransform: 'capitalize' }, empty: { alignItems: 'center', gap: 8, padding: 25, borderRadius: radius.md, borderWidth: 1, borderColor: colors.line, borderStyle: 'dashed' }, emptyText: { color: colors.muted, fontSize: 12, textAlign: 'center' },
});
