<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\AccountSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccountSessionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_driver_revokes_only_the_users_other_sessions(): void
    {
        config()->set('session.driver', 'database');
        config()->set('session.table', 'sessions');
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->insertSession('current-session', $user);
        $this->insertSession('other-session', $user);
        $this->insertSession('foreign-session', $other);

        $deleted = app(AccountSessionService::class)->revokeOtherDatabaseSessions($user, 'current-session');

        $this->assertSame(1, $deleted);
        $this->assertDatabaseHas('sessions', ['id' => 'current-session', 'user_id' => $user->id]);
        $this->assertDatabaseMissing('sessions', ['id' => 'other-session']);
        $this->assertDatabaseHas('sessions', ['id' => 'foreign-session', 'user_id' => $other->id]);
    }

    public function test_non_database_driver_leaves_session_rows_unchanged(): void
    {
        config()->set('session.driver', 'array');
        $user = User::factory()->create();
        $this->insertSession('current-session', $user);
        $this->insertSession('other-session', $user);

        $deleted = app(AccountSessionService::class)->revokeOtherDatabaseSessions($user, 'current-session');

        $this->assertSame(0, $deleted);
        $this->assertDatabaseHas('sessions', ['id' => 'current-session']);
        $this->assertDatabaseHas('sessions', ['id' => 'other-session']);
    }

    public function test_database_driver_can_revoke_all_sessions_for_only_one_user(): void
    {
        config()->set('session.driver', 'database');
        config()->set('session.table', 'sessions');
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->insertSession('first-session', $user);
        $this->insertSession('second-session', $user);
        $this->insertSession('foreign-session', $other);

        $deleted = app(AccountSessionService::class)->revokeAllDatabaseSessions($user);

        $this->assertSame(2, $deleted);
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        $this->assertDatabaseHas('sessions', ['id' => 'foreign-session', 'user_id' => $other->id]);
    }

    private function insertSession(string $id, User $user): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => 'test-payload',
            'last_activity' => now()->getTimestamp(),
        ]);
    }
}
