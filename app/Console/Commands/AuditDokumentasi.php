<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\IpAddress;
use App\Models\Topology;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AuditDokumentasi extends Command
{
    protected $signature = 'dokumentasi:audit
        {--details : Tampilkan contoh data yang perlu diperiksa}
        {--json : Keluarkan hasil sebagai JSON}';

    protected $description = 'Audit read-only integritas data perangkat, IP address, monitoring, dan topologi';

    public function handle(): int
    {
        $invalidIps = IpAddress::query()
            ->get(['id', 'nama'])
            ->filter(fn (IpAddress $ip): bool => filter_var($ip->nama, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false)
            ->values();

        $assignedIpIds = Device::query()
            ->whereNotNull('ip_address_id')
            ->pluck('ip_address_id')
            ->filter()
            ->unique()
            ->flip();

        $bookedMismatches = IpAddress::query()
            ->get(['id', 'nama', 'booked'])
            ->filter(function (IpAddress $ip) use ($assignedIpIds): bool {
                $actuallyUsed = $assignedIpIds->has($ip->id);

                return (bool) $ip->booked !== $actuallyUsed;
            })
            ->values();

        $duplicateIpAssignments = Device::query()
            ->join('ip_addresses', 'devices.ip_address_id', '=', 'ip_addresses.id')
            ->select([
                'devices.ip_address_id',
                'ip_addresses.nama as ip_address',
                DB::raw('COUNT(devices.id) as total'),
            ])
            ->whereNotNull('devices.ip_address_id')
            ->groupBy('devices.ip_address_id', 'ip_addresses.nama')
            ->havingRaw('COUNT(devices.id) > 1')
            ->orderByDesc('total')
            ->get();

        $duplicateTopologies = Topology::query()
            ->select([
                'device_id',
                DB::raw('COUNT(id) as total'),
            ])
            ->whereNotNull('device_id')
            ->groupBy('device_id')
            ->havingRaw('COUNT(id) > 1')
            ->orderByDesc('total')
            ->get();

        $monitoringWithoutIp = Device::query()
            ->where('monitoring_enabled', true)
            ->whereNull('ip_address_id')
            ->get(['id', 'nama']);

        $devicesWithoutTopology = Device::query()
            ->whereDoesntHave('topology')
            ->get(['id', 'nama']);

        $summary = collect([
            ['issue' => 'IP address tidak valid', 'severity' => 'critical', 'count' => $invalidIps->count()],
            ['issue' => 'Satu IP dipakai lebih dari satu Device', 'severity' => 'critical', 'count' => $duplicateIpAssignments->count()],
            ['issue' => 'Flag booked tidak sesuai pemakaian aktual', 'severity' => 'warning', 'count' => $bookedMismatches->count()],
            ['issue' => 'Topology duplicate untuk Device yang sama', 'severity' => 'warning', 'count' => $duplicateTopologies->count()],
            ['issue' => 'Monitoring aktif tetapi Device tidak punya IP', 'severity' => 'warning', 'count' => $monitoringWithoutIp->count()],
            ['issue' => 'Device belum memiliki node Topology', 'severity' => 'info', 'count' => $devicesWithoutTopology->count()],
        ]);

        if ($this->option('json')) {
            $this->line(json_encode([
                'summary' => $summary->all(),
                'generated_at' => now()->toIso8601String(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('Audit Dokumentasi ICT YTB');
        $this->line('Mode read-only. Tidak ada data yang diubah.');
        $this->newLine();

        $this->table(
            ['Severity', 'Pemeriksaan', 'Jumlah'],
            $summary->map(fn (array $row): array => [
                strtoupper($row['severity']),
                $row['issue'],
                $row['count'],
            ])->all(),
        );

        if ($this->option('details')) {
            $this->renderDetails(
                $invalidIps,
                $duplicateIpAssignments,
                $bookedMismatches,
                $duplicateTopologies,
                $monitoringWithoutIp,
                $devicesWithoutTopology,
            );
        }

        $problemCount = $summary->sum('count');

        if ($problemCount === 0) {
            $this->newLine();
            $this->info('Tidak ditemukan masalah pada pemeriksaan yang tersedia.');
        } else {
            $this->newLine();
            $this->warn("Ditemukan {$problemCount} item yang perlu ditinjau. Gunakan --details untuk contoh data.");
        }

        return self::SUCCESS;
    }

    private function renderDetails(
        Collection $invalidIps,
        Collection $duplicateIpAssignments,
        Collection $bookedMismatches,
        Collection $duplicateTopologies,
        Collection $monitoringWithoutIp,
        Collection $devicesWithoutTopology,
    ): void {
        $this->detailTable(
            'IP tidak valid',
            ['ID', 'IP'],
            $invalidIps->take(20)->map(fn ($row): array => [$row->id, $row->nama])->all(),
        );

        $this->detailTable(
            'Duplicate IP assignment',
            ['IP', 'Jumlah Device'],
            $duplicateIpAssignments->take(20)->map(fn ($row): array => [$row->ip_address, $row->total])->all(),
        );

        $this->detailTable(
            'Booked mismatch',
            ['ID', 'IP', 'Booked'],
            $bookedMismatches->take(20)->map(fn ($row): array => [$row->id, $row->nama, $row->booked ? 'true' : 'false'])->all(),
        );

        $this->detailTable(
            'Duplicate topology',
            ['Device ID', 'Jumlah Node'],
            $duplicateTopologies->take(20)->map(fn ($row): array => [$row->device_id, $row->total])->all(),
        );

        $this->detailTable(
            'Monitoring aktif tanpa IP',
            ['Device ID', 'Nama'],
            $monitoringWithoutIp->take(20)->map(fn ($row): array => [$row->id, $row->nama])->all(),
        );

        $this->detailTable(
            'Device tanpa Topology',
            ['Device ID', 'Nama'],
            $devicesWithoutTopology->take(20)->map(fn ($row): array => [$row->id, $row->nama])->all(),
        );
    }

    private function detailTable(string $title, array $headers, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $this->newLine();
        $this->comment($title . ' (maks. 20 baris)');
        $this->table($headers, $rows);
    }
}
