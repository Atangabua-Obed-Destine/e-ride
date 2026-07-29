<?php

namespace Modules\BlogManagement\Service;


use App\Service\BaseService;
use Modules\BlogManagement\Repository\BlogCategoryRepositoryInterface;
use Modules\BlogManagement\Service\Interfaces\BlogCategoryServiceInterface;

class BlogCategoryService extends BaseService implements BlogCategoryServiceInterface
{
    protected $blogCategoryRepository;

    public function __construct(BlogCategoryRepositoryInterface $blogCategoryRepository)
    {
        parent::__construct($blogCategoryRepository);
        $this->blogCategoryRepository = $blogCategoryRepository;
    }
}
