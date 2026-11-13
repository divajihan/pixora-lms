<?php

namespace App\Actions\Courses;

use App\Enums\CourseStatus;
use App\Models\Course;
use App\Models\User;
use App\Support\UniqueSlug;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Creates or updates a course from validated admin form data, including the
 * thumbnail (public disk, random file name). Free courses always cost 0.
 */
class SaveCourse
{
    public const THUMBNAIL_DIRECTORY = 'course-thumbnails';

    /**
     * @param  array<string, mixed>  $data  Validated CourseRequest data.
     */
    public function handle(?Course $course, array $data, User $actor, ?UploadedFile $thumbnail = null, bool $removeThumbnail = false): Course
    {
        $course ??= new Course([
            'status' => CourseStatus::Draft,
            'created_by' => $actor->id,
        ]);

        $isFree = (bool) ($data['is_free'] ?? false);
        $oldThumbnail = $course->thumbnail_path;
        $newThumbnail = $this->storeThumbnail($thumbnail);

        try {
            DB::transaction(function () use ($course, $data, $isFree, $newThumbnail, $removeThumbnail): void {
                $course->fill([
                    'title' => $data['title'],
                    'slug' => $this->slug($course, $data['slug'] ?? null, $data['title']),
                    'summary' => $data['summary'] ?? null,
                    'description' => $data['description'] ?? null,
                    'category_id' => $data['category_id'] ?? null,
                    'is_free' => $isFree,
                    'price' => $isFree ? 0 : (int) $data['price'],
                    'drip_enabled' => (bool) ($data['drip_enabled'] ?? false),
                    'access_duration_days' => $data['access_duration_days'] ?? null,
                    'certificate_enabled' => (bool) ($data['certificate_enabled'] ?? false),
                ]);

                if ($newThumbnail !== null) {
                    $course->thumbnail_path = $newThumbnail;
                } elseif ($removeThumbnail) {
                    $course->thumbnail_path = null;
                }

                $course->save();
            });
        } catch (\Throwable $exception) {
            if ($newThumbnail !== null) {
                Storage::disk('public')->delete($newThumbnail);
            }

            throw $exception;
        }

        // Remove the replaced file only after the database change succeeded.
        if ($oldThumbnail !== null && $oldThumbnail !== $course->thumbnail_path) {
            Storage::disk('public')->delete($oldThumbnail);
        }

        return $course;
    }

    /**
     * Stores the upload under a random name (hashName) on the public disk.
     */
    private function storeThumbnail(?UploadedFile $thumbnail): ?string
    {
        if ($thumbnail === null) {
            return null;
        }

        $path = $thumbnail->store(self::THUMBNAIL_DIRECTORY, 'public');

        if ($path === false) {
            throw new \RuntimeException('Gambar sampul gagal disimpan.');
        }

        return $path;
    }

    private function slug(Course $course, ?string $requested, string $title): string
    {
        if (is_string($requested) && $requested !== '') {
            return $requested; // Uniqueness already validated by CourseRequest.
        }

        return UniqueSlug::make($title, fn (string $slug) => Course::withTrashed()
            ->where('slug', $slug)
            ->when($course->exists, fn ($query) => $query->whereKeyNot($course->id))
            ->exists());
    }
}
