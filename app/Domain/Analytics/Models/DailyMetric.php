<?php

namespace App\Domain\Analytics\Models;

use Illuminate\Database\Eloquent\Model;

class DailyMetric extends Model
{
    protected $table = 'analytics_daily_metrics';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['metric_date' => 'date', 'metadata' => 'array', 'value' => 'integer'];
    }
}
