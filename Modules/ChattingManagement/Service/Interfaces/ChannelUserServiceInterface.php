<?php

namespace Modules\ChattingManagement\Service\Interfaces;

use App\Service\BaseServiceInterface;

interface ChannelUserServiceInterface extends BaseServiceInterface
{
public function sendMessageChannelUserupdate($data);
}
