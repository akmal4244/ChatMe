<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('password')->nullable()->change();
            $googleSubject = $table->string('google_sub', 255)->nullable()->unique()->after('email_verified_at');
            match (Schema::getConnection()->getDriverName()) {
                'mysql', 'mariadb' => $googleSubject->charset('ascii')->collation('ascii_bin'),
                'sqlite' => $googleSubject->collation('BINARY'),
                default => null,
            };
            $table->timestamp('google_linked_at')->nullable()->after('google_sub');
        });
    }

    public function down(): void
    {
        // Forward-only: dropping provider links or fabricating passwords can lock users out.
    }
};
