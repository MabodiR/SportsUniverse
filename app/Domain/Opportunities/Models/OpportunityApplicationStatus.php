<?php

namespace App\Domain\Opportunities\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpportunityApplicationStatus extends Model
{
    protected $table = 'opportunity_application_status_history';
    protected $guarded = ['id'];

    public function application(): BelongsTo
    {
        return $this->belongsTo(OpportunityApplication::class, 'opportunity_application_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_id');
    }
}
