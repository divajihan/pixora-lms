import { Head, usePage } from '@inertiajs/react';
import { BookOpen, CalendarClock, ClipboardCheck, Users } from 'lucide-react';
import { DashboardPlaceholder } from '@/components/dashboard-placeholder';
import { dashboard } from '@/routes/instructor';

export default function InstructorDashboard() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Dasbor Instruktur" />
            <DashboardPlaceholder
                greeting={`Halo, ${auth.user.name}`}
                description="Dasbor instruktur: kursus yang Anda ajar, siswa, dan tugas masuk."
                items={[
                    {
                        title: 'Kursus saya',
                        description: 'Kursus yang ditugaskan kepada Anda.',
                        phase: 2,
                        icon: BookOpen,
                    },
                    {
                        title: 'Pembukaan lesson',
                        description:
                            'Atur jadwal buka lesson per kelas atau per siswa.',
                        phase: 4,
                        icon: CalendarClock,
                    },
                    {
                        title: 'Siswa & progres',
                        description: 'Lihat daftar siswa dan progres belajar.',
                        phase: 4,
                        icon: Users,
                    },
                    {
                        title: 'Antrean tugas',
                        description: 'Periksa tugas dan beri feedback.',
                        phase: 5,
                        icon: ClipboardCheck,
                    },
                ]}
            />
        </>
    );
}

InstructorDashboard.layout = {
    breadcrumbs: [{ title: 'Dasbor', href: dashboard() }],
};
