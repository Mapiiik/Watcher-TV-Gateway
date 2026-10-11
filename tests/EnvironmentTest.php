<?php
declare(strict_types=1);

namespace WatcherTvGateway\Test;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WatcherTvGateway\ConfigurationError;
use WatcherTvGateway\Environment;

#[CoversClass(Environment::class)]
final class EnvironmentTest extends TestCase
{
    public function testARequiredSettingThatIsMissingOrBlankIsRefused(): void
    {
        $env = new Environment(['BLANK' => '  ']);

        $this->expectException(ConfigurationError::class);
        $this->expectExceptionMessage('BLANK is not set.');
        $env->required('BLANK');
    }

    public function testValuesAreTrimmedAndDefaultsFillIn(): void
    {
        $env = new Environment(['NAME' => ' netair.tv ']);

        $this->assertSame('netair.tv', $env->required('NAME'));
        $this->assertSame('fallback', $env->optional('MISSING', 'fallback'));
    }

    public function testAFlagIsReadAndAnythingElseRefused(): void
    {
        $env = new Environment(['OFF' => 'false', 'ON' => 'yes', 'ODD' => 'maybe']);

        $this->assertFalse($env->flag('OFF', true));
        $this->assertTrue($env->flag('ON', false));
        $this->assertTrue($env->flag('MISSING', true));

        $this->expectException(ConfigurationError::class);
        $env->flag('ODD', true);
    }

    public function testAListLeavesOutEmptyItems(): void
    {
        $env = new Environment(['LIST' => ' MOPOS, ,Other ']);

        $this->assertSame(['MOPOS', 'Other'], $env->list('LIST'));
        $this->assertSame([], $env->list('MISSING'));
    }
}
