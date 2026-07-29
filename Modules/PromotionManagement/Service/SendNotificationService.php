<?php

namespace Modules\PromotionManagement\Service;

use App\Service\BaseService;
use Illuminate\Database\Eloquent\Model;
use Modules\PromotionManagement\Repository\SendNotificationRepositoryInterface;


class SendNotificationService extends BaseService implements Interfaces\SendNotificationServiceInterface
{
    protected $sendNotificationRepository;

    public function __construct(SendNotificationRepositoryInterface $sendNotificationRepository)
    {
        parent::__construct($sendNotificationRepository);
        $this->sendNotificationRepository = $sendNotificationRepository;
    }

    public function create(array $data): ?Model
    {
        $storeData = [
            'name'=>$data['name'],
            'description'=>$data['description'],
            'targeted_users'=>$data['targeted_users'],
            'image' => array_key_exists('image', $data) ? fileUploader('push-notification/', APPLICATION_IMAGE_FORMAT, $data['image'] ) : null,
        ];
        $model = $this->sendNotificationRepository->create(data: $storeData);
        $topics = [];
        if (in_array('customers', $data['targeted_users']))
        {
            $topics = array_merge($topics, ['customers_send_notification']);
        }
        if (in_array('drivers', $data['targeted_users']))
        {
            $topics = array_merge($topics, ['drivers_send_notification']);
        }
        foreach ($topics as $topic) {
            sendTopicNotification(topic: $topic, title: $data['name'], description: $data['description'], image: $storeData['image'] ?? null, type: 'send_notification', status: 1);
        }

        return $model;
    }

    public function update(int|string $id, array $data = []): ?Model
    {
        $model = $this->findOne(id: $id);
        $updateData = [
            'name'=> $data['name'],
            'description'=> $data['description'],
            'targeted_users'=> $data['targeted_users'],
        ];
        if (array_key_exists('image', $data)) {
            $updateData = array_merge($updateData,[
                'image'=> fileUploader('push-notification/', APPLICATION_IMAGE_FORMAT, $data['image'], $model->image),
            ]);
        } else if (!array_key_exists('old_image', $data))
        {
            $updateData = array_merge($updateData,[ 'image' => null]);
        }

        $model =$this->sendNotificationRepository->update(id: $id, data: $updateData);
        if (array_key_exists('update_and_resend', $data))
        {
            $topics = [];
            if (in_array('customers', $data['targeted_users']))
            {
                $topics = array_merge($topics, ['customers_send_notification']);
            }
            if (in_array('drivers', $data['targeted_users']))
            {
                $topics = array_merge($topics, ['drivers_send_notification']);
            }
            foreach ($topics as $topic) {
                sendTopicNotification(topic: $topic, title: $data['name'], description: $data['description'] ?? null, image: $model->image ?? null, type: 'send_notification', status: $model->is_active);
            }
        }
        return $model;
    }
}
