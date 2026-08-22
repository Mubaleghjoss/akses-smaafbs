<?php

namespace App\Models\Assessment;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubjectCategory extends Model
{
    use HasFactory;

    public const TYPE_WAJIB = 'wajib';

    public const TYPE_PILIHAN = 'pilihan';

    public const TYPES = [
        self::TYPE_WAJIB => 'Wajib',
        self::TYPE_PILIHAN => 'Pilihan',
    ];

    protected $table = 'assessment_subject_categories';

    protected $fillable = [
        'code',
        'name',
        'type',
        'sort_order',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class, 'assessment_subject_category_id');
    }
}
