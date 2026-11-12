<?php

namespace App\Filament\Pages;

use App\Filament\Resources\NetworkQualityTargetResource;
use App\Filament\Widgets\NetworkQualityLatencyChart;
use App\Filament\Widgets\NetworkQualityOverview;
use App\Models\NetworkQualityMeasurement;
use App\Models\NetworkProbeLocation;
use App\Models\Unit;
use App\Services\NetworkQualitySnapshot;
use Filament\Actions;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;

class NetworkQuality extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-signal';
    protected static ?string $navigationGroup = 'Infrastruktur';
    protected static ?string $navigationLabel = 'Kualitas Jaringan';
    protected static ?int $navigationSort = 5;
    protected static ?string $title = 'Kualitas Jaringan';
    protected static string $view = 'filament.pages.network-quality';

    public static function canAccess(): bool
    {
        return config('network_quality.enabled', false)
            && (auth()->user()?->can('view_any_device') ?? false);
    }

    protected function getHeaderWidgets(): array
    {
        return [NetworkQualityOverview::class, NetworkQualityLatencyChart::class];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('qualityDetails')
                ->label('Lihat Rincian')
                ->icon('heroicon-o-magnifying-glass-circle')
                ->modalHeading(fn (array $arguments): string => match (NetworkQualitySnapshot::validQuality($arguments['quality'] ?? 'all')) {
                    'good' => 'Rincian Kualitas Baik',
                    'degraded' => 'Rincian Kualitas Menurun',
                    'bad' => 'Rincian Kualitas Buruk',
                    'unknown' => 'Rincian Belum Diketahui',
                    default => 'Rincian Semua Target',
                })
                ->modalContent(fn (array $arguments): View => view('filament.pages.modals.network-quality-details', [
                    'quality' => NetworkQualitySnapshot::validQuality($arguments['quality'] ?? 'all'),
                ]))
                ->modalWidth('7xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Tutup'),
            Actions\Action::make('probeLocations')
                ->label('Lokasi Probe')
                ->icon('heroicon-o-map-pin')
                ->url(\App\Filament\Resources\NetworkProbeLocationResource::getUrl('index'))
                ->visible(fn (): bool => \App\Filament\Resources\NetworkProbeLocationResource::canViewAny()),
            Actions\Action::make('ispQuality')
                ->label('Kualitas per ISP')
                ->icon('heroicon-o-chart-pie')
                ->url(NetworkIspQuality::getUrl())
                ->visible(fn (): bool => NetworkIspQuality::canAccess()),
            Actions\Action::make('interfaces')
                ->label('Traffic Interface')
                ->icon('heroicon-o-chart-bar')
                ->url(NetworkInterfaces::getUrl())
                ->visible(fn (): bool => NetworkInterfaces::canAccess()),
            Actions\Action::make('incidents')
                ->label('Insiden Jaringan')
                ->icon('heroicon-o-exclamation-triangle')
                ->url(\App\Filament\Resources\NetworkIncidentResource::getUrl('index'))
                ->visible(fn (): bool => \App\Filament\Resources\NetworkIncidentResource::canViewAny()),
            Actions\Action::make('sla')
                ->label('Laporan Ketersediaan')
                ->icon('heroicon-o-document-chart-bar')
                ->url(NetworkSlaReport::getUrl())
                ->visible(fn (): bool => NetworkSlaReport::canAccess()),
            Actions\Action::make('targets')
                ->label('Target Layanan')
                ->icon('heroicon-o-adjustments-horizontal')
                ->url(NetworkQualityTargetResource::getUrl('index'))
                ->visible(fn (): bool => NetworkQualityTargetResource::canViewAny()),
        ];
    }

    #[On('open-network-quality-details')]
    public function openNetworkQualityDetails(string $quality = 'all'): void
    {
        abort_unless(static::canAccess(), 403);

        $this->mountAction('qualityDetails', [
            'quality' => NetworkQualitySnapshot::validQuality($quality),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(NetworkQualityMeasurement::query())
            ->columns([
                Tables\Columns\TextColumn::make('checked_at')
                    ->label('Waktu')->dateTime('d/m/Y H:i:s')->sortable(),
                Tables\Columns\TextColumn::make('target_name')
                    ->label('Target')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('probeLocation.name')
                    ->label('Lokasi Probe')->placeholder('Server Utama')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('isp_name')
                    ->label('ISP')->placeholder('Belum ditetapkan')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('probe_type')
                    ->label('Metode')->badge(),
                Tables\Columns\TextColumn::make('address')
                    ->label('Alamat')->searchable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('status')
                    ->label('Konektivitas')->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'up' => 'Terjangkau',
                        'down' => 'Tidak terjangkau',
                        default => 'Gagal diukur',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'up' => 'success',
                        'down' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('quality')
                    ->label('Kualitas')->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'good' => 'Baik',
                        'degraded' => 'Menurun',
                        'bad' => 'Buruk',
                        default => 'Belum diketahui',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'good' => 'success',
                        'degraded' => 'warning',
                        'bad' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('latency_ms')
                    ->label('RTT / Respons')->suffix(' ms')->placeholder('-')->sortable(),
                Tables\Columns\TextColumn::make('packet_loss_pct')
                    ->label('Packet Loss')->suffix('%')->placeholder('-')->sortable(),
                Tables\Columns\TextColumn::make('rtt_variation_ms')
                    ->label('Variasi RTT')->suffix(' ms')->placeholder('-')->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('error_code')
                    ->label('Kode Error')->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('probe_location_id')
                    ->label('Lokasi Probe')
                    ->relationship('probeLocation', 'name')
                    ->searchable()->preload()
                    ->visible(fn (): bool => config('network_quality.remote.enabled', false)),
                Tables\Filters\SelectFilter::make('isp_name')
                    ->label('ISP')
                    ->options(fn (): array => NetworkProbeLocation::query()->orderBy('isp_name')
                        ->distinct()->pluck('isp_name', 'isp_name')->all())
                    ->searchable()
                    ->visible(fn (): bool => config('network_quality.remote.enabled', false)),
                Tables\Filters\SelectFilter::make('probe_type')
                    ->label('Metode')->options([
                        'icmp' => 'Ping', 'dns' => 'DNS', 'tcp' => 'TCP', 'https' => 'HTTPS',
                    ]),
                Tables\Filters\SelectFilter::make('quality')
                    ->label('Kualitas')->options([
                        'good' => 'Baik', 'degraded' => 'Menurun',
                        'bad' => 'Buruk', 'unknown' => 'Belum diketahui',
                    ]),
                Tables\Filters\SelectFilter::make('unit_id')
                    ->label('Unit')
                    ->options(fn (): array => Unit::query()->orderBy('nama')->pluck('nama', 'id')->all())
                    ->searchable()
                    ->query(function (Builder $query, array $data): Builder {
                        $id = $data['value'] ?? null;

                        return $query->when($id, fn (Builder $q) => $q->whereHas(
                            'device', fn (Builder $d) => $d->where('owner_unit_id', $id)
                        ));
                    }),
            ])
            ->defaultSort('checked_at', 'desc')
            ->paginated([10, 25, 50, 100])
            ->actions([])
            ->bulkActions([]);
    }
}
