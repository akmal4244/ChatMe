<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MalayCopyTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_use_plain_consistent_malay_copy(): void
    {
        $this->seed(PlanSeeder::class);

        $html = collect(['/', '/login', '/register', '/privacy', '/terms'])
            ->map(fn (string $uri): string => $this->get($uri)->assertOk()->getContent())
            ->implode("\n");

        $user = User::factory()->create();
        $html .= "\n".$this->actingAs($user)->get('/')->assertOk()->getContent();

        foreach (['Buka dashboard', 'Platform SaaS', 'Kod benam', 'server', 'auto-debit'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $html);
        }

        foreach (['Buka papan pemuka', 'Kod pemasangan ChatMe', 'Platform chatbot buatan Malaysia', 'tiada potongan automatik'] as $required) {
            $this->assertStringContainsString($required, $html);
        }
    }

    public function test_common_error_pages_use_the_malay_guest_shell(): void
    {
        $expectations = [
            403 => 'Akses tidak dibenarkan',
            404 => 'Halaman tidak dijumpai',
            419 => 'Sesi telah tamat',
            429 => 'Terlalu banyak permintaan',
            500 => 'Sistem menghadapi masalah',
            503 => 'Sistem sedang diselenggara',
        ];

        foreach ($expectations as $status => $heading) {
            $path = resource_path("views/errors/{$status}.blade.php");
            $this->assertFileExists($path);

            $html = view("errors.{$status}")->render();

            $this->assertStringContainsString((string) $status, $html);
            $this->assertStringContainsString($heading, $html);
            $this->assertStringContainsString('Kembali', $html);
        }
    }
}
