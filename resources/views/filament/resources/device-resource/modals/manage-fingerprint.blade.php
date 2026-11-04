<div x-data="{ tab: 'overview', searchUser: '', searchAttendance: '' }"
    wire:key="fingerprint-management-{{ (int) $device->getKey() }}"
    wire:poll.10s
    class="space-y-5">
    <div class="flex flex-wrap gap-2" role="tablist" aria-label="Manajemen fingerprint">
        @foreach (['overview' => 'Ringkasan', 'users' => 'Pengguna', 'attendance' => 'Absensi', 'history' => 'Riwayat Operasi'] as $key => $label)
            @if ($key === 'overview' || $key === 'history' || $sensitive)
                <button type="button" role="tab"
                    :aria-selected="tab === '{{ $key }}'"
                    @click="tab = '{{ $key }}'"
                    :class="tab === '{{ $key }}' ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200'"
                    class="rounded-lg px-3 py-2 text-sm font-medium">{{ $label }}</button>
            @endif
        @endforeach
    </div>

    <div x-show="tab === 'overview'" class="space-y-4">
        <x-filament::section heading="Identitas & Snapshot">
            <dl class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-3">
                <div><dt class="text-gray-500">Perangkat</dt><dd class="font-semibold">{{ $device->nama }}</dd></div>
                <div><dt class="text-gray-500">Management IPv4</dt><dd>{{ $device->ip?->nama ?? $device->managementInterface?->ipAddress?->nama ?? 'Belum dikonfigurasi' }}</dd></div>
                <div><dt class="text-gray-500">Info terakhir dibaca</dt><dd>{{ $snapshot?->information_synced_at ?? 'Belum pernah' }}</dd></div>
            </dl>
            <p class="mt-3 text-xs text-gray-500">Informasi mesin berikut merupakan snapshot terakhir, bukan status live.</p>
        </x-filament::section>

        <x-filament::section heading="Informasi Mesin">
            @if ($information)
                <dl class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                    @foreach (['device_name' => 'Nama mesin', 'serial_number' => 'Serial number', 'platform' => 'Platform', 'firmware' => 'Firmware', 'version' => 'Versi', 'os_version' => 'OS', 'pin_width' => 'Lebar PIN', 'machine_time' => 'Jam mesin saat dibaca'] as $field => $label)
                        <div><dt class="text-gray-500">{{ $label }}</dt><dd class="font-medium">{{ $information[$field] ?? 'Tidak tersedia' }}</dd></div>
                    @endforeach
                </dl>
            @else
                <p class="text-sm text-gray-500">Belum ada informasi tersimpan. Pilih "Baca Info Mesin" pada bagian operasi di bawah untuk memperbarui.</p>
            @endif
        </x-filament::section>

        <x-filament::section heading="Pengamanan Operasional">
            <p class="text-sm text-gray-600 dark:text-gray-400">Pembacaan pengguna dan absensi tidak menulis atau menghapus data pada mesin. Perintah disalurkan melalui queue fingerprint khusus; penulisan biometrik dan penghapusan log mesin tidak disediakan.</p>
            @unless ($sensitive)
                <p class="mt-2 text-sm text-warning-600">Data pengguna/absensi memerlukan permission view_fingerprint_data atau role super_admin.</p>
            @endunless
            @if (! \App\Support\FingerprintDevice::canRestart($device, auth()->user()))
                <p class="mt-2 text-sm text-gray-500">Mode hanya baca. Operator membutuhkan permission update_device untuk menjalankan operasi.</p>
            @endif
        </x-filament::section>
    </div>

    @if ($sensitive)
        <div x-show="tab === 'users'" x-cloak class="space-y-3">
            <x-filament::section heading="Pengguna Mesin (Snapshot)">
                <p class="mb-3 text-sm text-gray-500">Total tersimpan: {{ number_format($userCount) }}. Sinkron terakhir: {{ $snapshot?->users_synced_at ?? 'Belum pernah' }}. Pencarian berlaku untuk maksimal 100 baris yang ditampilkan; unduhan XLSX mencakup seluruh pengguna tersimpan. Kata sandi dan template biometrik tidak disimpan.</p>
                <div class="mb-3 flex flex-wrap items-center gap-3">
                    <input type="search" x-model="searchUser" placeholder="Cari ID atau nama dari hasil ini..."
                        class="min-w-0 flex-1 rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900"
                        aria-label="Cari pengguna fingerprint" />
                    <x-filament::button type="button" color="gray" size="sm" icon="heroicon-o-arrow-down-tray"
                        wire:click="exportFingerprintUsers({{ (int) $device->getKey() }})"
                        wire:loading.attr="disabled"
                        wire:target="exportFingerprintUsers">Unduh seluruh XLSX</x-filament::button>
                </div>
                <div class="max-h-72 overflow-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="sticky top-0 bg-white dark:bg-gray-900"><tr class="border-b dark:border-gray-700"><th class="p-2">ID</th><th class="p-2">UID</th><th class="p-2">Nama</th><th class="p-2">Role</th><th class="p-2">Terakhir Terbaca</th></tr></thead>
                        <tbody>
                            @forelse ($users as $user)
                                <tr data-search="{{ mb_strtolower($user->user_id.' '.$user->name) }}"
                                    x-show="$el.dataset.search.includes(searchUser.toLowerCase())"
                                    class="border-b dark:border-gray-800">
                                    <td class="p-2">{{ $user->user_id }}</td><td class="p-2">{{ $user->uid }}</td><td class="p-2">{{ $user->name }}</td><td class="p-2">{{ $user->role }}</td><td class="p-2">{{ $user->last_seen_at }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="p-3 text-gray-500">Belum ada pengguna tersinkron.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        </div>

        <div x-show="tab === 'attendance'" x-cloak class="space-y-3">
            <x-filament::section heading="Log Absensi Mesin">
                <p class="mb-3 text-sm text-gray-500">Total tersimpan: {{ number_format($attendanceCount) }}. Sinkron terakhir: {{ $snapshot?->attendance_synced_at ?? 'Belum pernah' }}. Menampilkan 100 event terbaru; waktu sesuai jam mesin. Ekspor CSV mencakup semua event.</p>
                <div class="mb-3 flex flex-wrap items-center gap-3">
                    <input type="search" x-model="searchAttendance" placeholder="Cari ID di hasil terbaru..."
                        class="rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900"
                        aria-label="Cari log fingerprint" />
                    <x-filament::button type="button" color="gray" size="sm" icon="heroicon-o-arrow-down-tray"
                        wire:click="exportFingerprintAttendance({{ (int) $device->getKey() }})"
                        wire:loading.attr="disabled">Ekspor seluruh CSV</x-filament::button>
                </div>
                <div class="max-h-72 overflow-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="sticky top-0 bg-white dark:bg-gray-900"><tr class="border-b dark:border-gray-700"><th class="p-2">ID Pengguna</th><th class="p-2">UID</th><th class="p-2">Waktu Mesin</th><th class="p-2">State</th><th class="p-2">Type</th></tr></thead>
                        <tbody>
                            @forelse ($attendance as $event)
                                <tr data-search="{{ mb_strtolower($event->user_id) }}"
                                    x-show="$el.dataset.search.includes(searchAttendance.toLowerCase())"
                                    class="border-b dark:border-gray-800">
                                    <td class="p-2">{{ $event->user_id }}</td><td class="p-2">{{ $event->uid }}</td><td class="p-2">{{ $event->event_at }}</td><td class="p-2">{{ $event->state }}</td><td class="p-2">{{ $event->type }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="p-3 text-gray-500">Belum ada log tersinkron.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        </div>
    @endif

    <div x-show="tab === 'history'" x-cloak>
        <x-filament::section heading="Riwayat Operasi">
            <p class="mb-3 text-xs text-gray-500">Queue khusus fingerprint memproses perintah di belakang layar. Status dan hasil diperbarui otomatis setiap 10 detik selama modal terbuka.</p>
            <div class="max-h-72 overflow-auto">
                <table class="w-full text-left text-sm">
                    <thead class="sticky top-0 bg-white dark:bg-gray-900"><tr class="border-b dark:border-gray-700"><th class="p-2">Dikirim</th><th class="p-2">Operasi</th><th class="p-2">Status</th><th class="p-2">Jumlah</th><th class="p-2">Keterangan</th></tr></thead>
                    <tbody>
                        @forelse ($runs as $run)
                            <tr class="border-b dark:border-gray-800"><td class="p-2">{{ $run->created_at }}</td><td class="p-2">{{ $run->operation }}</td><td class="p-2">{{ $run->status }}</td><td class="p-2">{{ $run->records }}</td><td class="p-2">{{ $run->error ?? '-' }}</td></tr>
                        @empty
                            <tr><td colspan="5" class="p-3 text-gray-500">Belum ada operasi tercatat.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</div>
