<x-filament-panels::page>
    @php
        $severityStyles = [
            'danger' => 'border-red-200 bg-red-50 text-red-700 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-300',
            'warning' => 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-300',
            'info' => 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-900/60 dark:bg-blue-950/30 dark:text-blue-300',
        ];
    @endphp

    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">Health Check Data CMDB</h2>
                    <p class="mt-1 max-w-3xl text-sm text-gray-500 dark:text-gray-400">
                        Pemeriksaan ini read-only. Tidak ada data yang diperbaiki otomatis. Prioritaskan konflik IP dan inkonsistensi assignment sebelum melanjutkan automation jaringan.
                    </p>
                </div>
                <div class="text-xs text-gray-400">Dihitung saat halaman dibuka</div>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($issues as $issue)
                <div class="rounded-xl border p-5 shadow-sm {{ $severityStyles[$issue['severity']] ?? $severityStyles['info'] }}">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <h3 class="text-sm font-semibold">{{ $issue['label'] }}</h3>
                            <p class="mt-2 text-xs leading-5 opacity-80">{{ $issue['description'] }}</p>
                        </div>
                        <span class="shrink-0 text-2xl font-bold tabular-nums">{{ number_format($issue['count']) }}</span>
                    </div>

                    <div class="mt-4 border-t border-current/10 pt-3">
                        <a href="{{ $issue['device_url'] }}" class="text-xs font-semibold underline decoration-current/30 underline-offset-4 hover:decoration-current">
                            Buka daftar perangkat
                        </a>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-white/10 dark:bg-gray-900">
            <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Urutan perbaikan yang disarankan</h3>
            <ol class="mt-3 list-decimal space-y-2 pl-5 text-sm text-gray-600 dark:text-gray-300">
                <li>Konflik IP dan status booked yang tidak konsisten.</li>
                <li>Perangkat critical/high tanpa IP atau lokasi.</li>
                <li>Ownership, kode aset, serial number, dan MAC address.</li>
                <li>Interface terstruktur dan koneksi port-aware.</li>
                <li>Topology legacy yang belum memiliki representasi graph.</li>
            </ol>
        </div>
    </div>
</x-filament-panels::page>
