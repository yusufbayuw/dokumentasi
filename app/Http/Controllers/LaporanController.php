<?php

namespace App\Http\Controllers;

use App\Models\TypeDevice;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class LaporanController extends Controller
{
    public function generatePDF(): Response
    {
        $typeDevices = TypeDevice::query()
            ->withCount('device')
            ->with([
                'device' => fn ($query) => $query
                    ->with([
                        'ip',
                        'ruangan.unit',
                        'ruangan.lantai.gedung',
                    ])
                    ->orderBy('nama'),
            ])
            ->orderBy('nama')
            ->get();

        $pdf = Pdf::setOption([
            'dpi' => 150,
            'isHtml5ParserEnabled' => true,
            'defaultFont' => 'sans-serif',
        ])
            ->loadView('laporan.laporan', compact('typeDevices'))
            ->setPaper('a4', 'landscape');

        return $pdf->stream('dokumentasi-ict-ytb.pdf');
    }
}
