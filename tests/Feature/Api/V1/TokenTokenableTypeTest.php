<?php

namespace Tests\Feature\Api\V1;

use App\Enums\RoleType;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Sanctum's stock migration types `tokenable_id` as a bigint, but `users.id` is a
 * ULID. Every other test in this directory passes on SQLite either way, because
 * SQLite applies type affinity per value and stores the ULID in a bigint column
 * without complaint. PostgreSQL rejects it outright, so `POST /api/v1/login`
 * returned a 500 in production while the suite stayed green.
 *
 * The declared column type is therefore asserted directly, so this is caught on
 * whichever driver the suite happens to run against.
 */
class TokenTokenableTypeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function tokenable_id_is_declared_as_a_string_not_an_integer(): void
    {
        $type = strtolower($this->declaredType('personal_access_tokens', 'tokenable_id'));

        $this->assertTrue(
            str_contains($type, 'char') || str_contains($type, 'text'),
            "personal_access_tokens.tokenable_id is declared [{$type}]; it must be a string "
            .'type because users.id is a 26-character ULID.'
        );
    }

    #[Test]
    public function a_ulid_owner_round_trips_through_the_token_table(): void
    {
        $user = User::factory()->withRole(RoleType::Student)->create([
            'password_hash' => Hash::make('Password123!'),
            'status' => UserStatus::Active,
        ]);

        $token = $user->createToken('regression-device')->accessToken;

        $stored = DB::table('personal_access_tokens')->where('id', $token->id)->first();

        $this->assertSame($user->id, (string) $stored->tokenable_id);
        $this->assertTrue($token->fresh()->tokenable->is($user));
    }

    private function declaredType(string $table, string $column): string
    {
        $connection = DB::connection();

        if ($connection->getDriverName() === 'sqlite') {
            foreach ($connection->select("pragma table_info('{$table}')") as $info) {
                if ($info->name === $column) {
                    return $info->type;
                }
            }

            $this->fail("Column {$table}.{$column} does not exist.");
        }

        $row = $connection->selectOne(
            'select data_type from information_schema.columns where table_name = ? and column_name = ?',
            [$table, $column]
        );

        $this->assertNotNull($row, "Column {$table}.{$column} does not exist.");

        return $row->data_type;
    }
}
