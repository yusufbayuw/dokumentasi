<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class Diagram extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Diagram Jaringan';
    protected static string $view = 'filament.pages.diagram';
}
