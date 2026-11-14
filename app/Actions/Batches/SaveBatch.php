<?php

namespace App\Actions\Batches;

use App\Models\Batch;
use App\Models\Course;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates/updates a batch of a drip course.
 * - Once a batch has started its start date cannot change (it would move or
 *   re-lock lessons that students already have).
 * - Capacity cannot go below the seats already taken.
 */
class SaveBatch
{
    /**
     * @param  array{name: string, starts_on: string, ends_on: string, registration_closes_on: string|null, capacity: int|string|null, is_published: bool}  $data
     */
    public function handle(Course $course, ?Batch $batch, array $data): Batch
    {
        if (! $course->drip_enabled) {
            throw ValidationException::withMessages(['name' => 'Batch hanya untuk kursus dengan jadwal bertahap (drip).']);
        }

        return DB::transaction(function () use ($course, $batch, $data): Batch {
            if ($batch !== null) {
                /** @var Batch $batch */
                $batch = Batch::whereKey($batch->id)->lockForUpdate()->firstOrFail();

                if ($batch->hasStarted() && $batch->starts_on->toDateString() !== $data['starts_on']) {
                    throw ValidationException::withMessages([
                        'starts_on' => 'Batch sudah dimulai; tanggal mulai tidak dapat diubah karena akan mengubah jadwal lesson yang sudah terbuka.',
                    ]);
                }

                $taken = $batch->seatsTaken();

                if ($data['capacity'] !== null && (int) $data['capacity'] < $taken) {
                    throw ValidationException::withMessages(['capacity' => "Kuota tidak boleh kurang dari jumlah siswa saat ini ({$taken})."]);
                }
            }

            $batch ??= new Batch(['course_id' => $course->id]);
            $batch->fill([
                'name' => $data['name'],
                'starts_on' => $data['starts_on'],
                'ends_on' => $data['ends_on'],
                'registration_closes_on' => $data['registration_closes_on'],
                'capacity' => $data['capacity'] !== null ? (int) $data['capacity'] : null,
                'is_published' => $data['is_published'],
            ])->save();

            return $batch;
        });
    }

    /**
     * Soft delete, refused once students are in the batch (unpublish instead).
     */
    public function delete(Batch $batch): void
    {
        if ($batch->enrollments()->exists()) {
            throw ValidationException::withMessages([
                'batch' => 'Batch ini sudah memiliki siswa dan tidak dapat dihapus. Nonaktifkan publikasi agar tidak menerima pendaftar baru.',
            ]);
        }

        $batch->delete();
    }
}
