import { Head, usePage } from '@inertiajs/react';
import { Award, BookOpen, ClipboardList, Receipt } from 'lucide-react';
import { DashboardPlaceholder } from '@/components/dashboard-placeholder';
import { dashboard } from '@/routes/student';

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
                        description: 'Lanjutkan belajar dari lesson terakhir.',
                        phase: 4,
                        icon: BookOpen,
                    },
                    {
                        title: 'Tugas saya',
                        description: 'Kumpulkan tugas dan lihat feedback.',
                        phase: 5,
                        icon: ClipboardList,
                    },
                    {
                        title: 'Riwayat order',
                        description: 'Status pembayaran kursus yang dibeli.',
                        phase: 3,
                        icon: Receipt,
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
