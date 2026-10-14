<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            // untuk libur berulang, hanya bulan+tanggal yang dipakai (tahun diabaikan)
            $table->date('date');
            $table->string('description')->nullable();
            $table->boolean('is_recurring')->default(false);
            $table->timestamps();

            // satu proyek tidak boleh punya dua libur pada tanggal yang sama
            $table->unique(['project_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
