<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_logs', function (Blueprint $table) {
            $table->string('result', 10)->default('SUCCESS')->after('action'); // SUCCESS | FAILURE | DENIED
            $table->string('actor_type', 10)->default('SYSTEM')->after('user_id'); // USER | OWNER | SYSTEM | ANON
            $table->string('actor_label', 60)->nullable()->after('actor_type');
            $table->string('prev_hash', 64)->nullable();
            $table->string('entry_hash', 64)->nullable()->index();
        });

        // Append-only ditegakkan di level database (bukan hanya model).
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            DB::unprepared("CREATE TRIGGER system_logs_no_update BEFORE UPDATE ON system_logs BEGIN SELECT RAISE(ABORT, 'system_logs is append-only'); END;");
            DB::unprepared("CREATE TRIGGER system_logs_no_delete BEFORE DELETE ON system_logs BEGIN SELECT RAISE(ABORT, 'system_logs is append-only'); END;");
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::unprepared("CREATE TRIGGER system_logs_no_update BEFORE UPDATE ON system_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'system_logs is append-only'");
            DB::unprepared("CREATE TRIGGER system_logs_no_delete BEFORE DELETE ON system_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'system_logs is append-only'");
        }
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS system_logs_no_update');
        DB::unprepared('DROP TRIGGER IF EXISTS system_logs_no_delete');

        Schema::table('system_logs', function (Blueprint $table) {
            $table->dropColumn(['result', 'actor_type', 'actor_label', 'prev_hash', 'entry_hash']);
        });
    }
};
