<?php

namespace App\Filament\Pages;

use App\Filament\Resources\DeviceResource;
use App\Filament\Widgets\DataQualityOverview;
use App\Services\CmdbDataQualityService;
use Filament\Actions;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists\Infolist;
use Filament\Pages\Page;

class DataQuality extends Page implements HasInfolists
{
    use InteractsWithInfolists;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Laporan & Audit';

    protected static ?string $navigationLabel = 'Kualitas Data';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Kualitas Data CMDB';

    protected static string $view = 'filament.pages.data-quality';

    public array $issues = [];

    public function mount(): void
    {
        $this->loadIssues();
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->state($this->infolistState())
            ->schema([
                $this->issueSection(
                    severity: 'danger',
                    heading: 'Kritis',
                    description: 'Konflik dan inkonsistensi yang dapat memengaruhi integritas CMDB, IPAM, keamanan, atau topology.',
                    icon: 'heroicon-o-exclamation-circle',
                ),
                $this->issueSection(
                    severity: 'warning',
                    heading: 'Peringatan',
                    description: 'Temuan operasional yang perlu ditindaklanjuti agar monitoring dan lifecycle tetap akurat.',
                    icon: 'heroicon-o-exclamation-triangle',
                ),
                $this->issueSection(
                    severity: 'info',
                    heading: 'Kelengkapan Data',
                    description: 'Temuan kelengkapan yang meningkatkan kualitas identitas aset dan dokumentasi infrastruktur.',
                    icon: 'heroicon-o-information-circle',
                ),
            ]);
    }

    protected function getHeaderWidgets(): array
    {
        return [
            DataQualityOverview::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('devices')
                ->label('Buka Perangkat')
                ->icon('heroicon-o-computer-desktop')
                ->url(DeviceResource::getUrl('index')),
            Actions\Action::make('refresh')
                ->label('Muat Ulang')
                ->icon('heroicon-o-arrow-path')
                ->url(static::getUrl()),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_any_device') ?? false;
    }

    private function loadIssues(): void
    {
        $this->issues = collect(app(CmdbDataQualityService::class)->issues())
            ->map(fn (array $issue): array => [
                ...$issue,
                'device_url' => DeviceResource::getUrl('index'),
            ])
            ->all();
    }

    private function infolistState(): array
    {
        return collect(['danger', 'warning', 'info'])
            ->mapWithKeys(fn (string $severity): array => [
                $severity => collect($this->issues)
                    ->where('severity', $severity)
                    ->where('count', '>', 0)
                    ->values()
                    ->all(),
            ])
            ->all();
    }

    private function issueSection(
        string $severity,
        string $heading,
        string $description,
        string $icon,
    ): Section {
        $issues = collect($this->issues)
            ->where('severity', $severity)
            ->where('count', '>', 0)
            ->values();

        return Section::make($heading)
            ->description($description)
            ->icon($icon)
            ->iconColor($severity)
            ->collapsible()
            ->visible($issues->isNotEmpty())
            ->schema([
                RepeatableEntry::make($severity)
                    ->hiddenLabel()
                    ->schema([
                        TextEntry::make('label')
                            ->label('Pemeriksaan')
                            ->weight('semibold')
                            ->icon($icon)
                            ->iconColor($severity),
                        TextEntry::make('count')
                            ->label('Jumlah Temuan')
                            ->badge()
                            ->color($severity)
                            ->formatStateUsing(fn ($state): string => number_format((int) $state)),
                        TextEntry::make('description')
                            ->label('Penjelasan')
                            ->columnSpanFull(),
                        TextEntry::make('examples')
                            ->label('Contoh')
                            ->bulleted()
                            ->listWithLineBreaks()
                            ->limitList(5)
                            ->expandableLimitedList()
                            ->placeholder('Tidak ada contoh detail')
                            ->columnSpanFull(),
                        TextEntry::make('device_url')
                            ->label('Tindakan')
                            ->formatStateUsing(fn (): string => 'Buka daftar perangkat')
                            ->url(fn (?string $state): ?string => $state)
                            ->icon('heroicon-o-arrow-top-right-on-square')
                            ->color('primary')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }
}
