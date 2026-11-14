export type BatchOption = {
    id: number;
    name: string;
    starts_on: string;
    ends_on: string;
    registration_closes_on: string | null;
    status: 'upcoming' | 'running' | 'finished';
    status_label: string;
    seats_left: number | null;
    is_open: boolean;
    closed_reason: string | null;
};

export type BatchData = {
    id: number;
    name: string;
    starts_on: string;
    ends_on: string;
    registration_closes_on: string | null;
    capacity: number | null;
    is_published: boolean;
    status: 'upcoming' | 'running' | 'finished';
    status_label: string;
    seats_taken: number;
    seats_left: number | null;
    over_capacity: boolean;
    is_open: boolean;
    enrollments_count?: number;
};

export type OutlineLesson = {
    id: number;
    slug: string;
    title: string;
    type: 'video' | 'live';
    duration_seconds: number | null;
    is_preview: boolean;
    day_number: number | null;
    status: 'completed' | 'unlocked' | 'locked';
    unlock_at: string | null;
};

export type OutlineModule = {
    id: number;
    title: string;
    lessons: OutlineLesson[];
};

export type LearnCourse = {
    title: string;
    slug: string;
    drip_enabled: boolean;
};

export type LearnEnrollment = {
    progress_percent: number;
    expires_at: string | null;
    batch: {
        name: string;
        starts_on: string;
        ends_on: string;
        status_label: string;
    } | null;
};

export type LessonContent = {
    embed_url: string | null;
    summary_html: string | null;
    live: {
        starts_at: string;
        ends_at: string | null;
        platform: string | null;
        meeting_url: string | null;
        notes: string | null;
    } | null;
};
