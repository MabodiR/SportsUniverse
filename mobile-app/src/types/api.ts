export type User = { id: number; name: string; email?: string; phone?: string; roles: string[]; profile?: { completeness: number; city?: string; profile_image?: string } };
export type Video = { id: string; caption?: string; hashtags: string[]; creator: { id: number; name: string; slug?: string; sport?: string; position?: string; city?: string }; counts: { views: number; likes: number; comments: number; shares: number; saves: number } };
export type ApiResponse<T> = { data: T; message?: string; token?: string };
