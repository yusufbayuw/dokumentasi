<div class="space-y-3">
    <p class="text-sm text-gray-500 dark:text-gray-400">
        Tipe SNMP: {{ $oid->value_type ?? 'Tidak diketahui' }}
        | Encoding: {{ $oid->encoding }}
        | Panjang asli: {{ number_format($oid->raw_bytes) }} byte
        @if ($oid->truncated) | Terpotong demi keamanan @endif
    </p>
    <pre class="max-h-[60vh] overflow-auto whitespace-pre-wrap break-all rounded-lg bg-gray-100 p-4 text-xs text-gray-900 dark:bg-gray-800 dark:text-gray-100">{{ $oid->displayValue() }}</pre>
</div>
