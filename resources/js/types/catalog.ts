export type CategoryOption = {
    name: string;
    slug: string;
};

export type CourseCard = {
    id: number;
    slug: string;
    title: string;
    summary: string | null;
    thumbnail_url: string | null;
    /** Integer rupiah. */
    price: number;
    is_free: boolean;
    category: CategoryOption | null;
    instructors: string[];
};

export type CourseInstructor = {
    id: number;
    name: string;
    avatar_url: string | null;
    role: string | null;
    headline: string | null;
    bio: string | null;
};

export type CourseDetail = Omit<CourseCard, 'instructors'> & {
    description: string | null;
    access_duration_days: number | null;
    certificate_enabled: boolean;
    published_at: string | null;
    instructors: CourseInstructor[];
};

export type CatalogFilters = {
    q: string | null;
    kategori: string | null;
    harga: 'gratis' | 'berbayar' | null;
};

export type PageMeta = {
    title: string | null;
    description: string;
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
    links: PaginationLink[];
};
