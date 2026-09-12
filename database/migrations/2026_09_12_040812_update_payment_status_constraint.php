<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // PostgreSQL only.
        // SQLite used by PHPUnit does not support DROP CONSTRAINT syntax.
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('
            ALTER TABLE payments
            DROP CONSTRAINT IF EXISTS payments_status_check
        ');

        DB::statement("
            ALTER TABLE payments
            ADD CONSTRAINT payments_status_check
            CHECK (status IN (
                'pending',
                'paid',
                'failed',
                'expired',
                'cancelled'
            ))
        ");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('
            ALTER TABLE payments
            DROP CONSTRAINT IF EXISTS payments_status_check
        ');

        DB::statement("
            ALTER TABLE payments
            ADD CONSTRAINT payments_status_check
            CHECK (status IN (
                'pending',
                'failed',
                'expired',
                'cancelled'
            ))
        ");
    }
};
