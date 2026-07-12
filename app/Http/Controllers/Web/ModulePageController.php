<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ModulePageController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $key = (string) $request->route('module');
        $pages = config('sportuniverse_pages');

        abort_unless(isset($pages[$key]), 404);

        if (in_array($key, ['messages', 'message-requests'], true)) {
            return Inertia::render('Messages/Index', [
                'initialTab' => $key === 'message-requests' ? 'requests' : 'messages',
            ]);
        }

        if ($key === 'upload') {
            return Inertia::render('Upload/Index');
        }

        if ($key === 'sponsorship') {
            return Inertia::render('Promote/Index');
        }

        if ($key === 'profile') {
            return Inertia::render('Profile/Index');
        }

        if ($key === 'profile-edit') {
            return Inertia::render('Profile/Edit');
        }

        if ($key === 'explore') {
            return Inertia::render('Explore/Index');
        }

        if ($key === 'notifications') {
            return Inertia::render('Notifications/Index');
        }

        if ($key === 'opportunities') return Inertia::render('Opportunities/Index');
        if ($key === 'opportunity-create') return Inertia::render('Opportunities/Create');
        if ($key === 'saved') return Inertia::render('Saved/Index', ['videos' => $request->user()->belongsToMany(\App\Domain\Feed\Models\Video::class, 'saved_videos')->get()]);

        if ($key === 'saved') {
            return app(FeedController::class)->saved($request);
        }

        return Inertia::render('Workspace/ModulePage', [
            'page' => array_merge(['key' => $key], $pages[$key]),
        ]);
    }
}
