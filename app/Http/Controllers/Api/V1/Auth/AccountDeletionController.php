<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountDeletionController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => DB::table('account_deletion_requests')->where('user_id', $request->user()->id)->first()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'confirmation' => ['required', 'in:DELETE'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);
        $now = now();
        DB::table('account_deletion_requests')->updateOrInsert(
            ['user_id' => $request->user()->id],
            ['reason' => $data['reason'] ?? null, 'status' => 'pending', 'requested_at' => $now, 'scheduled_for' => $now->copy()->addDays(30), 'completed_at' => null, 'created_at' => $now, 'updated_at' => $now]
        );
        $request->user()->profile?->update(['is_public' => false, 'is_available' => false]);

        return response()->json(['message' => 'Account deletion requested. Your profile is now private and deletion is scheduled within 30 days.', 'data' => DB::table('account_deletion_requests')->where('user_id', $request->user()->id)->first()], 202);
    }

    public function destroy(Request $request): JsonResponse
    {
        $deleted = DB::table('account_deletion_requests')->where('user_id', $request->user()->id)->where('status', 'pending')->delete();
        abort_unless($deleted, 404, 'No pending account deletion request was found.');

        return response()->json(['message' => 'Account deletion request cancelled. You can make your profile public again in Settings.']);
    }
}
