<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            // tugas yang mendahului ( predecessor ) dan tugas yang mengikuti ( successor )
            $table->foreignId('predecessor_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('successor_id')->constrained('tasks')->cascadeOnDelete();
            $table->string('type', 2)->default('FS'); // FS, SS, FF, SF ala GanttProject
            $table->integer('lag_days')->default(0); // jeda hari (+/- ) antar tugas
            $table->timestamps();

            // satu pasangan tugas cukup satu relasi ketergantungan
            $table->unique(['predecessor_id', 'successor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_dependencies');
    }
};
