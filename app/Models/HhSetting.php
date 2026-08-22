<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HhSetting extends Model
{
    protected $table = 'hh_settings';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public static function get(string $key, string $default = ''): string
    {
        $row = static::find($key);

        return $row?->value ?? $default;
    }

    public static function set(string $key, string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}