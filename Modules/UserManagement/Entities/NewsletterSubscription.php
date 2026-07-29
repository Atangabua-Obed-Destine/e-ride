<?php

namespace Modules\UserManagement\Entities;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// use Modules\BusinessManagement\Database\Factories\NewsletterSubscriptionFactory;

class NewsletterSubscription extends Model
{
    use HasFactory, HasUuid;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'email', 'status'
    ];

    // protected static function newFactory(): NewsletterSubscriptionFactory
    // {
    //     // return NewsletterSubscriptionFactory::new();
    // }
}
