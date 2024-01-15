<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use App\Models\DeviceLink;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\DeviceLinkResource\Pages;
use App\Filament\Resources\DeviceLinkResource\RelationManagers;
use Saade\FilamentAdjacencyList\Forms\Components\AdjacencyList;

class DeviceLinkResource extends Resource
{
    protected static ?string $model = DeviceLink::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                AdjacencyList::make('subjects')
                    ->labelKey('deviceDevice.nama')        // defaults to 'label'
                    ->childrenKey('children_id')   // defaults to 'children'
                    ->form([
                        Forms\Components\Select::make('device_id')
                                ->relationship('deviceDevice', 'nama')
                                ->searchable()
                                ->required(),
                    ])
                /* Forms\Components\TextInput::make('device_id')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('children_id')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('label')
                    ->maxLength(255),
                Forms\Components\TextInput::make('children')
                    ->maxLength(255),
                Forms\Components\TextInput::make('keterangan')
                    ->maxLength(255), */
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('device_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('children_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('label')
                    ->searchable(),
                Tables\Columns\TextColumn::make('children')
                    ->searchable(),
                Tables\Columns\TextColumn::make('keterangan')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDeviceLinks::route('/'),
            'create' => Pages\CreateDeviceLink::route('/create'),
            'edit' => Pages\EditDeviceLink::route('/{record}/edit'),
        ];
    }
}
