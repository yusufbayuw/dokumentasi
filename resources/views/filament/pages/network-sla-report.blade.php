<x-filament-panels::page>
    <x-filament::section>
        <p class="text-sm text-gray-600 dark:text-gray-400">
            Angka ini adalah rasio <strong>sampel terjangkau</strong> terhadap sampel up/down, bukan persentase ketersediaan waktu atau SLA kontraktual.
            Error monitoring tidak otomatis dianggap downtime. Pilih baris untuk ekspor XLSX.
        </p>
    </x-filament::section>
    {{ $this->table }}
</x-filament-panels::page>
