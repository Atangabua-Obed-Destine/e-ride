<?php

namespace Modules\BlogManagement\Service;


use App\Service\BaseService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\BlogManagement\Repository\BlogSettingRepositoryInterface;
use Modules\BlogManagement\Service\Interfaces\BlogSettingServiceInterface;

class BlogSettingService extends BaseService implements BlogSettingServiceInterface
{
    protected $blogSettingRepository;

    public function __construct(BlogSettingRepositoryInterface $blogSettingRepository)
    {
        parent::__construct($blogSettingRepository);
        $this->blogSettingRepository = $blogSettingRepository;
    }

    public function updateAppContents(array $data): void
    {
        $criteria = ['key_name' => $data['key_name'], 'settings_type' => APP_DOWNLOAD_SETUP];
        $appDownloadSetupData = $this->blogSettingRepository->findOneBy(criteria: $criteria);
        $value = ['title' => $data['title'], 'subtitle' => $data['subtitle']];

        if (array_key_exists('image', $data)) {
            $fileName = fileUploader('blog/setting/app/', APPLICATION_IMAGE_FORMAT, $data['image'], $appDownloadSetupData->value['image'] ?? '');
            $value['image'] = $fileName;
        } else {
            $value['image'] = $appDownloadSetupData->value['image'] ?? '';
        }

        $value['play_store_status'] = array_key_exists('play_store_status', $data) ? 1 : 0;
        $value['apple_store_status'] = array_key_exists('apple_store_status', $data) ? 1 : 0;
        $appContents = ['key_name' => $data['key_name'], 'settings_type' => APP_DOWNLOAD_SETUP, 'value' => $value];

        if ($appDownloadSetupData) {
            $this->blogSettingRepository->update(id: $appDownloadSetupData->id, data: $appContents);
        } else {
            $this->blogSettingRepository->create(data: $appContents);
        }
    }
}
