import { Platform } from 'react-native';
import Constants from 'expo-constants';
import * as Device from 'expo-device';
import * as Notifications from 'expo-notifications';
import * as SecureStore from 'expo-secure-store';
import { api } from '../api/client';

const storageKey = 'sportuniverse_expo_push_token';

export async function enablePushNotifications(): Promise<string> {
  if (!Device.isDevice) throw new Error('Push notifications require a physical device.');
  const current = await Notifications.getPermissionsAsync();
  const permission = current.granted ? current : await Notifications.requestPermissionsAsync();
  if (!permission.granted) throw new Error('Notification permission was not granted.');
  if (Platform.OS === 'android') {
    await Notifications.setNotificationChannelAsync('default', { name: 'SportsUniverse', importance: Notifications.AndroidImportance.HIGH, vibrationPattern: [0, 250, 200, 250], lightColor: '#1B63F3' });
  }
  const projectId = Constants.easConfig?.projectId ?? Constants.expoConfig?.extra?.eas?.projectId;
  const token = (await Notifications.getExpoPushTokenAsync(projectId ? { projectId } : undefined)).data;
  await api.post('/push-subscriptions', { provider: 'expo', token, platform: Platform.OS, device_name: Device.deviceName || `${Platform.OS} device` });
  await SecureStore.setItemAsync(storageKey, token);
  return token;
}

export async function disablePushNotifications(): Promise<void> {
  const token = await SecureStore.getItemAsync(storageKey);
  if (token) await api.delete('/push-subscriptions', { data: { token } });
  await SecureStore.deleteItemAsync(storageKey);
}

export async function pushEnabled(): Promise<boolean> {
  return Boolean(await SecureStore.getItemAsync(storageKey));
}

export function routeForNotification(data: Record<string, any>): string {
  if (data.conversation_id) return `/conversation/${data.conversation_id}`;
  if (data.video_id) return `/post/${data.video_id}`;
  if (data.opportunity_id) return `/opportunity/${data.opportunity_id}`;
  if (data.profile_slug || data.slug) return `/profile/${data.profile_slug || data.slug}`;
  return '/notifications';
}
