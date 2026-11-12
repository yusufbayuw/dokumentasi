<div class="space-y-3">
    <x-filament::section>
        <x-slot name="heading">Indikasi hasil pengukuran</x-slot>
        <p class="text-sm text-gray-700 dark:text-gray-300">
            {{ $advice['summary'] }}
        </p>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Langkah pemeriksaan yang disarankan</x-slot>
        <ul class="list-disc space-y-2 pl-5 text-sm text-gray-700 dark:text-gray-300">
            @foreach ($advice['steps'] as $step)
                <li>{{ $step }}</li>
            @endforeach
        </ul>
        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
            Rekomendasi bersifat indikatif dan hanya baca. Tidak ada perubahan otomatis pada perangkat atau konfigurasi jaringan.
        </p>
    </x-filament::section>
</div>
