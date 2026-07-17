import { ActivityIndicator, Pressable, RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { Ionicons } from '@expo/vector-icons';
import { router } from 'expo-router';
import { useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import { api } from '../../src/api/client';
import { PrimaryButton } from '../../src/components/PrimaryButton';
import { ScreenMessage } from '../../src/components/ScreenMessage';
import { colors, radius, spacing } from '../../src/theme';
import type { ApiResponse, CreatorAnalytics } from '../../src/types/api';

const periods = [7, 30, 90] as const;
const compact = (value = 0) => Intl.NumberFormat(undefined, { notation: 'compact', maximumFractionDigits: 1 }).format(value);

export default function AnalyticsScreen() {
  const [days, setDays] = useState<(typeof periods)[number]>(30);
  const analytics = useQuery({ queryKey: ['analytics', days], queryFn: async () => (await api.get<ApiResponse<CreatorAnalytics>>('/analytics/me', { params: { days } })).data.data });
  const data = analytics.data;

  return <SafeAreaView edges={['top']} style={styles.safe}>
    <View style={styles.header}><Pressable accessibilityLabel="Go back" hitSlop={12} onPress={() => router.back()}><Ionicons name="arrow-back" size={24} color="#fff" /></Pressable><Text style={styles.headerTitle}>Creator analytics</Text><Pressable accessibilityLabel="Refresh analytics" hitSlop={12} onPress={() => analytics.refetch()}><Ionicons name="refresh" size={22} color="#fff" /></Pressable></View>
    {analytics.isLoading ? <ActivityIndicator style={{ flex: 1 }} color={colors.blue} /> : analytics.isError || !data ? <ScreenMessage icon="stats-chart-outline" title="Analytics unavailable" message="Check your connection and try again." action={<PrimaryButton label="Try again" secondary onPress={() => analytics.refetch()} />} /> :
    <ScrollView refreshControl={<RefreshControl refreshing={analytics.isRefetching} onRefresh={() => analytics.refetch()} tintColor={colors.blue} />} contentContainerStyle={styles.content}>
      <Text style={styles.eyebrow}>YOUR PERFORMANCE</Text><Text style={styles.title}>Understand your reach.</Text><Text style={styles.copy}>See what attracts viewers, followers and opportunity applicants.</Text>
      <View style={styles.periods}>{periods.map(value => <Pressable key={value} onPress={() => setDays(value)} style={[styles.period, days === value && styles.periodActive]}><Text style={[styles.periodText, days === value && styles.periodTextActive]}>{value === 7 ? '7 days' : value === 30 ? '30 days' : '90 days'}</Text></Pressable>)}</View>
      <View style={styles.metrics}>
        <Metric icon="eye-outline" label="Views this period" value={compact(data.period.views)} detail={`${compact(data.period.video_views)} video · ${compact(data.period.profile_views)} profile`} />
        <Metric icon="trending-up-outline" label="Engagement rate" value={`${data.period.engagement_rate}%`} detail={`${compact(data.period.interactions)} interactions`} accent={colors.pink} />
        <Metric icon="people-outline" label="Followers" value={compact(data.totals.followers)} detail={`${compact(data.totals.profile_views)} profile views`} accent={colors.green} />
        <Metric icon="heart-outline" label="Lifetime likes" value={compact(data.totals.likes)} detail={`${compact(data.totals.shares)} shares`} accent={colors.orange} />
      </View>
      <Panel title="Top content" subtitle="Most viewed published posts">
        {data.top_videos.length ? data.top_videos.map((post, index) => <Pressable key={post.id} onPress={() => router.push('/profile/my-posts')} style={styles.post}><Text style={styles.rank}>{index + 1}</Text><View style={{ flex: 1 }}><Text numberOfLines={2} style={styles.postTitle}>{post.caption}</Text><Text style={styles.postDate}>{post.published_at ? new Date(post.published_at).toLocaleDateString() : 'Published post'}</Text></View><View style={styles.postStat}><Ionicons name="eye-outline" size={14} color={colors.muted} /><Text style={styles.postStatText}>{compact(post.views)}</Text></View><View style={styles.postStat}><Ionicons name="heart-outline" size={14} color={colors.muted} /><Text style={styles.postStatText}>{compact(post.likes)}</Text></View></Pressable>) : <Text style={styles.empty}>Publish a post to start measuring performance.</Text>}
      </Panel>
      <Panel title="Audience locations" subtitle="Where profile viewers are based">
        {data.locations.length ? data.locations.map((location, index) => <View key={location.city} style={styles.location}><View style={styles.locationTop}><Text style={styles.locationName}><Ionicons name="location-outline" size={14} /> {location.city}</Text><Text style={styles.locationViews}>{compact(location.views)} views</Text></View><View style={styles.track}><View style={[styles.fill, { width: `${location.views / Math.max(data.locations[0]?.views ?? 1, 1) * 100}%` }]} /></View></View>) : <Text style={styles.empty}>Location insights appear as members view your profile.</Text>}
      </Panel>
      <View style={styles.applicationCard}><Ionicons name="briefcase-outline" size={24} color={colors.orange} /><View style={{ flex: 1 }}><Text style={styles.applicationValue}>{compact(data.totals.opportunity_applications)}</Text><Text style={styles.applicationLabel}>Applications received on your opportunities</Text></View></View>
    </ScrollView>}
  </SafeAreaView>;
}

function Metric({ icon, label, value, detail, accent = colors.blue }: { icon: keyof typeof Ionicons.glyphMap; label: string; value: string; detail: string; accent?: string }) { return <View style={styles.metric}><View style={[styles.metricIcon, { backgroundColor: `${accent}20` }]}><Ionicons name={icon} size={20} color={accent} /></View><Text style={styles.metricLabel}>{label}</Text><Text style={styles.metricValue}>{value}</Text><Text style={styles.metricDetail}>{detail}</Text></View>; }
function Panel({ title, subtitle, children }: { title: string; subtitle: string; children: React.ReactNode }) { return <View style={styles.panel}><Text style={styles.panelEyebrow}>{title.toUpperCase()}</Text><Text style={styles.panelTitle}>{subtitle}</Text><View style={{ marginTop: 12 }}>{children}</View></View>; }

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: colors.navy }, header: { height: 56, paddingHorizontal: spacing.md, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', borderBottomWidth: 1, borderBottomColor: colors.line }, headerTitle: { color: '#fff', fontSize: 16, fontWeight: '900' }, content: { padding: spacing.lg, paddingBottom: 48 }, eyebrow: { color: '#79A3FF', fontSize: 10, fontWeight: '900', letterSpacing: 1.2 }, title: { color: '#fff', fontSize: 30, fontWeight: '900', letterSpacing: -1, marginTop: 7 }, copy: { color: colors.muted, fontSize: 13, lineHeight: 20, marginTop: 7 }, periods: { flexDirection: 'row', gap: 7, marginTop: 22 }, period: { flex: 1, alignItems: 'center', paddingVertical: 11, borderRadius: radius.md, borderWidth: 1, borderColor: colors.line, backgroundColor: colors.surface }, periodActive: { borderColor: colors.blue, backgroundColor: colors.blue }, periodText: { color: colors.muted, fontSize: 11, fontWeight: '800' }, periodTextActive: { color: '#fff' }, metrics: { flexDirection: 'row', flexWrap: 'wrap', gap: 10, marginTop: 18 }, metric: { width: '48.5%', minHeight: 160, padding: 14, borderWidth: 1, borderColor: colors.line, borderRadius: radius.lg, backgroundColor: colors.surface }, metricIcon: { width: 38, height: 38, alignItems: 'center', justifyContent: 'center', borderRadius: 19 }, metricLabel: { color: colors.muted, fontSize: 10, fontWeight: '800', marginTop: 12 }, metricValue: { color: '#fff', fontSize: 25, fontWeight: '900', marginTop: 3 }, metricDetail: { color: colors.muted, fontSize: 9, lineHeight: 14, marginTop: 3 }, panel: { padding: spacing.md, marginTop: 14, borderWidth: 1, borderColor: colors.line, borderRadius: radius.lg, backgroundColor: colors.surface }, panelEyebrow: { color: '#79A3FF', fontSize: 9, fontWeight: '900', letterSpacing: 1 }, panelTitle: { color: '#fff', fontSize: 17, fontWeight: '900', marginTop: 3 }, post: { minHeight: 58, flexDirection: 'row', alignItems: 'center', gap: 9, paddingVertical: 9, borderTopWidth: StyleSheet.hairlineWidth, borderTopColor: colors.line }, rank: { width: 24, color: '#79A3FF', fontSize: 17, fontWeight: '900' }, postTitle: { color: '#fff', fontSize: 12, fontWeight: '800' }, postDate: { color: colors.muted, fontSize: 9, marginTop: 3 }, postStat: { alignItems: 'center', gap: 2 }, postStatText: { color: colors.muted, fontSize: 9, fontWeight: '800' }, location: { marginTop: 12 }, locationTop: { flexDirection: 'row', justifyContent: 'space-between' }, locationName: { color: '#DCE7F5', fontSize: 12, fontWeight: '800' }, locationViews: { color: colors.muted, fontSize: 10 }, track: { height: 5, overflow: 'hidden', borderRadius: 4, backgroundColor: colors.surfaceRaised, marginTop: 7 }, fill: { height: '100%', borderRadius: 4, backgroundColor: colors.blue }, empty: { color: colors.muted, fontSize: 12, lineHeight: 18, paddingVertical: 12 }, applicationCard: { flexDirection: 'row', alignItems: 'center', gap: 13, padding: spacing.md, marginTop: 14, borderRadius: radius.lg, backgroundColor: 'rgba(255,176,32,.09)' }, applicationValue: { color: '#fff', fontSize: 22, fontWeight: '900' }, applicationLabel: { color: '#D8C28E', fontSize: 11, marginTop: 2 },
});
