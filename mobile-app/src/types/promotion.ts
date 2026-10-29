export type EarlyAdopterPromotion = {
  id: number;
  title: string;
  message: string;
  cta_label: string;
  cta_url: string;
  show_banner: boolean;
  show_popup: boolean;
  code: string;
  remaining: number;
  limit: number;
  plan?: string | null;
  original_price_cents?: number | null;
};

export type MobileConfig = {
  android: { minimum_version: string; latest_version: string; download_url?: string | null };
  ios: { minimum_version: string; latest_version: string; download_url?: string | null };
  release_notes?: string | null;
  promotion?: EarlyAdopterPromotion | null;
};
