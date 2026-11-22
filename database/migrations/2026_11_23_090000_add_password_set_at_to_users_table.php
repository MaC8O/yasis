<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * users.password_set_at — when the account last got a password that a person knows (chosen by
 * them, or a temporary one handed to them). NULL means nobody has ever been given a way in: the
 * account still carries the random placeholder it was created with.
 *
 * Reactivating an Inactive account uses it: with a known password the account goes back to
 * Active; without one it returns to Pending and the setup link is e-mailed again.
 *
 * Fill-in for existing rows (the real moment is not recorded anywhere, so it is approximated):
 *  - Active: COALESCE(email_verified_at, created_at). Completing the e-mailed setup link stamps
 *    email_verified_at, so for invited users that is the moment they chose their password;
 *    accounts without it were created with a password (seeded, Admin-created with a password,
 *    the create-admin command), so the creation time is when that password existed.
 *  - Pending: left NULL — they have never set one.
 *  - Inactive: the same value as Active, except left NULL when the account has no role (an HR
 *    staff record without portal access, never a login), was erased (its password was replaced
 *    with a random one), or its most recent deactivation was from Pending.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('password_set_at')->nullable()->after('must_reset_password');
        });

        $this->fillExistingAccounts();
    }

    /** Public so a test can check the fill-in rules without running schema changes. */
    public function fillExistingAccounts(): void
    {
        DB::update(
            "UPDATE users SET password_set_at = COALESCE(email_verified_at, created_at, CURRENT_TIMESTAMP) WHERE status = 'Active'"
        );

        DB::update(
            "UPDATE users SET password_set_at = COALESCE(email_verified_at, created_at, CURRENT_TIMESTAMP)
              WHERE status = 'Inactive'
                AND email NOT LIKE ? AND email NOT LIKE ?
                AND EXISTS (SELECT 1 FROM model_has_roles mr WHERE mr.model_id = users.id AND mr.model_type = ?)
                AND NOT EXISTS (
                    SELECT 1 FROM audit_logs a
                     WHERE a.id = (SELECT MAX(a2.id) FROM audit_logs a2
                                    WHERE a2.entity_type = 'User' AND a2.entity_id = users.id AND a2.action = 'Deactivated user')
                       AND JSON_UNQUOTE(JSON_EXTRACT(a.details, '$.status.from')) = 'Pending'
                )",
            ['%@deleted.yasis.edu', '%@retention.invalid', 'App\\Models\\User'],
        );
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('password_set_at');
        });
    }
};
