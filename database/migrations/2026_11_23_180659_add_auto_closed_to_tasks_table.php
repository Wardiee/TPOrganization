<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * True when tasks:sync closed an automatic task because its problem went away. Such a task can
     * reopen if the problem comes back; a task the admin completed never reopens.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->boolean('auto_closed')->default(false)->after('is_auto');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('auto_closed');
        });
    }
};
