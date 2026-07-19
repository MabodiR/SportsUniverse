<?php

namespace App\Domain\Moderation\Services;

use App\Domain\Moderation\Models\ModerationAction;
use App\Domain\Moderation\Models\Report;
use App\Events\NotificationRequested;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ModerationService
{
    public function media(User $moderator, Model $media, string $status, ?string $notes = null): Model
    {
        return DB::transaction(function () use ($moderator, $media, $status, $notes) {
            $previous = $media->moderation_status;
            $media->update(['moderation_status' => $status]);
            $this->log($moderator, $media, 'moderate_media', $previous, $status, $notes);
            NotificationRequested::dispatch($media->user_id, 'moderation', ['event' => 'media_moderated', 'media_id' => $media->public_id, 'status' => $status, 'notes' => $notes]);

            return $media;
        });
    }

    public function video(User $moderator, Model $video, string $status, ?string $notes = null): Model
    {
        return DB::transaction(function () use ($moderator, $video, $status, $notes) {
            $previous = $video->status;
            $video->update(['status' => $status]);
            $this->log($moderator, $video, 'moderate_video', $previous, $status, $notes);
            NotificationRequested::dispatch($video->user_id, 'moderation', ['event' => 'video_moderated', 'video_id' => $video->public_id, 'status' => $status, 'notes' => $notes]);

            return $video;
        });
    }

    public function resolve(User $moderator, Report $report, string $status, string $action, ?string $notes = null): Report
    {
        return DB::transaction(function () use ($moderator, $report, $status, $action, $notes) {
            $previous = $report->status;
            $report->update(['status' => $status, 'assigned_to_id' => $moderator->id, 'resolved_at' => in_array($status, ['resolved', 'dismissed'], true) ? now() : null]);
            $this->log($moderator, $report->reportable, $action, $previous, $status, $notes, $report);

            return $report;
        });
    }

    public function verify(User $moderator, User $user, bool $verified, ?string $notes = null): User
    {
        return DB::transaction(function () use ($moderator, $user, $verified, $notes) {
            $user->profile()->updateOrCreate([], ['verified_at' => $verified ? now() : null, 'verified_by_id' => $verified ? $moderator->id : null]);
            $this->log($moderator, $user, $verified ? 'verify_profile' : 'remove_verification', null, $verified ? 'verified' : 'unverified', $notes);
            NotificationRequested::dispatch($user->id, 'moderation', ['event' => $verified ? 'profile_verified' : 'profile_verification_removed', 'notes' => $notes]);

            return $user;
        });
    }

    public function userStatus(User $moderator, User $user, string $status, ?string $notes = null): User
    {
        return DB::transaction(function () use ($moderator, $user, $status, $notes) {
            $previous = $user->status;
            $user->update(['status' => $status]);
            $this->log($moderator, $user, 'change_user_status', $previous, $status, $notes);
            NotificationRequested::dispatch($user->id, 'moderation', ['event' => 'account_status_changed', 'status' => $status, 'notes' => $notes]);

            return $user;
        });
    }

    private function log(User $moderator, ?Model $target, string $action, ?string $previous, ?string $new, ?string $notes, ?Report $report = null): void
    {
        ModerationAction::create(['moderator_id' => $moderator->id, 'moderatable_type' => $target?->getMorphClass(), 'moderatable_id' => $target?->getKey(), 'report_id' => $report?->id, 'action' => $action, 'previous_status' => $previous, 'new_status' => $new, 'notes' => $notes]);
    }
}
