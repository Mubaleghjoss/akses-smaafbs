<?php

namespace App\Models\Assessment;

use App\Models\GuruTendik;
use App\Models\Rombel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HomeroomAssignment extends Model
{
    use HasFactory;

    protected $table = 'assessment_homeroom_assignments';

    protected $fillable = [
        'assessment_semester_id',
        'teacher_id',
        'rombel_id',
        'teacher_name_snapshot',
        'rombel_name_snapshot',
        'is_active',
    ];

    protected static function booted(): void
    {
        static::saved(function (self $assignment): void {
            User::query()
                ->where('guru_tendik_id', $assignment->teacher_id)
                ->each(fn (User $user) => $user->syncAssessmentHomeroomRole());
        });
    }

    protected function casts(): array
    {
        return [
            'teacher_id' => 'integer',
            'rombel_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'assessment_semester_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(GuruTendik::class, 'teacher_id');
    }

    public function rombel(): BelongsTo
    {
        return $this->belongsTo(Rombel::class, 'rombel_id');
    }

    public function periodHomerooms(): HasMany
    {
        return $this->hasMany(AssessmentPeriodHomeroom::class, 'source_homeroom_assignment_id');
    }
}
