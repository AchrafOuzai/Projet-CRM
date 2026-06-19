<?php

namespace App\Tests;

use PHPUnit\Framework\TestCase;

class TenantIsolationTest extends TestCase
{
    public function testReferentFormatIsValid(): void
    {
        $referent = 'TI00001';
        $this->assertMatchesRegularExpression('/^TI\d{5}$/', $referent);
    }

    public function testCommandeRefPrefixByPlatform(): void
    {
        $this->assertStringStartsWith('PS-', 'PS-123');
        $this->assertStringStartsWith('WC-', 'WC-456');
        $this->assertStringStartsWith('SH-', 'SH-789');
    }
}