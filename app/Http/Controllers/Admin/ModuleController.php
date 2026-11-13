<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Curriculum\DeleteModule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ModuleRequest;
use App\Models\Course;
use App\Models\Module;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ModuleController extends Controller
{
    public function store(ModuleRequest $request, Course $course): RedirectResponse
    {
        $course->modules()->create([
            'title' => $request->validated('title'),
            'position' => (int) $course->modules()->max('position') + ($course->modules()->exists() ? 1 : 0),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Modul ditambahkan.']);

        return back();
    }

    public function update(ModuleRequest $request, Module $module): RedirectResponse
    {
        $module->update(['title' => $request->validated('title')]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Modul diperbarui.']);

        return back();
    }

    public function destroy(Module $module, DeleteModule $deleteModule): RedirectResponse
    {
        Gate::authorize('delete', $module);

        $deleteModule->handle($module);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Modul \"{$module->title}\" beserta lesson-nya dihapus."]);

        return back();
    }
}
