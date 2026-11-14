import { Head, usePage } from '@inertiajs/react';
import { BarChart3, BookOpen, Receipt, Settings, Users } from 'lucide-react';
import { DashboardPlaceholder } from '@/components/dashboard-placeholder';
import { dashboard } from '@/routes/admin';
import { index as adminCourses } from '@/routes/admin/courses';
import { index as adminUsers } from '@/routes/admin/users';

export default function AdminDashboard() {
    const { auth } = usePage().props;
    const isStaff = !auth.roles.includes('admin');
    const canManageCourses = auth.permissions.includes('courses.manage');
    const canManageUsers = auth.permissions.includes('users.manage');

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
                        icon: BookOpen,
                        ...(canManageCourses
                            ? { href: adminCourses() }
                            : { phase: 2 }),
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
                        icon: Users,
                        ...(canManageUsers
                            ? { href: adminUsers() }
                            : { phase: 7 }),
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
