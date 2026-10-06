<?php

namespace App\Console\Commands;

use App\Services\CmdbDataQualityService;
use Illuminate\Console\Command;

class AuditDokumentasi extends Command
{
    protected $signature = 'dokumentasi:audit
        {--details : Tampilkan contoh data yang perlu diperiksa}
        {--json : Keluarkan hasil sebagai JSON}';

    protected $description = 'Audit read-only integritas CMDB, IPAM, monitoring, maintenance, dan topology';

    public function handle(CmdbDataQualityService $service): int
    {
        $issues = collect($service->issues());

        if ($this->option('json')) {
            $this->line(json_encode([
                'summary' => $issues->all(),
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
            $issues->map(fn (array $row): array => [
                strtoupper($row['severity']),
                $row['label'],
                $row['count'],
            ])->all(),
        );

        if ($this->option('details')) {
            foreach ($issues as $issue) {
                if (($issue['examples'] ?? []) === []) {
                    continue;
                }

                $this->newLine();
                $this->comment($issue['label'] . ' (maks. 10 contoh)');
                $this->table(['Contoh'], collect($issue['examples'])
                    ->take(10)
                    ->map(fn (string $example): array => [$example])
                    ->all());
            }
        }

        $problemCount = $issues
            ->whereIn('severity', ['danger', 'warning'])
            ->sum('count');

        if ($problemCount === 0) {
            $this->newLine();
            $this->info('Tidak ditemukan masalah critical/warning pada pemeriksaan yang tersedia.');
        } else {
            $this->newLine();
            $this->warn("Ditemukan {$problemCount} item critical/warning yang perlu ditinjau.");
        }

        return self::SUCCESS;
    }
}
