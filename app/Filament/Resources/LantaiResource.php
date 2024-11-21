<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LantaiResource\Pages;
use App\Filament\Resources\LantaiResource\RelationManagers;
use App\Models\Lantai;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LantaiResource extends Resource
{
    protected static ?string $model = Lantai::class;

    protected static ?string $navigationIcon = 'heroicon-o-map';
    protected static ?string $navigationGroup = 'Konfigurasi';

    protected static ?string $navigationLabel = 'Lantai';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'lantai';
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('gedung_id')
                    ->required()
                    ->relationship('gedung', 'nama')
                    ->label('Gedung'),
                Forms\Components\TextInput::make('nama')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('keterangan')
                    ->maxLength(255),
                Forms\Components\FileUpload::make('image_path')
                    ->label('Denah Lantai'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('gedung.nama')
                    ->sortable(),
                Tables\Columns\TextColumn::make('nama')
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
                Tables\Columns\ImageColumn::make('image_path')
                    ->label('Denah'),
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
            'index' => Pages\ListLantais::route('/'),
            'create' => Pages\CreateLantai::route('/create'),
            'edit' => Pages\EditLantai::route('/{record}/edit'),
        ];
    }
}
