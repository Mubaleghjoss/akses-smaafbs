<?php

namespace App\Models;

use App\Support\Content\VisiMisiSanitizer;
use Illuminate\Database\Eloquent\Model;

class VisiMisi extends Model
{
    protected $table = 'visi_misis';

    protected $guarded = [];

    protected $casts = [
        'singleton_key' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $record): void {
            $record->singleton_key = 1;
        });

        static::saving(function (self $record): void {
            $record->title = VisiMisiSanitizer::sanitizeTitle($record->title);
            $record->content = VisiMisiSanitizer::sanitizeContent($record->content);
        });
    }

    public function getRenderedContentAttribute(): string
    {
        return VisiMisiSanitizer::sanitizeContent($this->content);
    }
}
