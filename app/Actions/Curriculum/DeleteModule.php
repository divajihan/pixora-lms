<?php

namespace App\Actions\Curriculum;

use App\Models\Module;
use Illuminate\Support\Facades\DB;

/**
 * Soft-deletes a module together with its lessons (history is kept so
 * progress/submissions from later phases are not broken).
 */
class DeleteModule
{
    public function handle(Module $module): void
    {
        DB::transaction(function () use ($module): void {
            $module->lessons()->get()->each->delete();
            $module->delete();
        });
    }
}
