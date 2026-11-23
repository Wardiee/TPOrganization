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
        Schema::create('org_chart_seats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('org_chart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('position_id')->constrained();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name_as_listed');
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['org_chart_id', 'position_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('org_chart_seats');
    }
};
