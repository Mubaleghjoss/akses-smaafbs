<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Unassigned matrix cells retain assignment work without retaining a teacher owner.
     */
    public function up(): void
    {
        Schema::table('assessment_period_assignments', function (Blueprint $table): void {
            $table->bigInteger('teacher_id')->nullable()->change();
            $table->string('teacher_name_snapshot', 150)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Restoring NOT NULL would require inventing teacher data for unassigned work.
    }
};
