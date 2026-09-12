<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('payments', 'expired_at')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->timestamp('expired_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('payments', 'expired_at')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropColumn('expired_at');
            });
        }
    }
};
