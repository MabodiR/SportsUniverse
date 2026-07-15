export type User = { id: number; name: string; email?: string; phone?: string; roles: string[]; profile?: { completeness: number; city?: string; profile_image?: string } };
export type Video = { id: string; type?: 'video' | 'images' | 'carousel'; caption?: string; hashtags: string[]; comments_enabled?: boolean; visibility?: 'public' | 'followers' | 'private'; status?: 'published' | 'draft'; published_at?: string | null; creator: { id: number; name: string; slug?: string; profile_image?: string | null; sport?: string; position?: string; city?: string }; sport?: { id: number; name: string; slug: string } | null; location?: { name?: string | null }; media?: { id: string; mime_type: string; duration_ms?: number | null; download_url: string } | null; images?: { id: string; download_url: string; is_cover: boolean; position: number }[]; counts: { views: number; likes: number; comments: number; shares: number; saves: number }; viewer?: { liked: boolean; saved: boolean; following_creator: boolean } };
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
  viewer?: { blocked: boolean };
  completeness?: number;
  views_count: number;
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

export type MediaUpload = { id: string; kind: 'image' | 'video' | 'document'; collection: string; original_name: string; mime_type: string; size_bytes: number; processing_status: 'pending' | 'processing' | 'ready' | 'failed'; processing_error?: string | null; moderation_status: string; download_url: string };

export type AppNotification = { id: string; type: string; category: string; data: Record<string, any>; read_at?: string | null; created_at: string };
export type NotificationPreferences = { messages: boolean; message_requests: boolean; opportunities: boolean; followers: boolean; engagement: boolean; moderation: boolean; profile_views: boolean; email_digest: boolean };
