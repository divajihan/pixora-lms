<?php

namespace App\Actions\Certificates;

use App\Models\CertificateSequence;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Takes the next certificate number of the year: {prefix}-{year}-{000001}.
 * Must run inside the caller's transaction: the sequence row stays locked
 * (FOR UPDATE) until commit, so concurrent claims never get the same number.
 * A rolled-back claim gives its number back (no gaps from failed claims).
 */
class NextCertificateNumber
{
    public function handle(CarbonInterface $issuedAt): string
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('NextCertificateNumber must run inside a transaction.');
        }

        $year = (int) $issuedAt->format('Y');
        $sequence = $this->lockedSequence($year);
        $sequence->increment('last_number');

        return sprintf('%s-%d-%06d', config('pixora.certificates.number_prefix'), $year, $sequence->last_number);
    }

    private function lockedSequence(int $year): CertificateSequence
    {
        $sequence = CertificateSequence::where('year', $year)->lockForUpdate()->first();

        if ($sequence !== null) {
            return $sequence;
        }

        try {
            // Savepoint: a concurrent insert of the same year must not abort the outer transaction.
            DB::transaction(fn () => CertificateSequence::create(['year' => $year, 'last_number' => 0]));
        } catch (UniqueConstraintViolationException) {
            // Another request created the row first; lock it below.
        }

        return CertificateSequence::where('year', $year)->lockForUpdate()->firstOrFail();
    }
}
