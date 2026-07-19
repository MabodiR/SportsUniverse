export type User = { id: number; name: string; email?: string; email_verified?: boolean; email_verified_at?: string | null; phone?: string; roles: string[]; profile?: { completeness: number; city?: string; profile_image?: string }; onboarding_completed_at?: string | null };
export type Video = { id: string; type?: 'video' | 'images' | 'carousel'; caption?: string; hashtags: string[]; comments_enabled?: boolean; visibility?: 'public' | 'followers' | 'private'; status?: 'published' | 'draft'; published_at?: string | null; creator: { id: number; name: string; slug?: string; profile_image?: string | null; sport?: string; position?: string; city?: string }; sport?: { id: number; name: string; slug: string } | null; location?: { name?: string | null }; media?: { id: string; mime_type: string; duration_ms?: number | null; download_url: string } | null; images?: { id: string; download_url: string; is_cover: boolean; position: number }[]; counts: { views: number; likes: number; comments: number; shares: number; saves: number }; viewer?: { liked: boolean; saved: boolean; following_creator: boolean }; sponsored?: { campaign_id: string; delivery_id: string; label: string; goal: string; cta: string; destination_url: string } | null };
export type Comment = { id: string; body: string; user: { id: number; name: string; slug?: string | null }; parent_id?: string | null; likes_count: number; liked: boolean; replies: Comment[]; created_at: string };
export type ApiResponse<T> = { data: T; message?: string; token?: string };

export type Profile = {
  id: number;
  slug: string;
  name: string;
  roles: string[];
  bio?: string | null;
  date_of_birth?: string | null;
  age?: number | null;
  gender?: string | null;
  location: {
    country?: string | null;
    province?: string | null;
    city?: string | null;
    locality?: string | null;
    township?: string | null;
  };
  images: { profile?: string | null; cover?: string | null };
  is_available: boolean;
  is_public: boolean;
  viewer?: { blocked: boolean; saved?: boolean; following?: boolean };
  completeness?: number;
  views_count: number;
  connections?: { followers: number; following: number };
  athlete?: {
    sport?: { id: number; name: string; slug: string } | null;
    position?: { id: number; name: string; slug: string } | null;
    club_name?: string | null;
    playing_level?: string | null;
    dominant_side?: string | null;
    height_cm?: number | null;
    weight_kg?: number | null;
  } | null;
  career?: AthleteCareer;
  professional?: {
    professional_type: string;
    specialisation?: string | null;
    years_experience?: number | null;
    certifications?: string[] | null;
    is_available?: boolean;
  } | null;
  organisation?: {
    organisation_name: string;
    organisation_type: string;
    registration_number?: string | null;
    website?: string | null;
    contact_email?: string | null;
    contact_phone?: string | null;
    services?: string[] | null;
  } | null;
  fan?: { interested_sports: string[]; favourites?: string | null } | null;
  club?: { name: string; slug: string } | null;
};

export type PublicClub = {
  id: number;
  name: string;
  slug: string;
  bio?: string | null;
  website?: string | null;
  image?: string | null;
  cover_image?: string | null;
  city?: string | null;
  province?: string | null;
  location?: { city?: string | null; province?: string | null; country?: string | null };
  staff_count: number;
  opportunities_count: number;
  staff?: { id: number; name: string; slug?: string | null; image?: string | null; role: string }[];
  opportunities?: { id: string; title: string; type: string; city?: string | null; is_remote: boolean; sport?: string | null; deadline?: string | null }[];
};

export type CareerEntry = { id: number; team_name: string; role?: string | null; level?: string | null; started_on?: string | null; ended_on?: string | null; is_current: boolean; description?: string | null };
export type AthleteAchievement = { id: number; title: string; issuer?: string | null; achieved_on?: string | null; description?: string | null };
export type AthleteStatistic = { id: number; season: string; competition?: string | null; name: string; value: string | number; unit?: string | null };
export type AthleteCareer = { history: CareerEntry[]; achievements: AthleteAchievement[]; statistics: AthleteStatistic[] };

export type SportPosition = { id: number; name: string; slug: string };
export type Sport = { id: number; name: string; slug: string; positions: SportPosition[] };

export type PaginationMeta = {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
};

export type PaginatedResponse<T> = { data: T[]; meta: PaginationMeta };

