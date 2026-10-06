<?php

namespace App\Filament\Pages;

use App\Filament\Resources\DeviceResource;
use App\Services\CmdbDataQualityService;
use Filament\Pages\Page;

class DataQuality extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationGroup = 'Laporan & Audit';
    protected static ?string $navigationLabel = 'Kualitas Data';
    protected static ?int $navigationSort = 1;
    protected static ?string $title = 'Kualitas Data CMDB';
    protected static string $view = 'filament.pages.data-quality';

    public array $issues = [];

    public function mount(): void
    {
        $this->issues = collect(app(CmdbDataQualityService::class)->issues())
            ->map(fn (array $issue): array => [
                ...$issue,
                'device_url' => DeviceResource::getUrl('index'),
            ])
            ->all();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_any_device') ?? false;
    }
}
