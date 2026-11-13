export type Option<T extends string | number = string> = {
    value: T;
    label: string;
};

export type CourseStatusValue = 'draft' | 'published' | 'archived';
export type LessonTypeValue = 'video' | 'live';
export type LessonStatusValue = 'draft' | 'published';

export type AdminCourse = {
    id: number;
    title: string;
    slug: string;
    summary: string | null;
    description: string | null;
    category_id: number | null;
    category?: string | null;
    thumbnail_url: string | null;
    price: number;
    is_free: boolean;
    status: CourseStatusValue;
    status_label: string;
    drip_enabled: boolean;
    access_duration_days: number | null;
    certificate_enabled: boolean;
    published_at: string | null;
    updated_at: string | null;
    modules_count?: number;
    lessons_count?: number;
    instructors?: string[];
};

export type CategoryItem = { id: number; name: string };

export type AssignedInstructor = {
    id: number;
    name: string;
    email: string;
    role: 'lead' | 'assistant' | null;
};

export type InstructorCandidate = { id: number; name: string; email: string };

export type LiveSessionData = {
    starts_at: string;
    ends_at: string | null;
    platform: string | null;
    meeting_url: string | null;
    notes: string | null;
};

export type CurriculumLesson = {
    id: number;
    module_id: number;
    title: string;
    slug: string;
    type: LessonTypeValue;
    status: LessonStatusValue;
    is_preview: boolean;
    duration_seconds: number | null;
    drip_day_offset: number;
    summary_html: string | null;
    video_url: string | null;
    live: LiveSessionData | null;
};

export type CurriculumModule = {
    id: number;
    title: string;
    lessons: CurriculumLesson[];
};

export type CurriculumTotals = {
    modules: number;
    lessons: number;
    published_lessons: number;
    duration_seconds: number;
};

export type AdminUser = {
    id: number;
    name: string;
    email: string;
    role: 'admin' | 'staff' | 'instructor' | 'student' | null;
    is_active: boolean;
    email_verified: boolean;
    headline: string | null;
    bio: string | null;
    created_at: string | null;
};
