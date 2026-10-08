<?php

namespace App\Filament\Resources;

use App\Models\NacAuthEvent;
use App\Filament\Resources\NacAuthEventResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class NacAuthEventResource extends Resource
{
    protected static ?string $model = NacAuthEvent::class;
    protected static ?string $navigationGroup = 'Network Access Control';
    protected static ?string $navigationLabel = 'Authentication Logs';
    protected static ?string $modelLabel = 'Authentication Logs';
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('super_admin') === true;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([

        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('occurred_at')->label('Waktu')->dateTime()->sortable(),
            Tables\Columns\TextColumn::make('network.ssid')->label('SSID')->placeholder('-'),
            Tables\Columns\TextColumn::make('client.label')->label('Client')->placeholder('-'),
            Tables\Columns\TextColumn::make('decision')->badge()->color(fn (string $state): string => $state === 'allow' ? 'success' : 'danger'),
            Tables\Columns\TextColumn::make('reason')->label('Alasan'),
            Tables\Columns\TextColumn::make('vlan_id')->label('VLAN')->placeholder('-'),
            Tables\Columns\TextColumn::make('nas_identifier')->label('NAS')->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\TextColumn::make('calling_station_id')->label('Station')->toggleable(isToggledHiddenByDefault: true),
        ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageNacAuthEvents::route('/')];
    }
}
