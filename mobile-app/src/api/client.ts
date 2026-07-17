import axios from 'axios';
import { Platform } from 'react-native';
import * as SecureStore from 'expo-secure-store';
import { useConnectivityStore } from '../stores/connectivity';

const fallback = Platform.OS === 'android' ? 'http://10.0.2.2:8000/api/v1' : 'http://localhost:8000/api/v1';

export const api = axios.create({ baseURL: process.env.EXPO_PUBLIC_API_URL || fallback, timeout: 15000, headers: { Accept: 'application/json' } });
api.interceptors.request.use(async (config) => {
  const token = await SecureStore.getItemAsync('sportuniverse_token');
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});
api.interceptors.response.use(
  response => {
    useConnectivityStore.getState().markAvailable();
    return response;
  },
  error => {
    if (!error?.response || error?.code === 'ERR_NETWORK' || error?.code === 'ECONNABORTED') useConnectivityStore.getState().markUnavailable();
    return Promise.reject(error);
  },
);
