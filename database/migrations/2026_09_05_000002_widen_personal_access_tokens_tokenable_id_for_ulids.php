<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sanctum's stock migration declares `tokenable_id` through `morphs()`, which is
 * a bigint. `users.id` is a 26-character ULID, so issuing a token fails on any
 * strictly-typed driver:
 *
 *     SQLSTATE[22P02]: invalid input syntax for type bigint: "01M1S0XX2V9MYF..."
 *
 * SQLite is dynamically typed and stores the ULID in a bigint column regardless,
 * which is why the test suite never saw this and PostgreSQL — what production
 * runs — is where `POST /api/v1/login` returned a 500.
 *
 * No row can be lost: `config/auth.php` had no `sanctum` guard before the commit
 * that added `/api/v1`, so no code path ever issued a token. Rows are preserved
 * anyway, because the divergence between the test and production schemas is what
 * hid the defect and both should now agree.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->setTokenableIdType('string');
    }

    /**
     * Reverting to bigint cannot keep ULID values, so tokens are discarded.
     * They are revocable credentials and clients already handle re-authenticating,
     * which is preferable to a rollback that cannot complete.
     */
    public function down(): void
    {
        DB::table('personal_access_tokens')->delete();

        $this->setTokenableIdType('bigint');
    }

    private function setTokenableIdType(string $type): void
    {
        if (! Schema::hasTable('personal_access_tokens')) {
            return;
        }

        // SQLite cannot alter a column type, so the table is rebuilt. Rows are
        // carried across so the migration is not destructive on a dev database.
        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->rebuildForSqlite($type);

            return;
        }

        $sql = match ([DB::connection()->getDriverName(), $type]) {
            ['pgsql', 'string'] => 'alter table "personal_access_tokens" alter column "tokenable_id" type varchar(255) using "tokenable_id"::varchar',
            ['pgsql', 'bigint'] => 'alter table "personal_access_tokens" alter column "tokenable_id" type bigint using "tokenable_id"::bigint',
            ['mysql', 'string'] => 'alter table `personal_access_tokens` modify `tokenable_id` varchar(255) not null',
            ['mysql', 'bigint'] => 'alter table `personal_access_tokens` modify `tokenable_id` bigint unsigned not null',
            default => null,
        };

        if ($sql !== null) {
            DB::statement($sql);
        }
    }

    private function rebuildForSqlite(string $type): void
    {
        $rows = DB::table('personal_access_tokens')->get();

        Schema::drop('personal_access_tokens');

        Schema::create('personal_access_tokens', function (Blueprint $table) use ($type) {
            $table->id();
            $table->string('tokenable_type');
            $type === 'string'
                ? $table->string('tokenable_id')
                : $table->unsignedBigInteger('tokenable_id');
            $table->index(['tokenable_type', 'tokenable_id']);
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        foreach ($rows->chunk(200) as $chunk) {
            DB::table('personal_access_tokens')->insert(
                $chunk->map(fn ($row) => (array) $row)->all()
            );
        }
    }
};
