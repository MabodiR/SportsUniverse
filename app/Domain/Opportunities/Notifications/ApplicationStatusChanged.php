<?php

namespace App\Domain\Opportunities\Notifications;

use App\Domain\Opportunities\Models\OpportunityApplication;
use Illuminate\Notifications\Notification;

class ApplicationStatusChanged extends Notification
{
    public function __construct(public OpportunityApplication $application) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['category' => 'opportunities', 'event' => 'opportunity_application_status', 'application_id' => $this->application->public_id, 'opportunity_id' => $this->application->opportunity->public_id, 'opportunity_title' => $this->application->opportunity->title, 'status' => $this->application->status];
    }
}
