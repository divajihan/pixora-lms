export type OrderStatusValue =
    | 'pending'
    | 'paid'
    | 'failed'
    | 'expired'
    | 'refunded';

export type OrderItemData = {
    id: number;
    course_id: number;
    title: string;
    price: number;
    course_slug: string | null;
};

export type PaymentData = {
    id: number;
    gateway: string;
    external_id: string;
    payment_type: string | null;
    gross_amount: number;
    status: string;
    status_label: string;
    raw_status: string | null;
    note: string | null;
    admin_name: string | null;
    paid_at: string | null;
    created_at: string | null;
    updated_at: string | null;
};

export type OrderEnrollmentData = {
    id: number;
    course_id: number;
    status: string;
    status_label: string;
    expires_at: string | null;
    is_accessible: boolean;
};

export type OrderData = {
    id: number;
    order_number: string;
    status: OrderStatusValue;
    status_label: string;
    subtotal: number;
    discount: number;
    total: number;
    is_payable: boolean;
    created_at: string | null;
    paid_at: string | null;
    expires_at: string | null;
    customer?: { name: string; email: string };
    items?: OrderItemData[];
    payments?: PaymentData[];
    enrollments?: OrderEnrollmentData[];
    has_access?: boolean;
};

export type PaymentClient = {
    driver: 'midtrans' | 'fake';
    script_url: string | null;
    client_key: string | null;
    token: string | null;
    redirect_url: string | null;
};

export type PurchaseInfo = {
    state: 'enrolled' | 'pending_order' | 'buy' | 'free' | 'not_student';
    price: number;
    is_free: boolean;
    checkout_url: string;
    learn_url: string | null;
    order_url: string | null;
};
