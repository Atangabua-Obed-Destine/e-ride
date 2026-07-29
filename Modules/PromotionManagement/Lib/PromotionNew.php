<?php


use Illuminate\Support\Facades\Http;
use Modules\UserManagement\Entities\AppNotification;
use Illuminate\Support\Facades\Cache;

if (!function_exists('sendDeviceNotification')) {
    function sendDeviceNotification($fcm_token, $title, $description, $status, $image = null, $ride_request_id = null, $type = null, $notification_type = null, $action = null, $user_id = null, $user_name = null, array $notificationData = []): bool|string
    {
        if ($user_id) {
            AppNotification::create([
                'user_id' => $user_id,
                'ride_request_id' => $ride_request_id,
                'title' => $title ?? 'Title Not Found',
                'description' => $description ?? 'Description Not Found',
                'type' => $type,
                'notification_type' => $notification_type,
                'action' => $action,
                'is_read' => 0,
            ]);
        }
        $postData = buildDeviceNotificationPayload(
            fcm_token: $fcm_token,
            title: $title,
            description: $description,
            status: $status,
            image: $image,
            ride_request_id: $ride_request_id,
            type: $type,
            action: $action,
            user_name: $user_name,
            notificationData: $notificationData
        );

        return sendNotificationToHttp($postData);
    }
}

