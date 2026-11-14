import { Form, Link, usePage } from '@inertiajs/react';
import { Clock, PlayCircle } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { formatRupiah } from '@/lib/format';
import { free } from '@/routes/checkout';
import type { PurchaseInfo } from '@/types/orders';

/**
 * Call-to-action on the course page. The state is computed on the server
 * (PurchaseState); buying always goes through server-side checkout.
 */
export function PurchaseCta({
    purchase,
    slug,
}: {
    purchase: PurchaseInfo;
    slug: string;
}) {
    const { auth } = usePage().props;

    switch (purchase.state) {
        case 'enrolled':
            return (
                <Button asChild size="lg">
                    <Link href={purchase.learn_url ?? '#'}>
                        <PlayCircle />
                        Lanjut belajar
                    </Link>
                </Button>
            );

        case 'pending_order':
            return (
                <div className="flex flex-col gap-2">
                    <Button asChild size="lg">
                        <Link href={purchase.order_url ?? '#'}>
                            <Clock />
                            Lanjutkan pembayaran
                        </Link>
                    </Button>
                    <p className="text-center text-xs text-muted-foreground">
                        Anda memiliki pesanan yang menunggu pembayaran.
                    </p>
                </div>
            );

        case 'not_student':
            return (
                <div className="flex flex-col gap-2">
                    <Button size="lg" disabled aria-describedby="purchase-note">
                        {purchase.is_free
                            ? 'Ambil gratis'
                            : `Beli ${formatRupiah(purchase.price)}`}
                    </Button>
                    <p
                        id="purchase-note"
                        className="text-center text-xs text-muted-foreground"
                    >
                        Pembelian hanya untuk akun siswa.
                    </p>
                </div>
            );

        case 'free':
            // Signed-in students enroll right here; guests go to login first (via checkout).
            return auth.user ? (
                <Form {...free.form(slug)} className="flex flex-col gap-2">
                    {({ processing, errors }) => (
                        <>
                            <Button
                                type="submit"
                                size="lg"
                                disabled={processing}
                            >
                                {processing && <Spinner />}
                                Ambil gratis
                            </Button>
                            <InputError message={errors.course} />
                        </>
                    )}
                </Form>
            ) : (
                <Button asChild size="lg">
                    <Link href={purchase.checkout_url}>Ambil gratis</Link>
                </Button>
            );

        default:
            return (
                <Button asChild size="lg">
                    <Link href={purchase.checkout_url}>
                        Beli {formatRupiah(purchase.price)}
                    </Link>
                </Button>
            );
    }
}
