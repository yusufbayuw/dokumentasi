<x-filament-panels::page>
    <x-filament::section>
        <p class="text-sm text-gray-600 dark:text-gray-300">
            Pengumpulan SNMP dilakukan per perangkat, bukan per interface. Sistem menyimpan
            OID numerik, tipe dan nilai mentah yang dapat dibaca dari perangkat.
            Status "Sebagian" berarti batas OID/nilai/waktu tercapai atau terjadi kegagalan
            saat membaca sebagian data. Ini bukan indikator bahwa perangkat mati.
        </p>
    </x-filament::section>
    {{ $this->table }}
</x-filament-panels::page>
