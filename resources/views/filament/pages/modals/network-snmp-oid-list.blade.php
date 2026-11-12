<div class="space-y-4">
    @if ($snapshot)
        <x-filament::section>
            <div class="grid gap-2 text-sm text-gray-700 dark:text-gray-300 md:grid-cols-2">
                <p><strong>Versi:</strong> SNMP{{ $snapshot->snmp_version }}</p>
                <p><strong>Status:</strong> {{ $snapshot->status }}</p>
                <p><strong>Jumlah OID:</strong> {{ number_format($snapshot->oid_count) }}</p>
                <p><strong>Mulai:</strong> {{ $snapshot->started_at?->format('d/m/Y H:i:s') }}</p>
                <p><strong>Kesalahan:</strong> {{ $snapshot->error_code ?? '-' }}</p>
                <p><strong>Nilai terlalu besar:</strong> {{ $snapshot->limited_values }}</p>
            </div>
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                Data ini mungkin memuat informasi sensitif jaringan. Snapshot hanya mencakup OID
                yang diizinkan agent SNMP dan berhasil dikumpulkan sesuai batas keamanan.
            </p>
        </x-filament::section>
        @livewire('network-snmp-oid-table', ['snapshotId' => $snapshot->id], key('snmp-oid-table-'.$snapshot->id))
    @else
        <p>Snapshot belum tersedia.</p>
    @endif
</div>
