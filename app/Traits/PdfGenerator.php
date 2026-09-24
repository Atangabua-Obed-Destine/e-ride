<?php

namespace App\Traits;

trait PdfGenerator
{
    public static function generatePdf($view, $filePrefix, $filePostfix)
    {
        $tempDir = storage_path('app/mpdf');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        $mpdf = new \Mpdf\Mpdf(['default_font' => 'Inter', 'mode' => 'utf-8', 'format' => [190, 250], 'autoLangToFont' => true, 'tempDir' => $tempDir]);
        $mpdf->autoScriptToLang = true;
        $mpdf->autoLangToFont = true;

        $mpdf_view = $view;
        $mpdf_view = $mpdf_view->render();
        $mpdf->WriteHTML($mpdf_view);
        $mpdf->Output($filePrefix . $filePostfix . '.pdf', 'D');
    }
}
