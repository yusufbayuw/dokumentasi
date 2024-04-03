<?php

namespace App\Http\Controllers;

use App\Models\Device;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class LaporanController extends Controller
{
    public function generatePDF()
    {
        $pdf = Pdf::setOption(['dpi' => 150, 'isHtml5ParserEnabled' => true, 'defaultFont' => 'sans-serif'])->loadView('laporan.laporan')->setPaper('a4', 'landscape');
        return $pdf->stream();//download('invoice.pdf');
    }
}
