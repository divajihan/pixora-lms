import { Link, usePage } from '@inertiajs/react';
import { Menu } from 'lucide-react';
import type { ReactNode } from 'react';
import AppLogo from '@/components/app-logo';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { dashboardFor } from '@/lib/roles';
import { cn } from '@/lib/utils';
import { home, login, register } from '@/routes';
import { index as catalog } from '@/routes/courses';

const navItems = [
    { title: 'Beranda', href: home(), exact: true },
    { title: 'Katalog', href: catalog(), exact: false },
];

export default function PublicLayout({ children }: { children: ReactNode }) {
    const { auth, name } = usePage().props;
    const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();

    const isActive = (item: (typeof navItems)[number]) =>
        item.exact ? isCurrentUrl(item.href) : isCurrentOrParentUrl(item.href);

    const authButtons = auth.user ? (
        <Button asChild size="sm">
            <Link href={dashboardFor(auth.roles)}>Dasbor</Link>
        </Button>
    ) : (
        <>
            <Button asChild variant="ghost" size="sm">
                <Link href={login()}>Masuk</Link>
            </Button>
            <Button asChild size="sm">
                <Link href={register()}>Daftar</Link>
            </Button>
        </>
    );

    return (
        <div className="flex min-h-svh flex-col bg-background">
            <header className="sticky top-0 z-40 border-b bg-background/90 backdrop-blur supports-[backdrop-filter]:bg-background/75">
                <div className="mx-auto flex h-16 w-full max-w-6xl items-center gap-6 px-4 md:px-6">
                    <Link
                        href={home()}
                        className="flex items-center"
                        aria-label={`${name} — Beranda`}
                    >
                        <AppLogo />
                    </Link>

                    <nav
                        aria-label="Navigasi utama"
                        className="hidden items-center gap-1 md:flex"
                    >
                        {navItems.map((item) => (
                            <Link
                                key={item.title}
                                href={item.href}
                                className={cn(
                                    'rounded-md px-3 py-2 text-sm font-medium text-muted-foreground transition-colors hover:text-foreground',
                                    isActive(item) && 'text-foreground',
                                )}
                                aria-current={
                                    isActive(item) ? 'page' : undefined
                                }
                            >
                                {item.title}
                            </Link>
                        ))}
                    </nav>

                    <div className="ml-auto hidden items-center gap-2 md:flex">
                        {authButtons}
                    </div>

                    <Sheet>
                        <SheetTrigger asChild>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="ml-auto md:hidden"
                                aria-label="Buka menu"
                            >
                                <Menu className="size-5" />
                            </Button>
                        </SheetTrigger>
                        <SheetContent side="right" className="w-72">
                            <SheetHeader>
                                <SheetTitle className="text-left">
                                    Menu
                                </SheetTitle>
                            </SheetHeader>
                            <nav
                                aria-label="Navigasi utama"
                                className="flex flex-col gap-1 px-4"
                            >
                                {navItems.map((item) => (
                                    <Link
                                        key={item.title}
                                        href={item.href}
                                        className={cn(
                                            'rounded-md px-3 py-2 text-sm font-medium hover:bg-accent',
                                            isActive(item) && 'bg-accent',
                                        )}
                                    >
                                        {item.title}
                                    </Link>
                                ))}
                            </nav>
                            <div className="flex flex-col gap-2 px-4 pt-2 [&>a]:w-full">
                                {authButtons}
                            </div>
                        </SheetContent>
                    </Sheet>
                </div>
            </header>

            <main className="flex-1">{children}</main>

            <footer className="border-t">
                <div className="mx-auto flex w-full max-w-6xl flex-col gap-2 px-4 py-8 text-sm text-muted-foreground md:flex-row md:items-center md:justify-between md:px-6">
                    <p>
                        © {new Date().getFullYear()} {name}. Hak cipta
                        dilindungi.
                    </p>
                    <nav aria-label="Tautan footer" className="flex gap-4">
                        <Link
                            href={catalog()}
                            className="hover:text-foreground"
                        >
                            Katalog kursus
                        </Link>
                        {!auth.user && (
                            <Link
                                href={register()}
                                className="hover:text-foreground"
                            >
                                Daftar gratis
                            </Link>
                        )}
                    </nav>
                </div>
            </footer>
        </div>
    );
}
