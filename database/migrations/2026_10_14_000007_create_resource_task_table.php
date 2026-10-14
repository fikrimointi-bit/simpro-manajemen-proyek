<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resource_task', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->decimal('effort', 8, 2)->nullable(); // perkiraan jam/hari kerja yang dialokasikan
            $table->timestamps();

            // satu sumber daya hanya dialokasikan sekali per tugas
            $table->unique(['resource_id', 'task_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_task');
    }
};
