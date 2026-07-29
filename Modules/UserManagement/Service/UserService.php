<?php

namespace Modules\UserManagement\Service;


use App\Service\BaseService;
use Illuminate\Database\Eloquent\Model;
use Modules\UserManagement\Lib\AdditionalDataForm;
use Modules\UserManagement\Repository\UserRepositoryInterface;
use Modules\UserManagement\Service\Interfaces\UserServiceInterface;

class UserService extends BaseService implements UserServiceInterface
{
    protected $userRepository;

    public function __construct(UserRepositoryInterface $userRepository)
    {
        parent::__construct($userRepository);
        $this->userRepository = $userRepository;
    }

    // Add your specific methods related to UserService here

    public function mergeUserAccount(array $data, string $id): ?Model {
        $user = $this->findOne(id: $id);
        $userType = $user->user_type;

        $identityImages = [];
        if (array_key_exists('identity_images', $data)) {
            foreach ($data['identity_images'] as $image) {
                $identityImages[] = fileUploader($userType . '/identity/', APPLICATION_IMAGE_FORMAT, $image);
            }
        }
        if (array_key_exists('other_documents', $data)) {
            $otherDocuments = [];
            foreach ($data['other_documents'] as $document) {
                $otherDocuments[] = fileUploader($userType . '/document/', AdditionalDataForm::uploadFormat($document), $document);
            }
        }
        $userData = [
            'full_name' => ($data['first_name'] . " " . ($data['last_name'] ?? '')) ?? $user->full_name,
            'user_level_id' => $user->user_level_id,
            'first_name' => $data['first_name'] ?? $user->first_name,
            'last_name' => $data['last_name'] ?? $user->last_name,
            'gender' => $data['gender'] ?? $user->gender,
            'email' => $data['email'] ?? $user->email,
            'phone' => $user->phone,
            'identification_number' => $data['identification_number'] ?? $user->identification_number,
            'identification_type' => $data['identification_type'] ?? $user->identification_type,
            'identification_image' => $identityImages ?? null,
            'other_documents' => $otherDocuments ?? null,
            'profile_image' => array_key_exists('profile_image', $data) ? fileUploader($userType . '/profile/', APPLICATION_IMAGE_FORMAT, $data['profile_image']) : null,
            'fcm_token' => $user->fcm_token,
            'phone_verified_at' => $user->phone_verified_at,
            'email_verified_at' => $user->email_verified_at,
            'loyalty_points' => $user->loyalty_points,
            'password' => array_key_exists('password', $data) ? bcrypt($data['password']) : null,
            'ref_code' => $user->ref_code,
            'user_type' => $user->user_type,
            'role_id' => $user->role_id,
            'remember_token' => $user->remember_token,
            'is_active' => $user->is_active,
            'logged_in_via' => 'manual',
            'current_language_key' => $user->current_language_key,
            'failed_attempt' => $user->failed_attempt,
            'is_temp_blocked' => $user->is_temp_blocked,
        ];

        return $this->userRepository->update(id: $user->id, data: $userData);
    }
}
