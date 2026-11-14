<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Removes files of deleted/replaced attachments. Dispatched with
 * afterCommit() inside the transaction that deletes the rows, so a rolled
 * back change never loses files and committed changes leave no orphans.
 */
class DeleteStoredFiles implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  list<string>  $paths
     */
    public function __construct(public string $disk, public array $paths) {}

    public function handle(): void
    {
        if ($this->paths !== []) {
            Storage::disk($this->disk)->delete($this->paths);
        }
    }
}
