<?php

namespace App\Filament\Resources\Reports\Pages;

use App\Domain\Feed\Models\Video;
use App\Domain\Moderation\Services\ModerationService;
use App\Filament\Resources\Reports\ReportResource;
use App\Filament\Resources\Videos\VideoResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewReport extends ViewRecord
{
    protected static string $resource = ReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewPost')
                ->label('Open reported post')
                ->icon('heroicon-o-play')
                ->url(fn (): ?string => $this->record->reportable instanceof Video
                    ? VideoResource::getUrl('view', ['record' => $this->record->reportable])
                    : null)
                ->visible(fn (): bool => $this->record->reportable instanceof Video),
            Action::make('review')
                ->label('Start review')
                ->color('warning')
                ->visible(fn (): bool => $this->record->status === 'open')
                ->action(fn () => app(ModerationService::class)->resolve(auth()->user(), $this->record, 'reviewing', 'review_report')),
            Action::make('resolve')
                ->label('Resolve')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool => in_array($this->record->status, ['open', 'reviewing'], true))
                ->action(fn () => app(ModerationService::class)->resolve(auth()->user(), $this->record, 'resolved', 'resolve_report')),
            Action::make('dismiss')
                ->label('Dismiss')
                ->color('gray')
                ->requiresConfirmation()
                ->visible(fn (): bool => in_array($this->record->status, ['open', 'reviewing'], true))
                ->action(fn () => app(ModerationService::class)->resolve(auth()->user(), $this->record, 'dismissed', 'dismiss_report')),
        ];
    }
}