export type Opportunity = {
  id: string;
  title: string;
  type: string;
  description: string;
  poster: { id: number; name: string; slug?: string | null };
  sport?: { id: number; name: string; slug: string } | null;
  position?: { id: number; name: string; slug: string } | null;
  location: { country?: string | null; province?: string | null; city?: string | null; is_remote: boolean };
  age_range: { minimum?: number | null; maximum?: number | null };
  requirements: string[];
  required_documents: { key: string; label: string; collection: string; required: boolean }[];
  status: string;
  deadline?: string | null;
  applications_count: number;
  viewer: { saved: boolean; applied: boolean };
  published_at?: string | null;
};

export type OpportunityApplication = {
  id: string;
  opportunity: Opportunity;
  applicant: { id: number; name: string; slug?: string | null; image?: string | null };
  cover_letter?: string | null;
  resume?: { id: string; download_url: string } | null;
  documents?: { id: string; requirement_key: string; name: string; collection: string; download_url: string }[];
  status: 'submitted' | 'reviewing' | 'shortlisted' | 'accepted' | 'rejected' | 'withdrawn';
  reviewer_notes?: string | null;
  reviewed_at?: string | null;
  timeline: { status: string; notes?: string | null; created_at: string }[];
  created_at: string;
};

export type Message = {
  id: string;
  sender?: { id: number; name: string; slug?: string | null } | null;
  body?: string | null;
  media?: { id: string; kind: string; mime_type: string; download_url: string } | null;
  read_at?: string | null;
  edited_at?: string | null;
  deleted_at?: string | null;
  created_at: string;
};

export type ConversationParticipant = { id: number; name: string; slug?: string | null; profile_image?: string | null };
export type Conversation = {
  id: string;
  type: string;
  participants: ConversationParticipant[];
  last_message?: Message | null;
  last_message_at?: string | null;
  unread_count: number;
  muted: boolean;
  archived: boolean;
  last_read_at?: string | null;
};

export type MessageRequest = {
  id: string;
  sender: { id: number; name: string; slug?: string | null };
  recipient: { id: number; name: string; slug?: string | null };
  message: string;
  status: string;
  conversation_id?: string | null;
  created_at: string;
};

export type CursorMeta = { next_cursor?: string | null; prev_cursor?: string | null };
export type CursorResponse<T> = { data: T[]; meta: CursorMeta };

export type LiveStream = { id: string; public_id?: string; host_id: number; title: string; description?: string | null; viewer_count: number; status?: string; started_at: string; host_name: string; slug?: string | null; image?: string | null };
export type LiveMessage = { id: number; name: string; body?: string | null; reaction?: 'heart' | 'fire' | 'clap' | 'football' | null; created_at: string; type?: string };
export type LiveRoomResponse = { data: { stream: LiveStream; messages: LiveMessage[] } };

export type MediaUpload = {
  id: string;
  kind: 'image' | 'video' | 'document';
  collection: string;
  title?: string | null;
  description?: string | null;
  original_name: string;
  mime_type: string;
  size_bytes: number;
  processing_status: 'pending' | 'processing' | 'ready' | 'failed';
  processing_error?: string | null;
  moderation_status: string;
  duration_ms?: number | null;
  width?: number | null;
  height?: number | null;
  download_url: string;
  created_at?: string | null;
};

export type AppNotification = { id: string; type: string; category: string; data: Record<string, any>; read_at?: string | null; created_at: string };
export type NotificationPreferences = { messages: boolean; message_requests: boolean; opportunities: boolean; followers: boolean; engagement: boolean; moderation: boolean; profile_views: boolean; email_digest: boolean };

export type CreatorAnalytics = {
  period_days: number;
  totals: { profile_views: number; video_views: number; likes: number; comments: number; shares: number; followers: number; opportunity_applications: number };
  period: { views: number; video_views: number; profile_views: number; interactions: number; likes: number; comments: number; shares: number; engagement_rate: number };
  daily: Record<string, { date: string; value: number }[]>;
  locations: { city: string; views: number }[];
  top_videos: { id: string; caption: string; views: number; likes: number; comments: number; shares: number; published_at?: string | null }[];
};

export type Campaign = {
  id: string;
  campaign_type: 'post_promotion' | 'sponsorship';
  title: string;
  description?: string | null;
  goal: 'views' | 'followers' | 'website' | 'applications' | 'awareness';
  audience: { sport_id?: number | null; gender?: 'female' | 'male' | 'all'; province?: string | null; min_age?: number; max_age?: number };
  destination_url?: string | null;
  daily_budget_cents: number;
  total_budget_cents: number;
  starts_on: string;
  ends_on: string;
  status: string;
  review_notes?: string | null;
  metrics: { impressions: number; clicks: number; click_rate: number; spent_cents: number };
  video?: { id: string; caption?: string | null; url?: string | null } | null;
  created_at: string;
};
