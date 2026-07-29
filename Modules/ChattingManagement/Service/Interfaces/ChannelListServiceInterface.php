<?php

namespace Modules\ChattingManagement\Service\Interfaces;

use App\Service\BaseServiceInterface;
use Illuminate\Database\Eloquent\Model;

interface ChannelListServiceInterface extends BaseServiceInterface
{
    public function createChannelWithChannelUser(array $data): ?Model;

    public function createChannelWithAdmin(array $data): ?Model;
}
