<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class Diagram extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-share';
    protected static ?string $navigationLabel = 'Diagram Jaringan';
    protected static ?int $navigationSort = 5;
    protected static ?string $navigationGroup = 'Infrastruktur';
    protected static string $view = 'filament.pages.diagram';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->can('view_any_topology') || $user->can('view_any_device');
    }
}
