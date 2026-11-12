<?php

namespace App\Livewire;

use App\Models\NetworkSnmpOid;
use App\Models\NetworkSnmpSnapshot;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class NetworkSnmpOidTable extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public int $snapshotId = 0;

    public function mount(int $snapshotId): void
    {
        $this->authorizeViewer();
        abort_unless(NetworkSnmpSnapshot::query()->whereKey($snapshotId)->exists(), 404);
        $this->snapshotId = $snapshotId;
    }

    protected function authorizeViewer(): void
    {
        abort_unless(
            (bool) config('network_quality.enabled', false)
                && (bool) config('network_quality.snmp.enabled', false)
                && (auth()->user()?->can('view_any_device') ?? false)
                && (auth()->user()?->can('update_device') ?? false),
            403
        );
    }

    public function table(Table $table): Table
    {
        $this->authorizeViewer();

        return $table
            ->query(NetworkSnmpOid::query()
                ->where('network_snmp_snapshot_id', $this->snapshotId))
            ->columns([
                Tables\Columns\TextColumn::make('oid')
                    ->label('OID Numerik')->searchable()->wrap()->copyable(),
                Tables\Columns\TextColumn::make('value_type')
                    ->label('Tipe')->placeholder('-')->toggleable(),
                Tables\Columns\TextColumn::make('display_value')
                    ->label('Nilai')
                    ->state(fn (NetworkSnmpOid $record): string =>
                        mb_strimwidth($record->displayValue(), 0, 240, '…'))
                    ->wrap()
                    ->placeholder('(kosong)'),
                Tables\Columns\IconColumn::make('truncated')
                    ->label('Dibatasi')->boolean()->toggleable(),
                Tables\Columns\TextColumn::make('raw_bytes')
                    ->label('Byte Asli')->numeric()->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\Action::make('fullValue')
                    ->label('Nilai Lengkap')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (NetworkSnmpOid $record): string => 'Nilai OID '.$record->oid)
                    ->modalContent(fn (NetworkSnmpOid $record): View => view(
                        'filament.pages.modals.network-snmp-oid-value',
                        ['oid' => $record]
                    ))
                    ->modalSubmitAction(false)->modalWidth('4xl')
                    ->modalCancelActionLabel('Tutup'),
            ])
            ->defaultSort('id')
            ->paginated([10, 25, 50, 100])
            ->emptyStateHeading('Belum ada nilai OID pada snapshot ini');
    }

    public function render(): View
    {
        $this->authorizeViewer();

        return view('livewire.network-snmp-oid-table');
    }
}
