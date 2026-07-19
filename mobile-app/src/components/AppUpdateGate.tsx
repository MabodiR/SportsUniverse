import { useState } from 'react';
import { Linking, Modal, Platform, Pressable, StyleSheet, Text, View } from 'react-native';
import Constants from 'expo-constants';
import { Ionicons } from '@expo/vector-icons';
import { useQuery } from '@tanstack/react-query';
import { api } from '../api/client';
import { colors, radius, spacing } from '../theme';

type PlatformConfig = { minimum_version: string; latest_version: string; download_url?: string | null };
type MobileConfig = { android: PlatformConfig; ios: PlatformConfig; release_notes?: string | null };

export function AppUpdateGate() {
  const [dismissedVersion, setDismissedVersion] = useState<string>();
  const config = useQuery({ queryKey: ['mobile-config'], queryFn: async () => (await api.get<{ data: MobileConfig }>('/mobile/config')).data.data, staleTime: 60 * 60 * 1000, retry: 1 });
  if (!config.data || Platform.OS === 'web') return null;

  const current = Constants.expoConfig?.version ?? '1.0.0';
  const platform = Platform.OS === 'ios' ? config.data.ios : config.data.android;
  const required = compareVersions(current, platform.minimum_version) < 0;
  const available = compareVersions(current, platform.latest_version) < 0;
  if ((!required && !available) || (!required && dismissedVersion === platform.latest_version)) return null;

  return <Modal visible transparent animationType="fade" onRequestClose={() => { if (!required) setDismissedVersion(platform.latest_version); }}>
    <View style={styles.overlay}>
      <View accessibilityRole="alert" style={styles.card}>
        <View style={[styles.icon, required && styles.iconRequired]}><Ionicons name={required ? 'warning' : 'sparkles'} size={27} color={required ? colors.orange : '#79A3FF'} /></View>
        <Text style={styles.eyebrow}>{required ? 'UPDATE REQUIRED' : 'UPDATE AVAILABLE'}</Text>
        <Text style={styles.title}>{required ? 'Update SportsUniverse to continue' : 'A new SportsUniverse version is ready'}</Text>
        <Text style={styles.copy}>{required ? 'This version is no longer supported. Update now to keep your account secure and features working correctly.' : config.data.release_notes || 'Update for the latest features, fixes and performance improvements.'}</Text>
        <Text style={styles.version}>Installed {current} · Latest {platform.latest_version}</Text>
        {platform.download_url ? <Pressable accessibilityRole="link" onPress={() => Linking.openURL(platform.download_url!)} style={styles.primary}><Text style={styles.primaryText}>Update now</Text></Pressable> : <Text style={styles.missing}>The download link is being prepared. Please check again shortly.</Text>}
        {!required ? <Pressable accessibilityRole="button" onPress={() => setDismissedVersion(platform.latest_version)} style={styles.later}><Text style={styles.laterText}>Not now</Text></Pressable> : null}
      </View>
    </View>
  </Modal>;
}

export function compareVersions(left: string, right: string): number {
  const a = left.split(/[.+-]/).map(value => Number.parseInt(value, 10) || 0);
  const b = right.split(/[.+-]/).map(value => Number.parseInt(value, 10) || 0);
  for (let index = 0; index < Math.max(a.length, b.length); index++) {
    if ((a[index] ?? 0) !== (b[index] ?? 0)) return (a[index] ?? 0) > (b[index] ?? 0) ? 1 : -1;
  }
  return 0;
}

const styles = StyleSheet.create({
  overlay: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: spacing.lg, backgroundColor: 'rgba(1,6,11,.82)' },
  card: { width: '100%', maxWidth: 390, alignItems: 'center', padding: 26, borderRadius: radius.lg, borderWidth: 1, borderColor: colors.line, backgroundColor: colors.navyLight },
  icon: { width: 58, height: 58, borderRadius: 29, alignItems: 'center', justifyContent: 'center', backgroundColor: 'rgba(71,111,234,.14)' },
  iconRequired: { backgroundColor: 'rgba(255,176,32,.12)' },
  eyebrow: { color: '#79A3FF', fontSize: 10, fontWeight: '900', letterSpacing: 1.1, marginTop: 15 },
  title: { color: '#fff', fontSize: 22, lineHeight: 27, fontWeight: '900', textAlign: 'center', marginTop: 7 },
  copy: { color: colors.muted, fontSize: 13, lineHeight: 20, textAlign: 'center', marginTop: 10 },
  version: { color: '#B8C8DA', fontSize: 10, fontWeight: '800', marginTop: 15 },
  primary: { width: '100%', minHeight: 50, alignItems: 'center', justifyContent: 'center', borderRadius: radius.md, backgroundColor: colors.blue, marginTop: 20 },
  primaryText: { color: '#fff', fontSize: 14, fontWeight: '900' },
  later: { minHeight: 42, justifyContent: 'center', marginTop: 6 },
  laterText: { color: '#91ACCA', fontSize: 12, fontWeight: '800' },
  missing: { color: colors.orange, fontSize: 11, lineHeight: 16, textAlign: 'center', marginTop: 18 },
});
