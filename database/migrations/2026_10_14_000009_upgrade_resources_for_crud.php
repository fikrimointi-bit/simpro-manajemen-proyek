<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resources', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            // gaji per bulan dalam Rupiah (bilangan bulat); ditampilkan terformat
            $table->unsignedBigInteger('salary')->nullable()->after('role');
            // data kustom: pasangan "nama kolom" => "nilai" per anggota, disimpan sebagai JSON
            $table->json('custom_fields')->nullable()->after('salary');
        });

        Schema::table('resource_days_off', function (Blueprint $table) {
            // cuti berrentang: date = hari "dari", end_date = hari "sampai"
            $table->date('end_date')->nullable()->after('date');
        });
    }

    public function down(): void
    {
        Schema::table('resources', function (Blueprint $table) {
            $table->dropColumn(['phone', 'salary', 'custom_fields']);
        });

        Schema::table('resource_days_off', function (Blueprint $table) {
            $table->dropColumn('end_date');
        });
    }
};
