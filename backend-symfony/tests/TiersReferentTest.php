<?php

namespace App\Tests;

use PHPUnit\Framework\TestCase;

class TiersReferentTest extends TestCase
{
    public function testReferentSequenceGeneration(): void
    {
        $counter = 0;
        $generate = function () use (&$counter) {
            $counter++;
            return 'TI' . str_pad((string)$counter, 5, '0', STR_PAD_LEFT);
        };

        $this->assertSame('TI00001', $generate());
        $this->assertSame('TI00002', $generate());
        $this->assertSame('TI00003', $generate());
    }

    public function testReferentFormatIsValid(): void
    {
        $referent = 'TI00042';
        $this->assertMatchesRegularExpression('/^TI\d{5}$/', $referent);
    }

    public function testReferentFormatRejectsInvalidPattern(): void
    {
        $referent = 'XX123';
        $this->assertDoesNotMatchRegularExpression('/^TI\d{5}$/', $referent);
    }
}