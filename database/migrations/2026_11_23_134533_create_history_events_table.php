<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('history_events', function (Blueprint $table) {
            $table->id();
            $table->date('happened_on')->index();
            $table->string('date_precision', 10)->default('day');
            $table->string('title');
            $table->text('detail')->nullable();
            $table->string('kind', 20);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('history_events');
    }
};
