<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PengaturanResource\Pages;
use App\Filament\Resources\PengaturanResource\RelationManagers;
use App\Models\Pengaturan;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PengaturanResource extends Resource
{
    protected static ?string $model = Pengaturan::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Administrator';

    protected static ?string $navigationLabel = 'Pengaturan';

    protected static ?string $slug = 'pengaturan';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('nama')
                    ->required()
                    ->readOnlyOn('edit')
                    ->maxLength(255),
                Forms\Components\TextInput::make('keterangan')
                    ->maxLength(255)
                    ->readOnlyOn('edit'),
                Forms\Components\TextInput::make('nilai')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nama')
                    ->searchable()
                    ->description(fn (Pengaturan $record) => $record->keterangan),
                Tables\Columns\TextColumn::make('nilai')
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
                Tables\Actions\Action::make('refresh')
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->action(function (Pengaturan $record) {
                        if ($record->nama === 'Kuota IPA') {
                            $userPrograms = User::where('program','IPA')->orderBy('nilai','desc');
                            $userPrograms->update(['ranking' => null,'eligible' => false]);
                            
                            foreach ($userPrograms->get() as $key => $value) {
                                $key++;
                                $value->update([
                                    'ranking' => $key,
                                    'eligible' => ($key <= $record->nilai),
                                ]);
                            }
                            Notification::make()
                            ->success()
                            ->title('Ranking & Eligible IPA telah diupdate.')
                            ->send();
                        } elseif ($record->nama === 'Kuota IPS') {
                            $userPrograms = User::where('program','IPS')->orderBy('nilai','desc');
                            $userPrograms->update(['ranking' => null,'eligible' => false]);
                            
                            foreach ($userPrograms->get() as $key => $value) {
                                $key++;
                                $value->update([
                                    'ranking' => $key,
                                    'eligible' => ($key <= $record->nilai),
                                ]);
                            }
                            Notification::make()
                            ->success()
                            ->title('Ranking & Eligible IPS telah diupdate.')
                            ->send();
                        } else {
                            //
                        }
                    })
                    ->hidden(fn (Pengaturan $record) => !($record->id === 1 || $record->id === 2)),
                Tables\Actions\EditAction::make(),
                //Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                //Tables\Actions\BulkActionGroup::make([
                    //Tables\Actions\DeleteBulkAction::make(),
                //]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManagePengaturans::route('/'),
        ];
    }
}
