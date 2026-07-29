<?php

namespace Modules\VehicleManagement\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VehicleRequestDeniedMail extends Mailable
{
    use Queueable, SerializesModels;


    public function build()
    {
        $businessLogo = businessConfig(key: 'header_logo', settingsType: BUSINESS_INFORMATION)?->value;
        $data['businessLogoUrl'] = dynamicStorage('storage/app/public/business/' . $businessLogo);
        $data['businessName'] = businessConfig(key: 'business_name', settingsType: BUSINESS_INFORMATION)?->value;
        $data['websiteUrl'] = url('/');

        return $this->view('vehiclemanagement::admin.vehicle.mail.vehicle-request-denied', $data);
    }
}
