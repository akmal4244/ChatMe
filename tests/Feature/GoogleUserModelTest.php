<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GoogleUserModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_subject_is_unique_hidden_and_not_mass_assignable(): void
    {
        $first = User::factory()->create();
        $first->forceFill([
            'google_sub' => 'google-sub-1',
            'google_linked_at' => now(),
        ])->save();

        $this->assertArrayNotHasKey('google_sub', $first->fresh()->toArray());

        $massAssigned = User::factory()->create();
        $massAssigned->fill([
            'google_sub' => 'attacker-controlled-subject',
            'google_linked_at' => now()->subDay(),
        ]);

        $this->assertNull($massAssigned->getRawOriginal('google_sub'));
        $this->assertNull($massAssigned->getRawOriginal('google_linked_at'));

        $this->expectException(QueryException::class);
        User::factory()->create()
            ->forceFill(['google_sub' => 'google-sub-1'])
            ->save();
    }

    public function test_google_only_user_has_no_local_password(): void
    {
        $user = User::factory()->create(['password' => null]);

        $this->assertFalse($user->hasLocalPassword());

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'anything',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_existing_password_users_remain_local_password_users(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $this->assertTrue($user->hasLocalPassword());
    }

    public function test_google_subject_column_declares_binary_collation_for_sqlite_and_mysql(): void
    {
        $tableDefinition = (string) DB::selectOne(
            "SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'users'",
        )->sql;
        $migration = (string) file_get_contents(database_path(
            'migrations/2026_07_12_130000_add_google_auth_fields_to_users_table.php',
        ));

        $this->assertMatchesRegularExpression(
            '/"google_sub"\s+varchar(?:\(255\))?\s+collate \'BINARY\'/i',
            $tableDefinition,
        );
        $this->assertStringContainsString("->charset('ascii')->collation('ascii_bin')", $migration);
        $this->assertStringContainsString("'mysql', 'mariadb' =>", $migration);
    }

    public function test_google_subject_uniqueness_is_case_sensitive(): void
    {
        User::factory()->create()->forceFill(['google_sub' => 'Subject-A'])->save();
        User::factory()->create()->forceFill(['google_sub' => 'subject-a'])->save();

        $this->assertDatabaseCount('users', 2);
    }
}
