<?php

namespace App\Http\Controllers\Api\V1\Profiles;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AthleteCareerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->ensureAthlete($request);
        $user = $request->user();

        return response()->json(['data' => [
            'history' => $user->careerEntries()->orderByDesc('is_current')->orderByDesc('started_on')->get(),
            'achievements' => $user->achievements()->latest('achieved_on')->get(),
            'statistics' => $user->athleteStatistics()->orderByDesc('season')->orderBy('name')->get(),
        ]]);
    }

    public function storeHistory(Request $request): JsonResponse
    {
        $this->ensureAthlete($request);
        $data = $request->validate([
            'team_name' => ['required', 'string', 'max:160'],
            'role' => ['nullable', 'string', 'max:100'],
            'level' => ['nullable', 'string', 'max:100'],
            'started_on' => ['nullable', 'date'],
            'ended_on' => ['nullable', 'date', 'after_or_equal:started_on'],
            'is_current' => ['required', 'boolean'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
        if ($data['is_current']) $data['ended_on'] = null;

        return $this->created($request->user()->careerEntries()->create($data), 'Career entry added.');
    }

    public function storeAchievement(Request $request): JsonResponse
    {
        $this->ensureAthlete($request);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'issuer' => ['nullable', 'string', 'max:160'],
            'achieved_on' => ['nullable', 'date', 'before_or_equal:today'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        return $this->created($request->user()->achievements()->create($data), 'Achievement added.');
    }

    public function storeStatistic(Request $request): JsonResponse
    {
        $this->ensureAthlete($request);
        $data = $request->validate([
            'season' => ['required', 'string', 'max:40'],
            'competition' => ['nullable', 'string', 'max:160'],
            'name' => ['required', 'string', 'max:80'],
            'value' => ['required', 'numeric', 'between:-9999999999,9999999999'],
            'unit' => ['nullable', 'string', 'max:30'],
        ]);

        return $this->created($request->user()->athleteStatistics()->create($data), 'Statistic added.');
    }

    public function destroyHistory(Request $request, int $entry): JsonResponse
    {
        return $this->destroy($request, $request->user()->careerEntries()->findOrFail($entry));
    }

    public function destroyAchievement(Request $request, int $achievement): JsonResponse
    {
        return $this->destroy($request, $request->user()->achievements()->findOrFail($achievement));
    }

    public function destroyStatistic(Request $request, int $statistic): JsonResponse
    {
        return $this->destroy($request, $request->user()->athleteStatistics()->findOrFail($statistic));
    }

    private function ensureAthlete(Request $request): void
    {
        abort_unless($request->user()->hasRole('athlete'), 403, 'Only athlete accounts can manage career records.');
    }

    private function created(Model $model, string $message): JsonResponse
    {
        return response()->json(['message' => $message, 'data' => $model], 201);
    }

    private function destroy(Request $request, Model $model): JsonResponse
    {
        $this->ensureAthlete($request);
        $model->delete();

        return response()->json(['message' => 'Record removed.']);
    }
}
