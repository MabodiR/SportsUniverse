import { useEffect, useState } from 'react';
import { Modal, Pressable, StyleSheet, Text, View } from 'react-native';
import { Ionicons } from '@expo/vector-icons';
import { LinearGradient } from 'expo-linear-gradient';
import * as SecureStore from 'expo-secure-store';
import { router } from 'expo-router';
import { useQuery } from '@tanstack/react-query';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { api } from '../api/client';
import { useAuthStore } from '../stores/auth';
import { colors, radius, spacing } from '../theme';
import type { MobileConfig } from '../types/promotion';

export function PromotionExperience() {
  const user = useAuthStore(state => state.user);
  const insets = useSafeAreaInsets();
  const [bannerVisible, setBannerVisible] = useState(false);
  const [popupVisible, setPopupVisible] = useState(false);
  const config = useQuery({ queryKey: ['mobile-config'], queryFn: async () => (await api.get<{ data: MobileConfig }>('/mobile/config')).data.data, staleTime: 30 * 1000, refetchInterval: 30 * 1000, retry: 1 });
  const promotion = config.data?.promotion;

  useEffect(() => {
    if (user || !promotion) { setBannerVisible(false); setPopupVisible(false); return; }
    let active = true;
    let timer: ReturnType<typeof setTimeout> | undefined;
    Promise.all([
      SecureStore.getItemAsync(`promotion-banner-dismissed-${promotion.id}`),
      SecureStore.getItemAsync(`promotion-popup-dismissed-${promotion.id}`),
    ]).then(([bannerDismissed, popupDismissed]) => {
      if (!active) return;
      setBannerVisible(promotion.show_banner && !bannerDismissed);
      if (promotion.show_popup && !popupDismissed) timer = setTimeout(() => active && setPopupVisible(true), 850);
    });
    return () => { active = false; if (timer) clearTimeout(timer); };
  }, [promotion?.id, promotion?.show_banner, promotion?.show_popup, user]);

  if (user || !promotion) return null;
  const dismissBanner = async () => { setBannerVisible(false); await SecureStore.setItemAsync(`promotion-banner-dismissed-${promotion.id}`, '1'); };
  const dismissPopup = async () => { setPopupVisible(false); await SecureStore.setItemAsync(`promotion-popup-dismissed-${promotion.id}`, '1'); };
  const claim = async () => { await dismissPopup(); router.push({ pathname: '/(auth)/register', params: { voucher: promotion.code } }); };
  const claimed = Math.max(0, promotion.limit - promotion.remaining);
  const progress = promotion.limit ? Math.max(2, Math.min(100, claimed / promotion.limit * 100)) : 2;

  return <>
    {bannerVisible ? <LinearGradient colors={['#08BBDD', '#476FEA', '#D92C91']} start={{ x: 0, y: .5 }} end={{ x: 1, y: .5 }} style={[styles.banner, { top: insets.top + 4 }]}>
      <Ionicons name="gift-outline" size={18} color="#fff" />
      <Pressable accessibilityRole="button" onPress={claim} style={styles.bannerCopy}><Text style={styles.bannerTitle}>{promotion.title}</Text><Text numberOfLines={1} style={styles.bannerText}>{promotion.remaining} of {promotion.limit} free {promotion.plan ?? 'Pro'} memberships remain</Text></Pressable>
      <Pressable accessibilityLabel="Close Early Adopter banner" hitSlop={8} onPress={dismissBanner} style={styles.bannerClose}><Ionicons name="close" size={18} color="#fff" /></Pressable>
    </LinearGradient> : null}
    <Modal visible={popupVisible} transparent animationType="fade" statusBarTranslucent onRequestClose={dismissPopup}>
      <View style={styles.overlay}><View accessibilityRole="alert" style={styles.card}>
        <View style={styles.glowOne}/><View style={styles.glowTwo}/>
        <Pressable accessibilityLabel="Close promotion" onPress={dismissPopup} style={styles.close}><Ionicons name="close" size={23} color="#071321" /></Pressable>
        <LinearGradient colors={['#16C7E8', '#536FE9', '#EC2D91']} style={styles.gift}><Ionicons name="gift-outline" size={27} color="#fff" /></LinearGradient>
        <Text style={styles.eyebrow}>LIMITED EARLY ADOPTER OFFER</Text>
        <Text style={styles.title}>Early Adopter <Text style={styles.titleAccent}>Special</Text></Text>
        <Text style={styles.copy}>{promotion.message}</Text>
        <View style={styles.remaining}><Text style={styles.remainingNumber}>{promotion.remaining}</Text><Text style={styles.remainingCopy}>of {promotion.limit} free memberships remaining</Text></View>
        <View style={styles.progress}><LinearGradient colors={['#12C4E5', '#EC2D91']} style={[styles.progressFill, { width: `${progress}%` }]} /></View>
        <View style={styles.price}><Text style={styles.pricePlan}>{promotion.duration_label} of {promotion.plan ?? 'Pro'}</Text>{promotion.original_price_cents ? <Text style={styles.oldPrice}>R{Math.round(promotion.original_price_cents / 100)}</Text> : null}<Text style={styles.free}>FREE</Text></View>
        <Pressable accessibilityRole="button" onPress={claim} style={styles.claim}><LinearGradient colors={['#10C5E5', '#178DE7']} start={{ x: 0, y: .5 }} end={{ x: 1, y: .5 }} style={styles.claimGradient}><Text style={styles.claimText}>{promotion.cta_label}</Text><Ionicons name="arrow-forward" size={20} color="#fff" /></LinearGradient></Pressable>
        <View style={styles.safe}><Ionicons name="shield-checkmark-outline" size={17} color="#16BCE2"/><Text style={styles.safeText}>No payment details required</Text></View>
      </View></View>
    </Modal>
  </>;
}

