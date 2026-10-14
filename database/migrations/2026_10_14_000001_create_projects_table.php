<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('organization')->nullable();
            $table->string('website')->nullable();
            $table->text('description')->nullable();
            $table->date('start_date');
            // hari weekend dalam format angka Carbon: 0=Minggu .. 6=Sabtu
            $table->json('weekend_days')->default('[6, 0]');
            // no_work = weekend libur, work = weekend tetap dihitung hari kerja
            $table->enum('weekend_mode', ['no_work', 'work'])->default('no_work');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
