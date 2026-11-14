import { Form, Link, usePage } from '@inertiajs/react';
import { Clock, PlayCircle } from 'lucide-react';
import { useState } from 'react';
import { BatchPicker } from '@/components/catalog/batch-picker';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { formatRupiah } from '@/lib/format';
import { free } from '@/routes/checkout';
import type { PurchaseInfo } from '@/types/orders';

/**
 * Call-to-action on the course page. The state is computed on the server
 * (PurchaseState); buying always goes through server-side checkout, which
 * re-validates the chosen batch.
 */
export function PurchaseCta({
    purchase,
    slug,
}: {
    purchase: PurchaseInfo;
    slug: string;
}) {
    const { auth } = usePage().props;
    const needsBatch = purchase.batches.length > 0 || purchase.requires_batch;
    const firstOpen =
        purchase.batches.find((batch) => batch.is_open)?.id ?? null;
    const [batchId, setBatchId] = useState<number | null>(firstOpen);

    const checkoutUrl = batchId
        ? `${purchase.checkout_url}?batch=${batchId}`
        : purchase.checkout_url;
    const blocked = needsBatch && batchId === null;

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

        default:
            return (
                <div className="flex flex-col gap-3">
                    {needsBatch && (
                        <BatchPicker
                            batches={purchase.batches}
                            value={batchId}
                            onChange={setBatchId}
                        />
                    )}

                    {purchase.state === 'free' && auth.user ? (
                        <Form
                            {...free.form(slug)}
                            className="flex flex-col gap-2"
                        >
                            {({ processing, errors }) => (
                                <>
                                    {batchId && (
                                        <input
                                            type="hidden"
                                            name="batch_id"
                                            value={batchId}
                                        />
                                    )}
                                    <Button
                                        type="submit"
                                        size="lg"
                                        disabled={processing || blocked}
                                    >
                                        {processing && <Spinner />}
                                        Ambil gratis
                                    </Button>
                                    <InputError
                                        message={
                                            errors.course ?? errors.batch_id
                                        }
                                    />
                                </>
                            )}
                        </Form>
                    ) : blocked ? (
                        <Button size="lg" disabled>
                            {purchase.is_free
                                ? 'Ambil gratis'
                                : `Beli ${formatRupiah(purchase.price)}`}
                        </Button>
                    ) : (
                        <Button asChild size="lg">
                            <Link href={checkoutUrl}>
                                {purchase.is_free
                                    ? 'Ambil gratis'
                                    : `Beli ${formatRupiah(purchase.price)}`}
                            </Link>
                        </Button>
                    )}
                </div>
            );
    }
}
