import * as SecureStore from 'expo-secure-store';
import { create } from 'zustand';
import { Platform } from 'react-native';
import { api } from '../api/client';
import type { User } from '../types/api';

type Credentials = { login: string; password: string };
type Registration = { name: string; email: string; phone?: string; password: string; password_confirmation: string; role: string };
type AuthState = { user: User | null; ready: boolean; busy: boolean; error: string | null; hydrate: () => Promise<void>; login: (data: Credentials) => Promise<boolean>; register: (data: Registration) => Promise<boolean>; logout: () => Promise<void>; clearError: () => void };

const tokenKey = 'sportuniverse_token';
const message = (error: any) => error?.response?.data?.message || Object.values(error?.response?.data?.errors || {})?.flat()?.[0] || 'Unable to connect. Please try again.';

export const useAuthStore = create<AuthState>((set) => ({
  user: null, ready: false, busy: false, error: null,
  clearError: () => set({ error: null }),
  hydrate: async () => {
    const token = await SecureStore.getItemAsync(tokenKey);
    if (!token) return set({ ready: true });
    try { const { data } = await api.get('/me'); set({ user: data.data, ready: true }); }
    catch { await SecureStore.deleteItemAsync(tokenKey); set({ user: null, ready: true }); }
  },
  login: async (credentials) => {
    set({ busy: true, error: null });
    try { const { data } = await api.post('/auth/login', { ...credentials, device_name: `${Platform.OS}-mobile` }); await SecureStore.setItemAsync(tokenKey, data.token); set({ user: data.data, busy: false }); return true; }
    catch (error) { set({ error: message(error), busy: false }); return false; }
  },
  register: async ({ role, ...registration }) => {
    set({ busy: true, error: null });
    try { const { data } = await api.post('/auth/register', { ...registration, device_name: `${Platform.OS}-mobile` }); await SecureStore.setItemAsync(tokenKey, data.token); await api.put('/onboarding/role', { role }); const me = await api.get('/me'); set({ user: me.data.data, busy: false }); return true; }
    catch (error) { set({ error: message(error), busy: false }); return false; }
  },
  logout: async () => { try { await api.post('/auth/logout'); } finally { await SecureStore.deleteItemAsync(tokenKey); set({ user: null }); } },
}));
