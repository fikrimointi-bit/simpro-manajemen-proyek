<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            // sub-tugas: nullOnDelete agar anak tak ikut hilang saat induk dihapus
            $table->foreignId('parent_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedInteger('duration')->nullable(); // durasi dalam hari (opsional)
            $table->unsignedTinyInteger('progress')->default(0); // persen selesai 0-100
            $table->string('color')->nullable();
            $table->boolean('is_milestone')->default(false);
            $table->unsignedInteger('position')->default(0); // urutan tampil di dalam proyek
            $table->timestamps();

            $table->index(['project_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
