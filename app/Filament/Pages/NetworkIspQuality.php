<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\NetworkIspOverview;
use App\Models\NetworkProbeLocation;
use App\Services\NetworkQualitySnapshot;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;

class NetworkIspQuality extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';
    protected static ?string $navigationGroup = 'Infrastruktur';
    protected static ?string $navigationLabel = 'Kualitas per ISP';
    protected static ?int $navigationSort = 9;
    protected static ?string $title = 'Kualitas Jaringan per ISP & Lokasi';
    protected static string $view = 'filament.pages.network-isp-quality';

    public static function canAccess(): bool
    {
        return config('network_quality.enabled', false)
            && config('network_quality.remote.enabled', false)
            && (auth()->user()?->can('view_any_device') ?? false);
    }

    protected function getHeaderWidgets(): array
    {
        return [NetworkIspOverview::class];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(NetworkQualitySnapshot::query()
                ->whereNotNull('probe_location_id')
                ->with('probeLocation.unit'))
            ->columns([
                Tables\Columns\TextColumn::make('isp_name')->label('ISP')->badge()->sortable(),
                Tables\Columns\TextColumn::make('probeLocation.name')
                    ->label('Lokasi')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('probeLocation.unit.nama')
                    ->label('Unit')->placeholder('-'),
                Tables\Columns\TextColumn::make('target_name')
                    ->label('Target')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('probe_type')->label('Metode')->badge(),
                Tables\Columns\TextColumn::make('quality')->label('Kualitas')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'good' => 'success', 'degraded' => 'warning', 'bad' => 'danger', default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('latency_ms')
                    ->label('RTT')->suffix(' ms')->placeholder('-')->sortable(),
                Tables\Columns\TextColumn::make('packet_loss_pct')
                    ->label('Loss')->suffix('%')->placeholder('-')->sortable(),
                Tables\Columns\TextColumn::make('checked_at')
                    ->label('Waktu')->dateTime('d/m/Y H:i:s')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('isp_name')
                    ->label('ISP')
                    ->options(fn (): array => NetworkProbeLocation::query()->orderBy('isp_name')
                        ->distinct()->pluck('isp_name', 'isp_name')->all())
                    ->searchable(),
                Tables\Filters\SelectFilter::make('probe_location_id')
                    ->label('Lokasi')->relationship('probeLocation', 'name')
                    ->searchable()->preload(),
            ])
            ->defaultSort('checked_at', 'desc')
            ->paginated([10, 25, 50])
            ->emptyStateHeading('Belum ada hasil ISP')
            ->emptyStateDescription('Aktifkan lokasi probe dan jalankan agen remote pada jaringan yang ingin dibandingkan.');
    }
}