const styles = StyleSheet.create({
  banner: { position: 'absolute', zIndex: 100, left: 10, right: 10, minHeight: 54, paddingLeft: 13, paddingRight: 8, borderRadius: 16, flexDirection: 'row', alignItems: 'center', gap: 10, shadowColor: '#020913', shadowOpacity: .35, shadowRadius: 16, shadowOffset: { width: 0, height: 8 }, elevation: 16 },
  bannerCopy: { flex: 1, minHeight: 48, justifyContent: 'center' }, bannerTitle: { color: '#fff', fontSize: 12, fontWeight: '900' }, bannerText: { color: 'rgba(255,255,255,.87)', fontSize: 10, marginTop: 2 }, bannerClose: { width: 35, height: 35, borderRadius: 18, alignItems: 'center', justifyContent: 'center', backgroundColor: 'rgba(3,12,27,.22)' },
  overlay: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: spacing.lg, backgroundColor: 'rgba(1,7,15,.82)' },
  card: { width: '100%', maxWidth: 390, overflow: 'hidden', alignItems: 'center', padding: 26, borderRadius: 28, borderWidth: 1, borderColor: 'rgba(134,192,237,.42)', backgroundColor: '#F9FCFF' }, glowOne: { position: 'absolute', width: 180, height: 180, borderRadius: 90, left: -90, top: -100, backgroundColor: 'rgba(17,196,229,.12)' }, glowTwo: { position: 'absolute', width: 190, height: 190, borderRadius: 95, right: -100, bottom: -110, backgroundColor: 'rgba(236,45,145,.10)' },
  close: { position: 'absolute', zIndex: 2, right: 14, top: 14, width: 42, height: 42, borderRadius: 21, alignItems: 'center', justifyContent: 'center', backgroundColor: '#EAF0F6' }, gift: { width: 62, height: 62, borderRadius: 19, alignItems: 'center', justifyContent: 'center', marginTop: 4, shadowColor: '#476FEA', shadowOpacity: .3, shadowRadius: 12, elevation: 8 }, eyebrow: { color: '#687C98', fontSize: 10, fontWeight: '900', letterSpacing: .8, marginTop: 17 },
  title: { color: '#071321', fontSize: 27, lineHeight: 32, fontWeight: '900', textAlign: 'center', marginTop: 7 }, titleAccent: { color: '#D92C91' }, copy: { maxWidth: 300, color: '#536782', fontSize: 13, lineHeight: 19, textAlign: 'center', marginTop: 10 }, remaining: { flexDirection: 'row', alignItems: 'flex-end', gap: 7, marginTop: 17 }, remainingNumber: { color: '#E72B90', fontSize: 34, lineHeight: 36, fontWeight: '900' }, remainingCopy: { color: '#536782', fontSize: 11, paddingBottom: 4 }, progress: { width: '100%', height: 6, overflow: 'hidden', borderRadius: 4, backgroundColor: '#DFEAF3', marginTop: 10 }, progressFill: { height: '100%', borderRadius: 4 },
  price: { width: '100%', minHeight: 49, paddingHorizontal: 14, marginTop: 14, borderRadius: radius.md, flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 8, backgroundColor: '#EBF3F8' }, pricePlan: { color: '#586D87', fontSize: 12, fontWeight: '700' }, oldPrice: { color: '#9BAABC', fontSize: 11, textDecorationLine: 'line-through' }, free: { color: '#05A8D5', fontSize: 15, fontWeight: '900' }, claim: { width: '100%', marginTop: 12, borderRadius: radius.md, overflow: 'hidden' }, claimGradient: { minHeight: 52, paddingHorizontal: 18, flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }, claimText: { flex: 1, color: '#fff', fontSize: 14, fontWeight: '900', textAlign: 'center' }, safe: { flexDirection: 'row', alignItems: 'center', gap: 6, marginTop: 14 }, safeText: { color: '#6E829A', fontSize: 11, fontWeight: '700' },
});
