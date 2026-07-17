import { ActivityIndicator, Pressable, StyleSheet, Text, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import type { QueryClient } from '@tanstack/react-query';
import { api } from '../api/client';
import { useConnectivityStore } from '../stores/connectivity';
import { colors } from '../theme';

export function ConnectivityBanner({ queryClient }: { queryClient: QueryClient }) {
  const { unavailable, checking, markAvailable, markUnavailable, setChecking } = useConnectivityStore();
  if (!unavailable) return null;

  const retry = async () => {
    setChecking(true);
    try {
      await api.get('/sports', { timeout: 8000, headers: { 'X-Connectivity-Check': '1' } });
      markAvailable();
      await queryClient.refetchQueries({ type: 'active' });
    } catch (error: any) {
      if (error?.response) {
        markAvailable();
        await queryClient.refetchQueries({ type: 'active' });
      } else {
        markUnavailable();
      }
    }
  };

  return <View accessibilityLiveRegion="assertive" accessibilityRole="alert" style={styles.banner}>
    <Ionicons name="cloud-offline-outline" size={18} color="#fff" />
    <Text style={styles.text}>You’re offline or SportUniverse cannot be reached.</Text>
    <Pressable accessibilityRole="button" disabled={checking} hitSlop={8} onPress={retry} style={styles.retry}>
      {checking ? <ActivityIndicator size="small" color="#fff" /> : <Text style={styles.retryText}>Retry</Text>}
    </Pressable>
  </View>;
}

const styles = StyleSheet.create({
  banner: { minHeight: 44, paddingHorizontal: 14, paddingVertical: 9, flexDirection: 'row', alignItems: 'center', gap: 9, backgroundColor: '#A83A52' },
  text: { flex: 1, color: '#fff', fontSize: 11, lineHeight: 15, fontWeight: '700' },
  retry: { minWidth: 45, height: 28, alignItems: 'center', justifyContent: 'center', paddingHorizontal: 8, borderRadius: 7, backgroundColor: 'rgba(255,255,255,.16)' },
  retryText: { color: '#fff', fontSize: 11, fontWeight: '900' },
});
