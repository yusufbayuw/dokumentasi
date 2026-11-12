@php($advice = \App\Services\NetworkQualityAdvice::forCategory($quality))
<div class="space-y-4">
    <x-filament::section>
        <x-slot name="heading">{{ $advice['heading'] }}</x-slot>
        <p class="text-sm text-gray-600 dark:text-gray-400">{{ $advice['description'] }}</p>
        <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-gray-600 dark:text-gray-400">
            @foreach ($advice['steps'] as $step)
                <li>{{ $step }}</li>
            @endforeach
        </ul>
    </x-filament::section>

    @livewire('network-quality-detail-table', ['quality' => $quality], key('network-quality-detail-'.$quality))
</div>
