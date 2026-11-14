<?php

namespace App\Actions\Assignments;

use App\Jobs\DeleteStoredFiles;
use App\Models\Attachment;
use App\Models\User;
use App\Services\Images\ImageNormalizer;
use App\Services\Images\ImageRejected;
use App\Services\Images\NormalizedImage;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Normalises uploaded images (ImageNormalizer) and writes them to the private
 * assignments disk under random names, then runs the database work. If that
 * work fails, the new files are deleted again (no orphans).
 *
 * Stored metadata never includes the client's path: only the base file name.
 */
class StoreImages
{
    public function __construct(private readonly ImageNormalizer $normalizer) {}

    /**
     * @template T
     *
     * @param  list<UploadedFile>  $files
     * @param  Closure(list<array<string, mixed>>): T  $persist  receives attachment attributes
     * @return T
     */
    public function store(array $files, string $directory, string $field, User $uploader, Closure $persist): mixed
    {
        $disk = (string) config('pixora.assignments.disk');
        $written = [];
        $rows = [];

        try {
            foreach ($files as $index => $file) {
                $image = $this->normalize($file, "{$field}.{$index}");
                $name = Str::random(40);
                $path = "{$directory}/{$name}.{$image->extension}";
                $thumbnailPath = "{$directory}/{$name}_thumb.{$image->extension}";

                Storage::disk($disk)->put($path, $image->contents);
                $written[] = $path;
                Storage::disk($disk)->put($thumbnailPath, $image->thumbnail);
                $written[] = $thumbnailPath;

                $rows[] = [
                    'disk' => $disk,
                    'path' => $path,
                    'thumbnail_path' => $thumbnailPath,
                    'original_name' => self::clientName($file),
                    'mime' => $image->mime,
                    'size' => strlen($image->contents),
                    'width' => $image->width,
                    'height' => $image->height,
                    'uploaded_by' => $uploader->id,
                ];
            }

            return $persist($rows);
        } catch (Throwable $exception) {
            if ($written !== []) {
                Storage::disk($disk)->delete($written);
            }

            throw $exception;
        }
    }

    /**
     * Deletes attachment rows and queues their files for removal after commit.
     *
     * @param  iterable<Attachment>  $attachments
     */
    public function delete(iterable $attachments): void
    {
        $byDisk = [];

        foreach ($attachments as $attachment) {
            $byDisk[$attachment->disk] = [...($byDisk[$attachment->disk] ?? []), ...$attachment->storedPaths()];
            $attachment->delete();
        }

        foreach ($byDisk as $disk => $paths) {
            DeleteStoredFiles::dispatch($disk, $paths)->afterCommit();
        }
    }

    /**
     * @template TOwner of Model
     *
     * @param  MorphMany<Attachment, TOwner>  $relation
     * @param  list<array<string, mixed>>  $rows
     */
    public static function attach(MorphMany $relation, array $rows): void
    {
        foreach ($rows as $row) {
            $relation->create($row);
        }
    }

    private function normalize(UploadedFile $file, string $field): NormalizedImage
    {
        try {
            return $this->normalizer->normalize((string) $file->getRealPath());
        } catch (ImageRejected $exception) {
            throw ValidationException::withMessages([$field => $exception->getMessage()]);
        }
    }

    /**
     * Base name only, without any directory part, trimmed to 200 characters.
     */
    private static function clientName(UploadedFile $file): string
    {
        $name = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';

        return Str::limit($name !== '' ? $name : 'gambar', 200, '');
    }
}
