<?php

namespace App\Models\Exam;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class StudentToken extends Model
{
    protected $table = 'exam_student_tokens';
    protected $guarded = [];
    protected $hidden = ['token_hash', 'birth_date'];
    protected function casts(): array { return ['birth_date' => 'date', 'verified_at' => 'datetime']; }

    public static function generatePlainToken(): string
    {
        return Str::upper(Str::random(4)).'-'.Str::upper(Str::random(4));
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', self::normalizeToken($token));
    }

    public function matchesToken(string $token): bool
    {
        return hash_equals($this->token_hash, self::hashToken($token));
    }

    private static function normalizeToken(string $token): string
    {
        return Str::upper(str_replace('-', '', trim($token)));
    }

    public function schedule(): BelongsTo { return $this->belongsTo(Schedule::class); }
}
