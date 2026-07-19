<?php

namespace App\Domain\Subscriptions\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    protected $fillable = ['name', 'slug', 'tagline', 'description', 'monthly_price_cents', 'annual_price_cents', 'features', 'limits', 'accent', 'is_featured', 'is_active', 'sort_order'];
    protected function casts(): array { return ['features' => 'array', 'limits' => 'array', 'is_featured' => 'boolean', 'is_active' => 'boolean']; }
    public function subscriptions(): HasMany { return $this->hasMany(Subscription::class); }
    public function payments(): HasMany { return $this->hasMany(SubscriptionPayment::class); }
}
