<?php

namespace Modules\AuthManagement\Service;

use App\Service\BaseService;
use Carbon\Carbon;
use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
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

    public function isSocialLoginEnabled(string $medium): bool
    {
        $options = businessConfig(CUSTOMER . '_login_options', LOGIN_SETTINGS)?->value ?? [];
        $master = (bool)($options['social_media_login'] ?? 0);
        $selected = (bool)($options['social_login'][$medium] ?? 0);
        return $master && $selected;
    }

    public function getSocialProfile(string $medium, string $token, ?string $uniqueId): array
    {
        $client = new Client();
        if ($medium == 'google') {
            $res = $client->request('GET', 'https://www.googleapis.com/oauth2/v3/userinfo?access_token=' . $token);
            $data = json_decode($res->getBody()->getContents(), true);
            return ['email' => $data['email'] ?? null, 'name' => $data['name'] ?? ''];
        }
        if ($medium == 'facebook') {
            $res = $client->request('GET', 'https://graph.facebook.com/' . $uniqueId . '?access_token=' . $token . '&fields=name,email');
            $data = json_decode($res->getBody()->getContents(), true);
            return ['email' => $data['email'] ?? null, 'name' => $data['name'] ?? ''];
        }
        return $this->getAppleProfile($uniqueId);
    }

    public function revokeSocialToken(?string $refreshToken): void
    {
        if (!$refreshToken) {
            return;
        }
        try {
            $apple = $this->appleLoginConfig();
            if (empty($apple['service_file'])) {
                return;
            }
            Http::asForm()->post('https://appleid.apple.com/auth/revoke', [
                'client_id' => $apple['bundle_id'] ?? '',
                'client_secret' => $this->appleClientSecret($apple),
                'token' => $refreshToken,
                'token_type_hint' => 'refresh_token',
            ]);
        } catch (\Throwable $exception) {
        }
    }

    private function appleLoginConfig(): array
    {
        return businessConfig('apple_login', SOCIAL_LOGIN)?->value ?? [];
    }

    private function appleClientSecret(array $apple): string
    {
        $keyContent = Storage::disk('public')->get('social-login/' . ($apple['service_file'] ?? ''));
        return JWT::encode([
            'iss' => $apple['team_id'] ?? '',
            'iat' => time(),
            'exp' => time() + 3600,
            'aud' => 'https://appleid.apple.com',
            'sub' => $apple['bundle_id'] ?? '',
        ], $keyContent, 'ES256', $apple['client_secret'] ?? null);
    }

    private function getAppleProfile(string $authorizationCode): array
    {
        $apple = $this->appleLoginConfig();
        $clientSecret = $this->appleClientSecret($apple);

        $response = Http::asForm()->post('https://appleid.apple.com/auth/token', [
            'grant_type' => 'authorization_code',
            'code' => $authorizationCode,
            'client_id' => $apple['bundle_id'] ?? '',
            'client_secret' => $clientSecret,
        ]);

        $idToken = $response['id_token'] ?? null;
        if (!$idToken) {
            throw new \Exception('Apple token exchange failed');
        }

        $payload = json_decode(JWT::urlsafeB64Decode(explode('.', $idToken)[1] ?? ''), true) ?? [];
        return [
            'email' => $payload['email'] ?? null,
            'name' => '',
            'refresh_token' => $response['refresh_token'] ?? null,
        ];
    }
}
