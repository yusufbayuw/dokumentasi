<div class="space-y-4">
    <p class="text-sm text-gray-600 dark:text-gray-400">
        Kecepatan RX/TX dihitung dari dua sampel counter SNMPv3; angka kosong pada sampel pertama, counter reset, atau polling gagal adalah hal normal.
    </p>
    @livewire('network-interface-traffic-chart', ['interfaceId' => $interfaceId], key('snmp-traffic-chart-'.$interfaceId))
    @livewire('network-interface-history-table', ['interfaceId' => $interfaceId], key('snmp-history-table-'.$interfaceId))
</div>
