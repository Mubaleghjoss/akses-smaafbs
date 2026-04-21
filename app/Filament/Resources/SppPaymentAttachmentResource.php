<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasConfirmedDeleteActions;
use App\Filament\Resources\SppPaymentAttachmentResource\Pages;
use App\Models\SppBill;
use App\Models\SppPaymentAttachment;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class SppPaymentAttachmentResource extends Resource
{
    use HasConfirmedDeleteActions;

    protected static ?string $model = SppPaymentAttachment::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string|\UnitEnum|null $navigationGroup = 'SPP';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Select::make('bill_id')
                    ->label('Tagihan')
                    ->required()
                    ->searchable()
                    ->getSearchResultsUsing(function (string $search): array {
                        return SppBill::query()
                            ->with('siswa')
                            ->where('id', 'like', '%'.$search.'%')
                            ->orWhereHas('siswa', fn ($q) => $q->where('nama', 'like', '%'.$search.'%'))
                            ->orderByDesc('id')
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(function (SppBill $b) {
                                $label = '#'.$b->id;
                                if ($b->siswa) {
                                    $label .= ' - '.$b->siswa->nama;
                                }
                                $label .= ' ('.sprintf('%02d/%d', (int) $b->period_month, (int) $b->period_year).')';

                                return [$b->id => $label];
                            })
                            ->toArray();
                    })
                    ->getOptionLabelUsing(function ($value): ?string {
                        $b = SppBill::query()->with('siswa')->find($value);
                        if (! $b) {
                            return null;
                        }
                        $label = '#'.$b->id;
                        if ($b->siswa) {
                            $label .= ' - '.$b->siswa->nama;
                        }
                        $label .= ' ('.sprintf('%02d/%d', (int) $b->period_month, (int) $b->period_year).')';

                        return $label;
                    }),
                Forms\Components\TextInput::make('amount')
                    ->label('Nominal')
                    ->numeric()
                    ->minValue(0)
                    ->required(),
                Forms\Components\FileUpload::make('file_name')
                    ->label('Bukti Bayar')
                    ->disk('public')
                    ->directory(fn (Get $get) => 'spp/payments/'.((string) ($get('bill_id') ?? 'unknown')))
                    ->preserveFilenames()
                    ->maxSize(4096)
                    ->required()
                    ->storeFileSizeIn('file_size')
                    ->storeFileMimeTypeIn('mime_type'),
                Forms\Components\Hidden::make('mime_type'),
                Forms\Components\Hidden::make('file_size'),
                Forms\Components\Hidden::make('status')
                    ->default('pending'),
                Forms\Components\DateTimePicker::make('uploaded_at'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('bill.siswa.nama')
                    ->label('Siswa')
                    ->searchable(),
                Tables\Columns\TextColumn::make('bill_id')
                    ->label('Bill')
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Nominal')
                    ->money('IDR', locale: 'id')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('file_name')
                    ->label('Bukti')
                    ->formatStateUsing(fn (?string $state): string => $state ? basename($state) : '-')
                    ->url(function (SppPaymentAttachment $record): ?string {
                        $path = (string) $record->file_name;
                        if ($path === '') {
                            return null;
                        }
                        if (str_starts_with($path, 'assets/')) {
                            $path = preg_replace('#^assets/uploads/#', '', $path) ?: (string) $record->file_name;
                        }

                        return Storage::disk('public')->url($path);
                    })
                    ->openUrlInNewTab(),
                Tables\Columns\TextColumn::make('uploaded_at')
                    ->label('Upload')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('verified_at')
                    ->label('Verified')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('verification_notes')
                    ->label('Catatan')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ])
                    ->default('pending'),
            ])
            ->actions([
                Action::make('approve')
                    ->label('Approve')
                    ->color('success')
                    ->visible(fn (SppPaymentAttachment $record): bool => $record->status === 'pending')
                    ->form([
                        Forms\Components\TextInput::make('amount')
                            ->label('Nominal Disetujui')
                            ->numeric()
                            ->required()
                            ->default(fn (SppPaymentAttachment $record) => (int) ($record->amount ?? 0)),
                        Forms\Components\Textarea::make('notes')
                            ->label('Catatan Verifikasi')
                            ->maxLength(255)
                            ->nullable(),
                    ])
                    ->action(function (SppPaymentAttachment $record, array $data): void {
                        $bill = $record->bill;
                        if (! $bill) {
                            Notification::make()->title('Bill tidak ditemukan')->danger()->send();

                            return;
                        }

                        $amount = max(0, (int) $data['amount']);
                        $remaining = max(0, (int) $bill->amount - (int) $bill->paid_amount);
                        if ($amount > $remaining) {
                            $amount = $remaining;
                        }

                        $record->update([
                            'amount' => $amount,
                            'status' => 'approved',
                            'verified_at' => now(),
                            'verified_by' => auth()->id(),
                            'verification_notes' => $data['notes'] ?? null,
                        ]);

                        $bill->applyPayment($amount);

                        Notification::make()->title('Pembayaran di-approve')->success()->send();
                    }),

                Action::make('reject')
                    ->label('Reject')
                    ->color('danger')
                    ->visible(fn (SppPaymentAttachment $record): bool => $record->status === 'pending')
                    ->form([
                        Forms\Components\Textarea::make('notes')
                            ->label('Alasan Reject')
                            ->maxLength(255)
                            ->required(),
                    ])
                    ->action(function (SppPaymentAttachment $record, array $data): void {
                        $record->update([
                            'status' => 'rejected',
                            'verified_at' => now(),
                            'verified_by' => auth()->id(),
                            'verification_notes' => (string) $data['notes'],
                        ]);

                        Notification::make()->title('Bukti bayar di-reject')->success()->send();
                    }),

                EditAction::make(),
                static::makeDeleteTableAction('bukti pembayaran'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    static::makeDeleteBulkTableAction(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['bill.siswa']);
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
            'index' => Pages\ListSppPaymentAttachments::route('/'),
            'create' => Pages\CreateSppPaymentAttachment::route('/create'),
            'edit' => Pages\EditSppPaymentAttachment::route('/{record}/edit'),
        ];
    }
}
