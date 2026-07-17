import { useEffect } from 'react';
import { ActivityIndicator, AppState, Pressable, StyleSheet, Text, View } from 'react-native';
import { router, Stack, type ErrorBoundaryProps } from 'expo-router';
import * as Notifications from 'expo-notifications';
import { StatusBar } from 'expo-status-bar';
import { focusManager, QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { useAuthStore } from '../src/stores/auth';
import { colors } from '../src/theme';
import { routeForNotification } from '../src/notifications/push';
import { ConnectivityBanner } from '../src/components/ConnectivityBanner';
import { AppUpdateGate } from '../src/components/AppUpdateGate';

Notifications.setNotificationHandler({ handleNotification: async () => ({ shouldShowBanner: true, shouldShowList: true, shouldPlaySound: true, shouldSetBadge: true }) });

const queryClient = new QueryClient({ defaultOptions: { queries: { retry: (count, error: any) => count < 2 && (!error?.response || error.response.status >= 500), staleTime: 30000, refetchOnReconnect: true }, mutations: { retry: 0 } } });
export default function RootLayout() {
  const { ready, hydrate, user } = useAuthStore();
  useEffect(() => { hydrate(); }, [hydrate]);
  useEffect(() => {
    const subscription = AppState.addEventListener('change', state => {
      focusManager.setFocused(state === 'active');
      if (state === 'active') queryClient.refetchQueries({ type: 'active', stale: true });
    });
    return () => subscription.remove();
  }, []);
  useEffect(() => {
    const subscription = Notifications.addNotificationResponseReceivedListener(response => router.push(routeForNotification(response.notification.request.content.data as Record<string, any>) as never));
    Notifications.getLastNotificationResponseAsync().then(response => { if (response) router.push(routeForNotification(response.notification.request.content.data as Record<string, any>) as never); });
    return () => subscription.remove();
  }, []);
  useEffect(() => { if (ready && user && !user.onboarding_completed_at) router.replace('/onboarding'); }, [ready, user]);
  if (!ready) return <View style={{flex:1,backgroundColor:colors.navy,alignItems:'center',justifyContent:'center'}}><ActivityIndicator color={colors.blue}/></View>;
  return <SafeAreaProvider><QueryClientProvider client={queryClient}><StatusBar style="light"/><ConnectivityBanner queryClient={queryClient}/><Stack screenOptions={{headerShown:false,contentStyle:{backgroundColor:colors.navy}}}/><AppUpdateGate /></QueryClientProvider></SafeAreaProvider>;
}

export function ErrorBoundary({ error, retry }: ErrorBoundaryProps) {
  return <View style={styles.errorSafe} accessibilityRole="alert">
    <View style={styles.errorIcon}><Text style={styles.errorIconText}>!</Text></View>
    <Text style={styles.errorTitle}>Something went wrong</Text>
    <Text style={styles.errorCopy}>Your information is safe. Try opening this screen again.</Text>
    {__DEV__ ? <Text numberOfLines={4} style={styles.errorDetail}>{error.message}</Text> : null}
    <Pressable accessibilityRole="button" onPress={retry} style={styles.errorButton}><Text style={styles.errorButtonText}>Try again</Text></Pressable>
    <Pressable accessibilityRole="button" onPress={() => router.replace('/(tabs)')} style={styles.homeButton}><Text style={styles.homeButtonText}>Return to home</Text></Pressable>
  </View>;
}

const styles = StyleSheet.create({
  errorSafe: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 28, backgroundColor: colors.navy },
  errorIcon: { width: 58, height: 58, borderRadius: 29, alignItems: 'center', justifyContent: 'center', backgroundColor: 'rgba(230,70,162,.15)' },
  errorIconText: { color: colors.pink, fontSize: 30, fontWeight: '900' },
  errorTitle: { color: '#fff', fontSize: 24, fontWeight: '900', textAlign: 'center', marginTop: 18 },
  errorCopy: { maxWidth: 330, color: colors.muted, fontSize: 14, lineHeight: 21, textAlign: 'center', marginTop: 8 },
  errorDetail: { maxWidth: 360, color: '#E6A7B4', fontSize: 11, lineHeight: 16, textAlign: 'center', marginTop: 13 },
  errorButton: { width: '100%', maxWidth: 330, minHeight: 50, alignItems: 'center', justifyContent: 'center', borderRadius: 12, backgroundColor: colors.blue, marginTop: 22 },
  errorButtonText: { color: '#fff', fontSize: 14, fontWeight: '900' },
  homeButton: { minHeight: 44, justifyContent: 'center', marginTop: 8 },
  homeButtonText: { color: '#79A3FF', fontSize: 13, fontWeight: '800' },
});
