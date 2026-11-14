import { Head, usePage } from '@inertiajs/react';
import { Award, BookOpen, ClipboardList, Receipt } from 'lucide-react';
import { DashboardPlaceholder } from '@/components/dashboard-placeholder';
import { dashboard } from '@/routes/student';
import { index as myCourses } from '@/routes/student/courses';
import { index as myOrders } from '@/routes/student/orders';

export default function StudentDashboard() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Dasbor Siswa" />
            <DashboardPlaceholder
                greeting={`Halo, ${auth.user.name}`}
                description="Kursus yang Anda ikuti akan muncul di sini."
                items={[
                    {
                        title: 'Kursus saya',
                        description: 'Kursus yang Anda ikuti.',
                        icon: BookOpen,
                        href: myCourses(),
                    },
                    {
                        title: 'Tugas saya',
                        description: 'Kumpulkan tugas dan lihat feedback.',
                        phase: 5,
                        icon: ClipboardList,
                    },
                    {
                        title: 'Riwayat pesanan',
                        description: 'Status pembayaran dan invoice.',
                        icon: Receipt,
                        href: myOrders(),
                    },
                    {
                        title: 'Sertifikat',
                        description: 'Unduh sertifikat kursus yang selesai.',
                        phase: 6,
                        icon: Award,
                    },
                ]}
            />
        </>
    );
}

StudentDashboard.layout = {
    breadcrumbs: [{ title: 'Dasbor', href: dashboard() }],
};
