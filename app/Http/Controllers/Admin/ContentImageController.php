<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Rules\AllowedImage;
use App\Services\Images\ImageNormalizer;
use App\Services\Images\ImageRejected;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Image upload for the rich text editor (lesson notes, assignment
 * instructions). courses.manage only (route middleware). The file is
 * re-encoded (EXIF/GPS removed, longest side limited), stored on the public
 * disk under a random name, and its root-relative URL returned for the
 * editor; HtmlSanitizer keeps only images from this directory.
 */
class ContentImageController extends Controller
{
    public function store(Request $request, ImageNormalizer $normalizer): JsonResponse
    {
        $maxKb = (int) config('pixora.content_images.max_kb');

        $request->validate(
            ['image' => ['required', 'file', 'max:'.$maxKb, new AllowedImage]],
            ['image.max' => 'Ukuran gambar maksimal '.intdiv($maxKb, 1024).' MB.'],
            ['image' => 'gambar'],
        );

        try {
            $image = $normalizer->normalize(
                (string) $request->file('image')?->getRealPath(),
                (int) config('pixora.content_images.max_dimension'),
            );
        } catch (ImageRejected $exception) {
            throw ValidationException::withMessages(['image' => $exception->getMessage()]);
        }

        $path = config('pixora.content_images.directory').'/'.Str::random(40).'.'.$image->extension;
        Storage::disk('public')->put($path, $image->contents);

        return response()->json([
            'url' => Storage::disk('public')->url($path),
            'width' => $image->width,
            'height' => $image->height,
        ], 201);
    }
}
