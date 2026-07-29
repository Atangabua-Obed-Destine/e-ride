<?php

namespace Modules\AuthManagement\Service\Interfaces;

use App\Service\BaseServiceInterface;
use Illuminate\Database\Eloquent\Model;

interface AuthServiceInterface extends BaseServiceInterface
{
    public function checkClientRoute($request);
//    public function generateOtp($user);

    public function sendOtpToClient($user,$type=null);

    public function updateLoginUser(string|int $id, array $data): ?Model;

}
