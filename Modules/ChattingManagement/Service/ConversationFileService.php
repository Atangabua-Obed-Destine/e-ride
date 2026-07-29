<?php

namespace Modules\ChattingManagement\Service;

use App\Service\BaseService;
use Modules\ChattingManagement\Repository\ConversationFileRepositoryInterface;
use Modules\ChattingManagement\Service\Interfaces\ConversationFileServiceInterface;

class ConversationFileService extends BaseService implements Interfaces\ConversationFileServiceInterface
{
    public function __construct(ConversationFileRepositoryInterface $baseRepository)
    {
        parent::__construct($baseRepository);
    }
}
