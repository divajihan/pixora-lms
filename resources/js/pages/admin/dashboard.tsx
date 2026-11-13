import { Head, usePage } from '@inertiajs/react';
import { BarChart3, BookOpen, Receipt, Settings, Users } from 'lucide-react';
import { DashboardPlaceholder } from '@/components/dashboard-placeholder';
import { dashboard } from '@/routes/admin';

export default function AdminDashboard() {
    const { auth } = usePage().props;
    const isStaff = !auth.roles.includes('admin');

    return (
        <>
            <Head title={isStaff ? 'Dasbor Staf' : 'Dasbor Admin'} />
            <DashboardPlaceholder
                greeting={`Halo, ${auth.user.name}`}
                description={
                    isStaff
                        ? 'Dasbor staf: kelola order, bantu siswa, dan moderasi.'
                        : 'Dasbor admin: kelola kursus, pengguna, order, dan pengaturan.'
                }
                items={[
                    {
                        title: 'Kursus',
                        description:
                            'Buat dan kelola kursus, modul, dan lesson.',
                        phase: 2,
                        icon: BookOpen,
                    },
                    {
                        title: 'Order & pembayaran',
                        description: 'Pantau order dan konfirmasi pembayaran.',
                        phase: 3,
                        icon: Receipt,
                    },
                    {
                        title: 'Pengguna',
                        description: 'Kelola akun siswa, instruktur, dan staf.',
                        phase: 7,
                        icon: Users,
                    },
                    {
                        title: 'Laporan',
                        description: 'Ringkasan penjualan dan progres siswa.',
                        phase: 7,
                        icon: BarChart3,
                    },
                    {
                        title: 'Pengaturan',
                        description: 'Branding, teks sertifikat, dan kontak.',
                        phase: 7,
                        icon: Settings,
                    },
                ]}
            />
        </>
    );
}

AdminDashboard.layout = {
    breadcrumbs: [{ title: 'Dasbor', href: dashboard() }],
};
