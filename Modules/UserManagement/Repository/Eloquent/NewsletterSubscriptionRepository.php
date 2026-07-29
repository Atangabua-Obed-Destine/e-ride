<?php

namespace Modules\UserManagement\Repository\Eloquent;

use App\Repository\Eloquent\BaseRepository;
use Modules\BusinessManagement\Repository\SupportSavedReplyRepositoryInterface;
use Modules\UserManagement\Entities\NewsletterSubscription;

class NewsletterSubscriptionRepository extends BaseRepository implements SupportSavedReplyRepositoryInterface
{
    public function __construct(NewsletterSubscription $model)
    {
        parent::__construct($model);
    }
}
