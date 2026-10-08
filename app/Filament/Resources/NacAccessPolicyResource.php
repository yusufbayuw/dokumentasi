<?php

namespace App\Filament\Resources;

use App\Models\NacAccessPolicy;
use App\Filament\Resources\NacAccessPolicyResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class NacAccessPolicyResource extends Resource
{
    protected static ?string $model = NacAccessPolicy::class;
    protected static ?string $navigationGroup = 'Network Access Control';
    protected static ?string $navigationLabel = 'Access Policies';
    protected static ?string $modelLabel = 'Access Policies';
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('super_admin') === true;
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('nac_network_id')->label('Jaringan')->relationship('network', 'name')->required()->searchable()->preload(),
            Forms\Components\TextInput::make('name')->label('Nama Kebijakan')->required()->maxLength(255),
            Forms\Components\Select::make('role_name')->label('Role pengguna')->options(fn (): array => \Spatie\Permission\Models\Role::query()->pluck('name', 'name')->all())->searchable()->placeholder('Semua pengguna aktif (fallback)'),
            Forms\Components\TextInput::make('vlan_id')->label('VLAN ID')->required()->integer()->minValue(1)->maxValue(4094),
            Forms\Components\TextInput::make('priority')->label('Prioritas (tinggi menang)')->required()->integer()->minValue(0)->default(100),
            Forms\Components\Toggle::make('enabled')->label('Aktif')->default(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('network.name')->label('Jaringan'),
            Tables\Columns\TextColumn::make('name')->searchable(),
            Tables\Columns\TextColumn::make('role_name')->label('Role')->placeholder('Semua'),
            Tables\Columns\TextColumn::make('vlan_id')->label('VLAN'),
            Tables\Columns\TextColumn::make('priority')->label('Prioritas'),
            Tables\Columns\IconColumn::make('enabled')->boolean(),
        ])
            ->actions([Tables\Actions\EditAction::make()])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageNacAccessPolicys::route('/')];
    }
}
