<?php

namespace App\Http\Controllers\Instructor;

use App\Actions\Assignments\ListSubmissionsToReview;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ListSubmissionsToReview $toReview): Response
    {
        /** @var User $user */
        $user = $request->user();
        $queue = $toReview->handle($user);

        return Inertia::render('instructor/dashboard', [
            'toReview' => [
                'count' => $queue->count(),
                'items' => $queue->take(5)->values(),
            ],
        ]);
    }
}
