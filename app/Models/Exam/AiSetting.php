<?php

namespace App\Models\Exam;

use Illuminate\Database\Eloquent\Model;

class AiSetting extends Model
{
    protected $table = 'exam_ai_settings';

    protected $guarded = [];

    protected $hidden = ['api_key'];

    protected function casts(): array
    {
        return ['api_key' => 'encrypted', 'enabled' => 'boolean'];
    }

    public function maskedKey(): string
    {
        return filled($this->api_key) ? '********'.substr((string) $this->api_key, -4) : 'Belum diatur';
    }
}
