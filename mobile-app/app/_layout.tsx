import { useEffect } from 'react';
import { ActivityIndicator, View } from 'react-native';
import { Stack } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { useAuthStore } from '../src/stores/auth';
import { colors } from '../src/theme';

const queryClient = new QueryClient({ defaultOptions: { queries: { retry: 1, staleTime: 30000 } } });
export default function RootLayout() {
  const { ready, hydrate } = useAuthStore();
  useEffect(() => { hydrate(); }, [hydrate]);
  if (!ready) return <View style={{flex:1,backgroundColor:colors.navy,alignItems:'center',justifyContent:'center'}}><ActivityIndicator color={colors.blue}/></View>;
  return <SafeAreaProvider><QueryClientProvider client={queryClient}><StatusBar style="light"/><Stack screenOptions={{headerShown:false,contentStyle:{backgroundColor:colors.navy}}}/></QueryClientProvider></SafeAreaProvider>;
}
