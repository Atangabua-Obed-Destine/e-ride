<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Email</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f6f8;">

    <table width="100%" cellpadding="0" cellspacing="0" bgcolor="#f4f6f8">
        <tr>
            <td align="center" style="padding:20px 0;">

                <table width="100%" cellpadding="0" cellspacing="0"
                    style="max-width:500px;
                        background-color:#ffffff;
                        font-family:'Open Sans', Arial, Helvetica, sans-serif;
                        color:#1D2D2B;
                        border-radius:10px;">

                    <!-- Logo -->
                    <tr>
                        <td align="center" style="padding:30px 20px 20px;">
                            <img src="{{ $businessLogoUrl }}"
                                width="100"
                                alt="{{ $businessName }}"
                                style="display:block; border:0; outline:none; text-decoration:none; width:100px;">
                        </td>
                    </tr>

                    <!-- Status Icon -->
                    <tr>
                        <td align="center" style="padding-bottom:20px;">
                            <img src="{{ dynamicAsset('public/assets/admin-module/img/email-template/denied-icon.png') }}"
                                width="50"
                                alt="Denied"
                                style="display:block; border:0; outline:none; text-decoration:none;">
                        </td>
                    </tr>

                    <!-- Title -->
                    <tr>
                        <td align="center" style="padding:0 30px 16px;">
                            <p style="margin:0; font-size:16px; font-weight:bold; color:#1D2D2B;">
                                {{ translate('Your Vehicle Request Denied') }}
                            </p>
                        </td>
                    </tr>

                    <!-- Message -->
                    <tr>
                        <td align="left" style="padding:0 30px 24px;">
                            <p style="margin:0; font-size:14px; line-height:22px; color:#6b7280;">
                                {{ translate('We are sorry to inform you that your vehicle request has been denied.') }}
                                {{ translate('Please log in to your account and update your vehicle information.') }}
                            </p>
                        </td>
                    </tr>

                    <!-- Update Button -->
                    <tr>
                        <td align="left" style="padding:0 30px 24px;">
                            <p style="margin:0 0 6px; font-size:14px; color:#6b7280;">
                                {{ translate('Visit Our Website') }}
                            </p>
                            <a href="{{ $websiteUrl }}"
                               style="font-size:14px; color:#016ACD; text-decoration:underline;">
                                {{ $websiteUrl }}
                            </a>

                        </td>
                    </tr>

                    <!-- Divider -->
                    <tr>
                        <td style="padding:0 30px;">
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="border-top:1px solid #dde2e7; height:1px; font-size:1px; line-height:1px;">
                                        &nbsp;
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td align="left" style="padding:20px 30px 30px;">
                            <p style="margin:0; font-size:14px; color:#6b7280;">
                                {{ translate('Please contact us for any queries; we’re always happy to help.') }}
                            </p>
                            <p style="margin:20px 0 0; font-size:14px; color:#6b7280;">
                                {{ translate('Thanks & Regards,') }}
                            </p>
                            <p style="margin:6px 0 0; font-size:14px; color:#6b7280;">
                                {{ $businessName ?? 'Drivemond' }}
                            </p>
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>
</html>
