<?php

namespace App\Actions\Certificates;

use App\Models\Certificate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Admin/staff certificate list: search (number, recipient name, course title,
 * verification code), filters (status aktif/dicabut, course, batch), newest
 * first. Relations are eager loaded (fixed query count per page).
 */
class ListAdminCertificates
{
    /**
     * @param  array{q?: string|null, status?: string|null, course?: int|null, batch?: int|null}  $filters
     * @return LengthAwarePaginator<int, Certificate>
     */
    public function handle(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $search = trim((string) ($filters['q'] ?? ''));

        return Certificate::query()
            ->with(['user:id,name,email', 'issuer:id,name', 'revoker:id,name'])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
                $query->where(fn (Builder $query) => $query
                    ->whereRaw("certificate_number LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("recipient_name LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("course_title LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("verification_code LIKE ? ESCAPE '!'", [$like]));
            })
            ->when(($filters['status'] ?? null) === 'aktif', fn (Builder $query) => $query->whereNull('revoked_at'))
            ->when(($filters['status'] ?? null) === 'dicabut', fn (Builder $query) => $query->whereNotNull('revoked_at'))
            ->when($filters['course'] ?? null, fn (Builder $query, int $course) => $query->where('course_id', $course))
            ->when($filters['batch'] ?? null, fn (Builder $query, int $batch) => $query->where('batch_id', $batch))
            ->latest('issued_at')
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }
}
