<div class="space-y-4">
    <x-filament::section>
        <x-slot name="heading">Petunjuk Korelasi</x-slot>
        <p class="text-sm text-gray-700 dark:text-gray-300">{{ $incident->correlation_hint }}</p>
        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
            Ini petunjuk pemeriksaan, bukan penetapan akar masalah. Periksa kondisi perangkat dan jalur secara langsung sebelum mengambil tindakan.
        </p>
    </x-filament::section>
    <x-filament::section>
        <x-slot name="heading">Histori Penanganan</x-slot>
        <ul class="space-y-2 text-sm text-gray-700 dark:text-gray-300">
            @forelse ($events as $event)
                <li>
                    <span class="font-semibold">{{ $event->created_at?->format('d/m/Y H:i') }}</span>
                    &mdash; {{ $event->message ?? $event->event_type }}
                </li>
            @empty
                <li>Belum ada aktivitas penanganan.</li>
            @endforelse
        </ul>
    </x-filament::section>
</div>
