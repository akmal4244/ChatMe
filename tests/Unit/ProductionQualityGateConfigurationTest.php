<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ProductionQualityGateConfigurationTest extends TestCase
{
    #[Test]
    public function larastan_is_locked_and_cannot_be_silenced_by_a_baseline(): void
    {
        $composer = json_decode($this->projectFile('composer.json'), true, flags: JSON_THROW_ON_ERROR);
        $configuration = $this->projectFile('phpstan.neon');

        $this->assertSame('3.10', $composer['require-dev']['larastan/larastan'] ?? null);
        $this->assertSame('phpstan analyse --memory-limit=1G --no-progress', $composer['scripts']['analyse'] ?? null);
        $this->assertStringContainsString('vendor/larastan/larastan/extension.neon', $configuration);
        $this->assertMatchesRegularExpression('/^\s*level:\s*5\s*$/m', $configuration);
        $this->assertMatchesRegularExpression('/^\s*- app\s*$/m', $configuration);
        $this->assertStringNotContainsString('baseline', strtolower($configuration));
        $this->assertStringNotContainsString('ignoreErrors', $configuration);
    }

    #[Test]
    public function gitleaks_scans_complete_history_and_the_checked_out_tree_with_a_verified_release(): void
    {
        $workflow = $this->projectFile('.github/workflows/quality-gates.yml');

        $this->assertStringContainsString("GITLEAKS_VERSION: '8.30.1'", $workflow);
        $this->assertStringContainsString(
            "GITLEAKS_LINUX_X64_SHA256: '551f6fc83ea457d62a0d98237cbad105af8d557003051f41f3e7ca7b3f2470eb'",
            $workflow,
        );
        $this->assertStringContainsString('fetch-depth: 0', $workflow);
        $this->assertStringContainsString('persist-credentials: false', $workflow);
        $this->assertStringContainsString('sha256sum --check --strict', $workflow);
        $this->assertStringContainsString('/tmp/gitleaks git .', $workflow);
        $this->assertStringContainsString('--log-opts="--all"', $workflow);
        $this->assertStringContainsString('/tmp/gitleaks dir .', $workflow);
        $this->assertStringContainsString('--redact=100', $workflow);
    }

    private function projectFile(string $path): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path));

        $this->assertNotFalse($contents, "Unable to read project file: {$path}");

        return $contents;
    }
}
