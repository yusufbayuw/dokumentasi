<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Laporan Dokumentasi ICT</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10pt;
            color: #222;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 5px;
            text-align: left;
            vertical-align: top;
        }

        th {
            background-color: #f0f0f0;
            font-weight: bold;
        }

        tbody tr:nth-child(even) {
            background-color: #f8f8f8;
        }

        h2 {
            page-break-before: always;
        }

        h3 {
            margin-bottom: 6px;
        }

        .cover {
            text-align: center;
        }

        .cover-note {
            margin-top: 45%;
            font-style: italic;
            font-size: 9pt;
        }

        .muted {
            color: #666;
        }

        @page {
            size: A4 landscape;
            margin: 10mm;
        }
    </style>
</head>
<body>
    <div class="cover">
        <h1 style="margin-bottom: 1pt">Dokumentasi Sistem & Teknologi Informasi</h1>
        <h1 style="margin-top: 1pt">Yayasan Taruna Bakti</h1>
        <p class="cover-note">
            Dokumen ini digenerate melalui Sistem Dokumentasi ICT Yayasan Taruna Bakti pada
            {{ now()->timezone('Asia/Jakarta')->format('d-m-Y') }} pukul
            {{ now()->timezone('Asia/Jakarta')->format('H:i:s') }} WIB.
            Untuk mendapatkan dokumen terbaru silakan menghubungi Administrator.
        </p>
    </div>

    <div>
        <h2>Rekap Perangkat IT</h2>
        <table>
            <thead>
                <tr>
                    <th style="width: 8%">No</th>
                    <th>Jenis Perangkat</th>
                    <th style="width: 15%">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($typeDevices as $idx => $type)
                    <tr>
                        <td>{{ $idx + 1 }}</td>
                        <td>{{ $type->nama }}</td>
                        <td>{{ $type->device_count }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="muted">Belum ada data perangkat.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>
        <h2>Rincian Perangkat IT</h2>

        @forelse ($typeDevices as $idx => $type)
            <h3>{{ $idx + 1 }}. {{ $type->nama }}</h3>

            <table>
                <thead>
                    <tr>
                        <th style="width: 6%">No</th>
                        <th>Nama</th>
                        <th style="width: 16%">IP</th>
                        <th>Lokasi</th>
                        <th style="width: 18%">Unit</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($type->device as $index => $device)
                        @php
                            $room = $device->ruangan?->nama;
                            $floor = $device->ruangan?->lantai?->nama;
                            $building = $device->ruangan?->lantai?->gedung?->nama;
                            $locationParts = array_values(array_filter([$room, $floor, $building]));
                            $location = $locationParts !== [] ? implode(' - ', $locationParts) : '-';
                            $unit = $device->ruangan?->unit?->nama ?? '-';
                        @endphp
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>{{ $device->nama }}</td>
                            <td>{{ $device->ip?->nama ?? '-' }}</td>
                            <td>{{ $location }}</td>
                            <td>{{ $unit }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="muted">Belum ada perangkat pada kategori ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        @empty
            <p class="muted">Belum ada data perangkat.</p>
        @endforelse
    </div>
</body>
</html>
