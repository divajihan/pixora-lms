<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Batches\SaveBatch;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BatchRequest;
use App\Http\Resources\BatchResource;
use App\Models\Batch;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin batch management (permission courses.manage). Drip courses only.
 */
class BatchController extends Controller
{
    public function index(Course $course): Response
    {
        Gate::authorize('update', $course);

        $batches = $course->batches()->withSeatsTaken()->withCount('enrollments')->get();

        return Inertia::render('admin/courses/batches', [
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
                'slug' => $course->slug,
                'status' => $course->status->value,
                'drip_enabled' => $course->drip_enabled,
            ],
            'batches' => BatchResource::collection($batches)->resolve(),
        ]);
    }

    public function store(BatchRequest $request, Course $course, SaveBatch $save): RedirectResponse
    {
        /** @var array{name: string, starts_on: string, ends_on: string, registration_closes_on: string|null, capacity: int|string|null, is_published: bool} $data */
        $data = $request->validated();
        $save->handle($course, null, $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Batch ditambahkan.']);

        return back();
    }

    public function update(BatchRequest $request, Batch $batch, SaveBatch $save): RedirectResponse
    {
        /** @var array{name: string, starts_on: string, ends_on: string, registration_closes_on: string|null, capacity: int|string|null, is_published: bool} $data */
        $data = $request->validated();
        $save->handle($batch->course, $batch, $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Batch diperbarui.']);

        return back();
    }

    public function togglePublish(Batch $batch): RedirectResponse
    {
        Gate::authorize('update', $batch->course);

        $batch->update(['is_published' => ! $batch->is_published]);

        Inertia::flash('toast', ['type' => 'success', 'message' => $batch->is_published ? 'Batch dipublikasikan.' : 'Publikasi batch dihentikan.']);

        return back();
    }

    public function destroy(Batch $batch, SaveBatch $save): RedirectResponse
    {
        Gate::authorize('update', $batch->course);

        $save->delete($batch);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Batch \"{$batch->name}\" dihapus."]);

        return back();
    }
}
