<?php

namespace App\Livewire;

use App\Models\NetworkInterfaceSample;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class NetworkInterfaceHistoryTable extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public int $interfaceId = 0;

    public function mount(int $interfaceId): void
    {
        $this->authorizeViewing();
        $this->interfaceId = $interfaceId;
    }

    private function authorizeViewing(): void
    {
        abort_unless(
            config('network_quality.enabled', false)
                && config('network_quality.snmp.enabled', false)
                && (auth()->user()?->can('view_any_device') ?? false),
            403,
        );
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(NetworkInterfaceSample::query()
                ->where('device_interface_id', $this->interfaceId))
            ->columns([
                Tables\Columns\TextColumn::make('checked_at')
                    ->label('Waktu')->dateTime('d/m/Y H:i:s')->sortable(),
                Tables\Columns\TextColumn::make('snmp_version')
                    ->label('Versi')->badge()
                    ->formatStateUsing(fn (string $state): string =>
                        $state === '2c' ? 'SNMPv2c' : 'SNMPv3'),
                Tables\Columns\TextColumn::make('status')->label('Port')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'up' => 'success', 'down' => 'danger', default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('rx_mbps')
                    ->label('RX')->suffix(' Mbps')->placeholder('-'),
                Tables\Columns\TextColumn::make('tx_mbps')
                    ->label('TX')->suffix(' Mbps')->placeholder('-'),
                Tables\Columns\TextColumn::make('in_errors_delta')
                    ->label('Error RX')->placeholder('-'),
                Tables\Columns\TextColumn::make('out_errors_delta')
                    ->label('Error TX')->placeholder('-'),
                Tables\Columns\TextColumn::make('error_code')
                    ->label('Error Polling')->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('checked_at', 'desc')
            ->paginated([10, 25, 50])
            ->emptyStateHeading('Belum ada histori untuk interface ini');
    }

    public function render(): View
    {
        $this->authorizeViewing();

        return view('livewire.network-interface-history-table');
    }
}
