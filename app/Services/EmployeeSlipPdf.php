<?php
declare(strict_types=1);
namespace App\Services;

use App\Domain\PrintLayout;
use Dompdf\{Dompdf,Options};

final class EmployeeSlipPdf {
    public function render(array $rows): string {
        $print=PrintLayout::metadata('');
        ob_start();
        try {
            foreach($rows as $row)require BASE_PATH.'/app/Views/employee-slip-print.php';
            $document=ob_get_contents();
        } finally { ob_end_clean(); }
        $css=file_get_contents(BASE_PATH.'/public/assets/employee-slip.css');
        $options=new Options();
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setIsJavascriptEnabled(false);
        $options->setDefaultMediaType('print');
        $options->setChroot(BASE_PATH.'/public/assets');
        $pdf=new Dompdf($options);
        $pdf->setPaper('A4','portrait');
        $pdf->loadHtml('<!doctype html><html lang="id"><head><meta charset="utf-8"><style>@page{size:A4 portrait;margin:12mm}body{margin:0;padding:0}'.$css.'</style></head><body>'.$document.'</body></html>','UTF-8');
        $pdf->render();
        return $pdf->output();
    }
}
