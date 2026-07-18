<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared("CREATE TRIGGER audit_logs_prevent_update BEFORE UPDATE ON audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Auditlog-Eintraege sind unveraenderlich'");
            DB::unprepared("CREATE TRIGGER audit_logs_prevent_delete BEFORE DELETE ON audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Auditlog-Eintraege duerfen nicht geloescht werden'");

            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER audit_logs_prevent_update BEFORE UPDATE ON audit_logs BEGIN SELECT RAISE(ABORT, 'Auditlog-Eintraege sind unveraenderlich'); END");
            DB::unprepared("CREATE TRIGGER audit_logs_prevent_delete BEFORE DELETE ON audit_logs BEGIN SELECT RAISE(ABORT, 'Auditlog-Eintraege duerfen nicht geloescht werden'); END");
        }
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_prevent_update');
        DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_prevent_delete');
    }
};
