<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\NetworkSlaOverview;
use App\Models\NetworkSlaDaily;
use Filament\Forms;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class NetworkSlaReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static ?string $navigationGroup = 'Infrastruktur';
    protected static ?string $navigationLabel = 'Laporan Ketersediaan';
    protected static ?int $navigationSort = 11;
    protected static ?string $title = 'Laporan Ketersediaan Teramati';
    protected static string $view = 'filament.pages.network-sla-report';

    public static function canAccess(): bool
    {
        return config('network_quality.enabled', false)
            && config('network_quality.incidents.enabled', false)
            && (auth()->user()?->can('view_any_device') ?? false);
    }

    protected function getHeaderWidgets(): array
    {
        return [NetworkSlaOverview::class];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(NetworkSlaDaily::query()->with(['device', 'target']))
            ->columns([
                Tables\Columns\TextColumn::make('report_date')
                    ->label('Tanggal')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('monitor_key')
                    ->label('Target / Lokasi Probe')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('device.nama')
                    ->label('Perangkat')->placeholder('-')->toggleable(),
                Tables\Columns\TextColumn::make('target.name')
                    ->label('Layanan')->placeholder('-')->toggleable(),
                Tables\Columns\TextColumn::make('total_samples')
                    ->label('Sampel')->sortable(),
                Tables\Columns\TextColumn::make('up_samples')
                    ->label('Up')->sortable(),
                Tables\Columns\TextColumn::make('down_samples')
                    ->label('Down')->sortable(),
                Tables\Columns\TextColumn::make('error_samples')
                    ->label('Error Monitor')->sortable(),
                Tables\Columns\TextColumn::make('observed_availability_pct')
                    ->label('Terjangkau Saat Diperiksa')->suffix('%')->placeholder('N/A')
                    ->sortable()
                    ->color(fn ($state): string => $state === null ? 'gray'
                        : ((float) $state >= 99 ? 'success' : 'warning')),
                Tables\Columns\TextColumn::make('degraded_samples')
                    ->label('Menurun')->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('bad_samples')
                    ->label('Buruk')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('date_range')
                    ->label('Rentang Tanggal')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Dari'),
                        Forms\Components\DatePicker::make('until')->label('Sampai'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('report_date', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('report_date', '<=', $date))),
            ])
            ->bulkActions([ExportBulkAction::make()])
            ->defaultSort('report_date', 'desc')
            ->paginated([10, 25, 50, 100])
            ->emptyStateHeading('Belum ada rekap harian')
            ->emptyStateDescription('Rekap sampel dijalankan tiap jam saat modul insiden aktif. Ini bukan SLA kontraktual.');
    }
}
