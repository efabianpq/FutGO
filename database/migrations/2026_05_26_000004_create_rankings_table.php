<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rankings', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->integer('total_points')->default(0);
            $table->integer('exact_predictions')->default(0);
            $table->integer('current_position')->nullable();
            $table->integer('previous_position')->nullable();
            $table->timestamp('last_calculated_at')->nullable();
            $table->timestamps();

            $table->index('total_points');
            $table->index('current_position');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rankings');
    }
};
