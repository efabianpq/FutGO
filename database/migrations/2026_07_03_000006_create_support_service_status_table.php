<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_service_status', function (Blueprint $table) {
            $table->id();
            $table->string('component', 100)->unique();
            $table->enum('status', ['operativo', 'degradado', 'caido', 'mantenimiento'])
                  ->default('operativo');
            $table->text('message')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->boolean('auto_detected')->default(true);
            $table->timestamps();
        });

        DB::table('support_service_status')->insert([
            ['component' => 'plataforma',     'status' => 'operativo', 'created_at' => now(), 'updated_at' => now()],
            ['component' => 'login',          'status' => 'operativo', 'created_at' => now(), 'updated_at' => now()],
            ['component' => 'correos',        'status' => 'operativo', 'created_at' => now(), 'updated_at' => now()],
            ['component' => 'notificaciones', 'status' => 'operativo', 'created_at' => now(), 'updated_at' => now()],
            ['component' => 'ranking',        'status' => 'operativo', 'created_at' => now(), 'updated_at' => now()],
            ['component' => 'scheduler',      'status' => 'operativo', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('support_service_status');
    }
};