if (!function_exists('sendBatchDeviceNotification')) {
    function sendBatchDeviceNotification(array $notifications, int $chunkSize = 50): array
    {
        if (empty($notifications)) {
            return [];
        }

        $now = now();
        $appNotifications = [];
        $payloads = [];

        foreach ($notifications as $notification) {
            if (empty($notification['fcm_token'])) {
                continue;
            }

            $title = $notification['title'] ?? 'Title Not Found';
            $description = $notification['description'] ?? 'Description Not Found';
            $rideRequestId = $notification['ride_request_id'] ?? null;
            $type = $notification['type'] ?? null;
            $notificationType = $notification['notification_type'] ?? null;
            $action = $notification['action'] ?? null;
            $userId = $notification['user_id'] ?? null;

            if ($userId) {
                $appNotifications[] = [
                    'user_id' => $userId,
                    'ride_request_id' => $rideRequestId,
                    'title' => $title,
                    'description' => $description,
                    'type' => $type,
                    'notification_type' => $notificationType,
                    'action' => $action,
                    'is_read' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            $payloads[] = buildDeviceNotificationPayload(
                fcm_token: $notification['fcm_token'],
                title: $title,
                description: $description,
                status: $notification['status'] ?? 0,
                image: $notification['image'] ?? null,
                ride_request_id: $rideRequestId,
                type: $type,
                action: $action,
                user_name: $notification['user_name'] ?? null,
                notificationData: $notification['notificationData'] ?? []
            );
        }

        if (!empty($appNotifications)) {
            AppNotification::insert($appNotifications);
        }

        return sendNotificationsToHttpBatch($payloads, $chunkSize);
    }
}

if (!function_exists('sendTopicNotification')) {
    function sendTopicNotification($topic, $title, $description, $image = null, $ride_request_id = null, $type = null, $sentBy = null, $tripReferenceId = null, $route = null, $action = null, $status = null): bool|string
    {

        $imageURL = $image ? asset('storage/app/public/push-notification/' . $image) : null;
        $postData = [
            'message' => [
                'topic' => $topic,
                'data' => [
                    'title' => (string)$title,
                    'body' => (string)$description,
                    "ride_request_id" => (string)$ride_request_id,
                    "type" => (string)$type,
                    "title_loc_key" => (string)$ride_request_id,
                    "body_loc_key" => (string)$type,
                    "image" => (string)$imageURL,
                    "sound" => "notification.wav",
                    "android_channel_id" => "hexaride",
                    "sent_by" => (string)$sentBy,
                    "trip_reference_id" => (string)$tripReferenceId,
                    "route" => (string)$route,
                    "action" => (string)$action,
                    'status' => (string)$status,
                ],
                'notification' => [
                    'title' => (string)$title,
                    'body' => (string)$description,
                    "image" => (string)$imageURL,
                ],
                "android" => [
                    'priority' => 'high',
                    "notification" => [
                        "channelId" => "hexaride"
                    ]
                ],
                "apns" => [
                    "payload" => [
                        "aps" => [
                            "sound" => "notification.wav"
                        ]
                    ],
                    'headers' => [
                        'apns-priority' => '10',
                    ],
                ],
            ]
        ];
        return sendNotificationToHttp($postData);
    }
}

/**
 * @param string $url
 * @param string $postdata
 * @param array $header
 * @return bool|string
 */
function sendCurlRequest(string $url, string $postdata, array $header): string|bool
{
    $ch = curl_init();
    $timeout = 120;
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

    // Get URL content
    $result = curl_exec($ch);
    curl_close($ch);

    return $result;
}

function buildDeviceNotificationPayload($fcm_token, $title, $description, $status, $image = null, $ride_request_id = null, $type = null, $action = null, $user_name = null, array $notificationData = []): array
{
    $imageUrl = $image ? asset("storage/app/public/push-notification/$image") : null;

    return [
        'message' => [
            'token' => $fcm_token,
            'data' => [
                'title' => (string)$title,
                'body' => (string)$description,
                'status' => (string)$status,
                "ride_request_id" => (string)$ride_request_id,
                "type" => (string)$type,
                "user_name" => (string)$user_name,
                "title_loc_key" => (string)$ride_request_id,
                "body_loc_key" => (string)$type,
                "image" => (string)$imageUrl,
                "action" => (string)$action,
                "reward_type" => (string)($notificationData['reward_type'] ?? null),
                "reward_amount" => (string)($notificationData['reward_amount'] ?? 0),
                "next_level" => (string)($notificationData['next_level'] ?? null),
                "sound" => "notification.wav",
                "android_channel_id" => "hexaride"
            ],
            'notification' => [
                'title' => (string)$title,
                'body' => (string)$description,
                "image" => (string)$imageUrl,
            ],
            "android" => [
                'priority' => 'high',
                "notification" => [
                    "channel_id" => "hexaride",
                    "sound" => "notification.wav",
                    "icon" => "notification_icon",
                ]
            ],
            "apns" => [
                "payload" => [
                    "aps" => [
                        "sound" => "notification.wav"
                    ]
                ],
                'headers' => [
                    'apns-priority' => '10',
                ],
            ],
        ]
    ];
}

function sendNotificationToHttp(array|null $data): bool|string|null
{
    $key = Cache::rememberForever('server_key', function () {
        return json_decode(businessConfig('server_key')?->value);
    });

    if (!$key) return false;

    $accessTokenData = Cache::get('firebase_access_token');

    if ($accessTokenData && isset($accessTokenData['access_token']) && isset($accessTokenData['expires_at'])) {
        $expiresAt = $accessTokenData['expires_at'];
        if ($expiresAt > time()) {
            $accessToken = $accessTokenData['access_token'];
        } else {
            $accessToken = fetchAndCacheAccessToken($key);
        }
    } else {
        $accessToken = fetchAndCacheAccessToken($key);
    }

    if (!$accessToken) {
        return false;
    }

    $url = 'https://fcm.googleapis.com/v1/projects/' . $key->project_id . '/messages:send';
    $headers = [
        'Authorization' => 'Bearer ' . $accessToken,
        'Content-Type' => 'application/json',
    ];
    try {
        $response = Http::withHeaders($headers)->post($url, $data);
        if ($response->successful()) {
            return true;
        }
        return false;
    } catch (\Exception $exception) {
        \Illuminate\Support\Facades\Log::error('FCM Push failed', ['exception' => $exception]);
        return false;
    }
}

function sendNotificationsToHttpBatch(array $payloads, int $chunkSize = 50): array
{
    if (empty($payloads)) {
        return [];
    }

    $key = Cache::rememberForever('server_key', function () {
        return json_decode(businessConfig('server_key')?->value);
    });

    if (!$key) return [];

    $accessTokenData = Cache::get('firebase_access_token');

    if ($accessTokenData && isset($accessTokenData['access_token']) && isset($accessTokenData['expires_at'])) {
        $expiresAt = $accessTokenData['expires_at'];
        if ($expiresAt > time()) {
            $accessToken = $accessTokenData['access_token'];
        } else {
            $accessToken = fetchAndCacheAccessToken($key);
        }
    } else {
        $accessToken = fetchAndCacheAccessToken($key);
    }

    if (!$accessToken) {
        return array_fill(0, count($payloads), false);
    }

    $url = 'https://fcm.googleapis.com/v1/projects/' . $key->project_id . '/messages:send';
    $headers = [
        'Authorization' => 'Bearer ' . $accessToken,
        'Content-Type' => 'application/json',
    ];

    $results = [];
    foreach (array_chunk($payloads, $chunkSize) as $chunk) {
        try {
            $responses = Http::pool(function ($pool) use ($chunk, $headers, $url) {
                return array_map(
                    fn($payload) => $pool->withHeaders($headers)->post($url, $payload),
                    $chunk
                );
            });

            foreach ($responses as $response) {
                $results[] = $response->successful();
            }
        } catch (\Exception $exception) {
            \Illuminate\Support\Facades\Log::error('Batch FCM Push failed', ['exception' => $exception]);
            $results = array_merge($results, array_fill(0, count($chunk), false));
        }
    }

    return $results;
}

function fetchAndCacheAccessToken($key)
{
    $accessTokenData = getAccessToken($key);

    if ($accessTokenData['status'] && isset($accessTokenData['data'])) {
        $expiresAt = time() + 3600;
        $data = [
            'access_token' => $accessTokenData['data'],
            'expires_at' => $expiresAt
        ];

        Cache::put('firebase_access_token', $data, 3500);
        return $accessTokenData['data'];
    }

    return false;
}

function getAccessToken($key): array|string
{
    $jwtToken = [
        'iss' => $key->client_email,
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud' => 'https://oauth2.googleapis.com/token',
        'exp' => time() + 3600,
        'iat' => time(),
    ];

    $jwtHeader = base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $jwtPayload = base64UrlEncode(json_encode($jwtToken));
    $unsignedJwt = $jwtHeader . '.' . $jwtPayload;
    openssl_sign($unsignedJwt, $signature, $key->private_key, OPENSSL_ALGO_SHA256);
    $jwt = $unsignedJwt . '.' . base64_encode($signature);

    $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt,
    ]);
    if ($response->failed()) {
        return [
            'status' => false,
            'data' => $response->json()
        ];

    }
    return [
        'status' => true,
        'data' => $response->json('access_token')
    ];
}

function base64UrlEncode($data)
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}
