<?php

namespace App\Filament\Resources;

use App\Models\NacCertificate;
use App\Filament\Resources\NacCertificateResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class NacCertificateResource extends Resource
{
    protected static ?string $model = NacCertificate::class;
    protected static ?string $navigationGroup = 'Network Access Control';
    protected static ?string $navigationLabel = 'Certificates';
    protected static ?string $modelLabel = 'Certificates';
    protected static ?string $navigationIcon = 'heroicon-o-key';

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
            Forms\Components\Select::make('nac_client_id')->label('Network Client')->relationship('client', 'label')->required()->searchable()->preload(),
            Forms\Components\TextInput::make('issuer')->label('Issuer DN (exact match)')->required()->maxLength(512)->live(onBlur: true)->afterStateUpdated(fn (Forms\Set $set, ?string $state) => $set('issuer', trim((string) $state)))->dehydrateStateUsing(fn (?string $state): string => trim((string) $state)),
            Forms\Components\TextInput::make('serial_number')->label('Serial number (hex / exact)')->required()->maxLength(128)->live(onBlur: true)->afterStateUpdated(fn (Forms\Set $set, ?string $state) => $set('serial_number', strtoupper(trim((string) $state))))->dehydrateStateUsing(fn (?string $state): string => strtoupper(trim((string) $state)))->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule, Forms\Get $get) => $rule->where('issuer', trim((string) $get('issuer')))),
            Forms\Components\DateTimePicker::make('valid_from')->label('Valid from')->required()->seconds(false),
            Forms\Components\DateTimePicker::make('valid_until')->label('Valid until')->required()->after('valid_from')->seconds(false),
            Forms\Components\DateTimePicker::make('revoked_at')->label('Revoked at (set to revoke)')->seconds(false)->helperText('Revocation in NAC is not a substitute for PKI CRL/OCSP.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('client.label')->label('Client')->searchable(),
            Tables\Columns\TextColumn::make('issuer')->limit(30),
            Tables\Columns\TextColumn::make('serial_number')->searchable(),
            Tables\Columns\TextColumn::make('valid_until')->dateTime(),
            Tables\Columns\TextColumn::make('revoked_at')->label('Revoked')->dateTime()->placeholder('-'),
        ])
            ->actions([Tables\Actions\EditAction::make()])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageNacCertificates::route('/')];
    }
}
