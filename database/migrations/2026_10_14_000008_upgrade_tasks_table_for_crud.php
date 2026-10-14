<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // prioritas tugas: rendah / normal / tinggi (alat bantu urutan, bukan pengubah jadwal)
            $table->enum('priority', ['rendah', 'normal', 'tinggi'])->default('normal')->after('duration');
        });

        Schema::table('tasks', function (Blueprint $table) {
            // tanggal tugas induk bisa kosong (mengisi otomatis dari sub-tugas)
            $table->date('start_date')->nullable()->change();
            $table->date('end_date')->nullable()->change();
        });

        Schema::table('tasks', function (Blueprint $table) {
            // hapus induk -> sub-tugas ikut terhapus, bukan menjadi yatim
            $table->dropForeign(['parent_id']);
            $table->foreign('parent_id')->references('id')->on('tasks')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->foreign('parent_id')->references('id')->on('tasks')->nullOnDelete();
            $table->date('start_date')->nullable(false)->change();
            $table->date('end_date')->nullable(false)->change();
            $table->dropColumn('priority');
        });
    }
};
