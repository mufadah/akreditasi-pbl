<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\File;

class PdfService
{
    public function dariView($view, $data = [], $orientasi = 'portrait')
    {
        $folder = storage_path('app/dompdf');
        File::ensureDirectoryExists($folder);

        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('tempDir', $folder);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view($view, $data)->render());
        $dompdf->setPaper('A4', $orientasi);
        $dompdf->render();

        return $dompdf->output();
    }
}