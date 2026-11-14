export type AttachmentData = {
    id: number;
    name: string;
    mime: string;
    size: number;
    width: number;
    height: number;
    url: string;
    thumbnail_url: string;
    download_url: string;
};

export type CommentData = {
    id: number;
    author: string;
    role: 'instructor' | 'staff' | 'admin';
    role_label: string;
    body: string | null;
    created_at: string;
    edited_at: string | null;
    is_mine: boolean;
    images: AttachmentData[];
};

export type StudentState = 'not_submitted' | 'on_time' | 'late' | 'feedback';

export type AssignmentPanelData = {
    assignment: {
        id: number;
        title: string;
        instructions_html: string | null;
        is_final_project: boolean;
        max_images: number;
    };
    deadline: { due_at: string; is_extended: boolean; has_passed: boolean };
    state: StudentState;
    state_label: string;
    submission: {
        id: number;
        body: string | null;
        submitted_at: string;
        is_late: boolean;
        is_reviewed: boolean;
        can_edit: boolean;
        edit_blocker: string | null;
        images: AttachmentData[];
        comments: CommentData[];
        had_unseen_feedback: boolean;
    } | null;
    limits: {
        max_images: number;
        max_image_kb: number;
        max_image_label: string;
        accept: string;
    };
};

export type AssignmentForm = {
    id: number;
    lesson_id: number | null;
    is_final_project: boolean;
    title: string;
    instructions_html: string | null;
    due_offset_days: number;
    max_images: number;
    is_required: boolean;
    status: 'draft' | 'published';
    status_label: string;
    submissions_count?: number;
};

export type ReviewState =
    | 'not_submitted'
    | 'on_time'
    | 'needs_review'
    | 'reviewed'
    | 'late';

export type AssignmentOverview = {
    id: number;
    title: string;
    status: 'draft' | 'published';
    status_label: string;
    is_final_project: boolean;
    lesson: { title: string; day_number: number } | null;
    due_offset_days: number;
    deadline: {
        default: string;
        override: string | null;
        effective: string;
        has_passed: boolean;
    } | null;
    counts: {
        not_submitted: number;
        awaiting_review: number;
        needs_review: number;
        reviewed: number;
        late: number;
    };
};

export type BatchOptionLite = {
    id: number;
    name: string;
    status_label: string;
};
