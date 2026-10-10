<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'rejection_reason')) {
            Schema::table('users', function (Blueprint $table) {
                $table->text('rejection_reason')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'rejection_reason')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('rejection_reason');
            });
        }
    }
};
