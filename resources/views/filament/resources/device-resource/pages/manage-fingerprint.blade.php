<x-filament-panels::page>
    <div x-data="{ tab: 'overview' }" class="space-y-6">
        <div class="flex flex-wrap gap-2" role="tablist" aria-label="Manajemen fingerprint">
            @foreach (['overview' => 'Ringkasan & Info', 'users' => 'Pengguna', 'attendance' => 'Log Absensi', 'history' => 'Riwayat Operasi'] as $key => $label)
                @if ($key === 'overview' || $key === 'history' || $sensitive)
                    <button type="button" role="tab" :aria-selected="tab === '{{ $key }}'"
                        @click="tab = '{{ $key }}'"
                        :class="tab === '{{ $key }}' ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200'"
                        class="rounded-lg px-4 py-2 text-sm font-medium">{{ $label }}</button>
                @endif
            @endforeach
        </div>

        <div x-show="tab === 'overview'" class="space-y-6">
            <x-filament::section heading="Identitas Perangkat">
                <dl class="grid grid-cols-1 gap-4 md:grid-cols-3 text-sm">
                    <div><dt class="text-gray-500">Perangkat</dt><dd class="font-semibold">{{ $this->record->nama }}</dd></div>
                    <div><dt class="text-gray-500">Management IPv4</dt><dd>{{ $this->record->ip?->nama ?? $this->record->managementInterface?->ipAddress?->nama ?? 'Belum dikonfigurasi' }}</dd></div>
                    <div><dt class="text-gray-500">Terakhir baca info</dt><dd>{{ $snapshot?->information_synced_at ?? 'Belum pernah' }}</dd></div>
                </dl>
                <p class="mt-4 text-xs text-gray-500">Informasi berikut merupakan snapshot terakhir, bukan status live. Gunakan tombol Baca Info Mesin untuk memperbarui.</p>
            </x-filament::section>

            <x-filament::section heading="Informasi Mesin">
                @if ($information)
                    <dl class="grid grid-cols-1 gap-4 md:grid-cols-2 text-sm">
                        @foreach (['device_name' => 'Nama mesin', 'serial_number' => 'Serial number', 'platform' => 'Platform', 'firmware' => 'Firmware', 'version' => 'Versi', 'os_version' => 'OS', 'pin_width' => 'Lebar PIN', 'machine_time' => 'Jam mesin saat dibaca'] as $field => $label)
                            <div><dt class="text-gray-500">{{ $label }}</dt><dd class="font-medium">{{ $information[$field] ?? 'Tidak didukung / tidak tersedia' }}</dd></div>
                        @endforeach
                    </dl>
                @else
                    <p class="text-sm text-gray-500">Belum ada informasi tersimpan. Klik Baca Info Mesin untuk mengantrekan pengambilan data.</p>
                @endif
            </x-filament::section>

            <x-filament::section heading="Pengamanan Operasional">
                <p class="text-sm text-gray-600 dark:text-gray-400">Sinkronisasi tidak menghapus data pada mesin. Pengelolaan template biometrik, hapus massal pengguna, shutdown, dan pembersihan log mesin sengaja tidak disediakan. Koneksi memakai UDP/4370 sesuai paket existing.</p>
                @unless ($sensitive)
                    <p class="mt-2 text-sm text-warning-600">Daftar pengguna dan absensi memerlukan izin view_fingerprint_data atau role super_admin.</p>
                @endunless
            </x-filament::section>
        </div>

        @if ($sensitive)
            <div x-show="tab === 'users'" class="space-y-4">
                <x-filament::section heading="Pengguna Mesin (Snapshot)">
                    <p class="mb-3 text-sm text-gray-500">Total tersimpan: {{ number_format($userCount) }}. Sinkron terakhir: {{ $snapshot?->users_synced_at ?? 'Belum pernah' }}. Maksimal 100 hasil ditampilkan; password, kartu dan template sidik jari tidak diambil ke database.</p>
                    <input type="search" wire:model.live.debounce.400ms="searchUser" placeholder="Cari ID atau nama..."
                        class="mb-4 w-full rounded-lg border-gray-300 dark:bg-gray-900 dark:border-gray-700" aria-label="Cari pengguna fingerprint" />
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead><tr class="border-b dark:border-gray-700"><th class="p-2">ID Mesin</th><th class="p-2">UID</th><th class="p-2">Nama</th><th class="p-2">Role</th><th class="p-2">Terakhir Terbaca</th></tr></thead>
                            <tbody>
                                @forelse ($users as $user)
                                    <tr class="border-b dark:border-gray-800">
                                        <td class="p-2">{{ $user->user_id }}</td><td class="p-2">{{ $user->uid }}</td>
                                        <td class="p-2">{{ $user->name }}</td><td class="p-2">{{ $user->role }}</td>
                                        <td class="p-2">{{ $user->last_seen_at }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="p-3 text-gray-500">Belum ada pengguna yang tersinkron.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-filament::section>
            </div>

            <div x-show="tab === 'attendance'" class="space-y-4">
                <x-filament::section heading="Riwayat Absensi Mesin">
                    <p class="mb-3 text-sm text-gray-500">Total tersimpan: {{ number_format($attendanceCount) }}. Sinkron terakhir: {{ $snapshot?->attendance_synced_at ?? 'Belum pernah' }}. Ditampilkan 100 event terbaru; waktu mengikuti jam mesin, bukan hasil konversi timezone.</p>
                    <div class="mb-4 flex flex-wrap items-center gap-3">
                        <input type="search" wire:model.live.debounce.400ms="searchAttendanceUser" placeholder="Cari ID pengguna..."
                            class="rounded-lg border-gray-300 dark:bg-gray-900 dark:border-gray-700" aria-label="Cari log fingerprint" />
                        <x-filament::button wire:click="exportAttendance" color="gray" icon="heroicon-o-arrow-down-tray">Ekspor seluruh CSV</x-filament::button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead><tr class="border-b dark:border-gray-700"><th class="p-2">ID Pengguna</th><th class="p-2">UID</th><th class="p-2">Waktu Mesin</th><th class="p-2">State</th><th class="p-2">Type</th></tr></thead>
                            <tbody>
                                @forelse ($attendance as $event)
                                    <tr class="border-b dark:border-gray-800">
                                        <td class="p-2">{{ $event->user_id }}</td><td class="p-2">{{ $event->uid }}</td>
                                        <td class="p-2">{{ $event->event_at }}</td><td class="p-2">{{ $event->state }}</td><td class="p-2">{{ $event->type }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="p-3 text-gray-500">Belum ada log yang tersinkron.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-filament::section>
            </div>
        @endif

        <div x-show="tab === 'history'">
            <x-filament::section heading="Riwayat Operasi">
                <p class="mb-3 text-sm text-gray-500">Status diproses oleh queue device-actions. Refresh halaman untuk melihat perkembangan. Tombol ini tidak melakukan koneksi langsung dari browser.</p>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead><tr class="border-b dark:border-gray-700"><th class="p-2">Dikirim</th><th class="p-2">Operasi</th><th class="p-2">Status</th><th class="p-2">Jumlah Dibaca</th><th class="p-2">Keterangan</th></tr></thead>
                        <tbody>
                            @forelse ($runs as $run)
                                <tr class="border-b dark:border-gray-800">
                                    <td class="p-2">{{ $run->created_at }}</td><td class="p-2">{{ $run->operation }}</td>
                                    <td class="p-2">{{ $run->status }}</td><td class="p-2">{{ $run->records }}</td>
                                    <td class="p-2">{{ $run->error ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="p-3 text-gray-500">Belum ada operasi tercatat.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
