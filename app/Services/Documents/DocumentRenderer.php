<?php

namespace App\Services\Documents;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class DocumentRenderer
{
    public function __construct(private readonly DocumentDataService $documents)
    {
    }

    public function html(string $type, Model $document, bool $preferSnapshot = true): string
    {
        return view('documents.show', [
            'document' => $this->documents->normalize($type, $document, $preferSnapshot),
        ])->render();
    }

    public function preview(string $type, Model $document)
    {
        return response($this->html($type, $document))->header('Content-Type', 'text/html; charset=UTF-8');
    }

    public function pdf(string $type, Model $document): string
    {
        if (!class_exists(Dompdf::class)) {
            throw new RuntimeException('The dompdf/dompdf package is required. Run composer install.');
        }

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $pdf = new Dompdf($options);
        $pdf->loadHtml($this->html($type, $document));
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        $canvas = $pdf->getCanvas();
        $canvas->page_text(520, 815, __('Page') . ' {PAGE_NUM} / {PAGE_COUNT}', null, 8, [0.4, 0.4, 0.4]);

        return $pdf->output();
    }
}
