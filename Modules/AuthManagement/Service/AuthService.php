<?php

namespace Modules\AuthManagement\Service;

use App\Service\BaseService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Modules\BusinessManagement\Repository\SettingRepositoryInterface;
use Modules\Gateways\Traits\SmsGateway;
use Modules\UserManagement\Repository\OtpVerificationRepositoryInterface;
use Modules\UserManagement\Repository\UserRepositoryInterface;

class AuthService extends BaseService implements Interfaces\AuthServiceInterface
{
    use SmsGateway;

    protected $userRepository;
    protected $otpVerificationRepository;
    protected $settingRepository;

    public function __construct(UserRepositoryInterface $userRepository, OtpVerificationRepositoryInterface $otpVerificationRepository, SettingRepositoryInterface $settingRepository)
    {
        parent::__construct($userRepository);
        $this->userRepository = $userRepository;
        $this->otpVerificationRepository = $otpVerificationRepository;
        $this->settingRepository = $settingRepository;
    }

    public function checkClientRoute($request)
    {
        $route = str_contains($request->route()?->getPrefix(), 'customer');
        if ($route) {
            $user = $this->userRepository->findOneBy(criteria: ['phone' => $request->phone_or_email, 'user_type' => CUSTOMER]);
        } else {
            $user = $this->userRepository->findOneBy(criteria: ['phone' => $request->phone_or_email, 'user_type' => DRIVER]);
        }
        return $user;
    }

    private function generateOtp($user, $otp)
    {
        $expires_at = env('APP_MODE') == 'live' ? 3 : 1000;
        $attributes = [
            'phone_or_email' => $user->phone,
            'otp' => $otp,
            'expires_at' => Carbon::now()->addMinutes($expires_at),
        ];
        $verification = $this->otpVerificationRepository->findOneBy(['phone_or_email' => $user->phone]);
        if ($verification) {
            $verification->delete();
        }
        $this->otpVerificationRepository->create(data: $attributes);
        return $otp;
    }

    public function updateLoginUser(string|int $id, array $data): ?Model
    {
        return $this->userRepository->update(id: $id, data: $data);
    }


    public function sendOtpToClient($user, $type = null)
    {
        if ($type == 'trip') {
            $otp = env('APP_MODE') == 'live' ? rand(1000, 9999) : '0000';
            if (self::send($user->phone, $otp) == "not_found") {
                return $this->generateOtp($user, '0000');
            }
            return $this->generateOtp($user, $otp);
        }
        $dataValues = $this->settingRepository->getBy(criteria: ['settings_type' => SMS_CONFIG]);
        if ($dataValues->where('live_values.status', 1)->isNotEmpty() && env('APP_MODE') === 'live') {
            $otp = rand(100000, 999999);
        } else {
            $otp = '000000';
        }

        if (self::send($user->phone, $otp) == "not_found") {
            return $this->generateOtp($user, '000000');
        }
        return $this->generateOtp($user, $otp);

    }
}
