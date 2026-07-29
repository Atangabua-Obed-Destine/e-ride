<?php

if (!function_exists('aiImageFullPath'))
{
    function aiImageFullPath($imageName)
    {
        if (in_array(request()->ip(), ['127.0.0.1', '::1'])) {
            return [
                'image_name' => $imageName,
                'image_full_path' => "https://drivemond-admin.codemond.com/storage/app/public/promotion/discount/2024-09-26-66f51b640c4d7.png",
            ];
        }

        return [
            'image_name' => $imageName,
            'image_full_path' => asset(path: 'storage/app/public/blog/ai-image/' . $imageName)
        ];
    }
}
