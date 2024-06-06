@php
use App\Models\Device;
use App\Models\TypeDevice;
    $typeDevice = TypeDevice::all();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Laporan</title>
    <style>
table {
    width: auto; /* Set table width to auto to fit content */
    border-collapse: collapse; /* Make cells share borders */ 
    font-family: Arial, sans-serif; /* Set a basic font */
    margin-bottom: 20px; /* Add space below table */
}

th, td {
    border: 1px solid #ddd; 
    padding: 5px; /* Reduce padding */
    text-align: left; /* Align text within cells */
}

th {
    background-color: #f0f0f0; /* Light gray header background */
    font-weight: bold;
}

tbody tr:nth-child(even) {
    background-color: #f8f8f8; /* Subtle background on even rows */
}

@media print {
    @page {
        size: A4 portrait; /* Set page size to A4 portrait */
        margin: 10mm; /* Set margin */
    }

    table {
        width: 100%; /* Make table fill available width */
        font-size: 10pt; /* Adjust font size */
        max-width: 100%; /* Ensure table doesn't overflow */
        page-break-inside: auto; /* Allow page breaks inside table */
    }
}

tbody tr:hover {
    background-color: #e8e8e8; /* Highlight on mouse-over */
}

/* Center content in location column */
td:nth-child(4) { 
    text-align: center;  
}

h2 {
    page-break-before: always; /* Membuat elemen h2 muncul di halaman baru */
}

    </style>
</head>
<body>
    <div style="text-align: center">
        <h1 style="margin-bottom: 1pt">Dokumentasi Sistem & Teknologi Informasi</h1>
        <h1 style="margin-top: 1pt">Yayasan Taruna Bakti</h1>
        <p style="margin-top: 50%;"><i>Dokumen ini digenerate melalui <a href="{{ env('APP_URL') }}">Sistem Dokumentasi ICT Yayasan Taruna Bakti</a> pada tanggal {{ date_format(now(),"d-m-Y") }} pukul {{ date_format(now(),"H:i:s") }} WIB dan untuk digunakan sebagaimana mestinya. Untuk mendapatkan dokumen terbaru silakan menghubungi Administrator.</i></p>
    </div>
    <div>
        <h2>Rekap Perangkat IT</h2>
        <div>
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Jenis Perangkat</th>
                        <th>Jumlah</th>
                    </tr>
                </thead>
                @foreach ($typeDevice as $idx => $type)
                    <tr>
                        <td>{{ $idx+1 }}</td>
                        <td>{{ $type->nama }}</td>
                        <td>{{ Device::where('type_device_id', $type->id)->count() }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
    </div>
    <div>
        <h2>Rincian Perangkat IT</h2>
        @foreach ($typeDevice as $idx => $type)
            <h3>{{ $idx+1 .". ".$type->nama }}</h3>
            <table>   
            <thead>
                <tr>
                    <th>No</th>
                    <th>Nama</th>
                    <th>IP</th>
                    <th>Lokasi</th>
                    <th>Unit</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $devices = Device::where('type_device_id', $type->id)->get();
                @endphp
                @foreach ($devices as $index => $device)
                    <tr>
                        <td>{{ $index+1 }}</td>
                        <td>{{ $device->nama }}</td>
                        <td>{{ $device->ip->nama ?? null }}</td>
                        <td>{{ $device->ruangan->nama . ' - ' . $device->ruangan->lantai->nama . ' - ' . $device->ruangan->lantai->gedung->nama }}</td>
                        <td>{{ $device->ruangan->unit->nama }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table> 
        @endforeach
    </div>
</body>
</html>