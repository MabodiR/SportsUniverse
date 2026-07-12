import axios from 'axios';
import { Platform } from 'react-native';
import * as SecureStore from 'expo-secure-store';

const fallback = Platform.OS === 'android' ? 'http://10.0.2.2:8000/api/v1' : 'http://localhost:8000/api/v1';

export const api = axios.create({ baseURL: process.env.EXPO_PUBLIC_API_URL || fallback, timeout: 15000, headers: { Accept: 'application/json' } });
api.interceptors.request.use(async (config) => {
  const token = await SecureStore.getItemAsync('sportuniverse_token');
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});
