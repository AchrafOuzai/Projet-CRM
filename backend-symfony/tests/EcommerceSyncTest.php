<?php

namespace App\Tests;

use PHPUnit\Framework\TestCase;

class EcommerceSyncTest extends TestCase
{
    public function testReferencePrefixesAreUniquePerPlatform(): void
    {
        $refs = ['PS-123', 'WC-123', 'SH-123'];
        $this->assertCount(3, array_unique($refs));
    }

    public function testWoocommerceStatusMappingFallback(): void
    {
        $map = [
            'pending'   => 'Attente paiement',
            'completed' => 'Terminée',
            'cancelled' => 'Annulée',
        ];

        $mapStatut = fn(string $status) => $map[$status] ?? ucfirst($status);

        $this->assertSame('Terminée', $mapStatut('completed'));
        $this->assertSame('Statut-inconnu', $mapStatut('statut-inconnu'));
    }

    public function testDockerUrlResolution(): void
    {
        $resolveUrl = function (string $url, bool $isDockerized): string {
            if ($isDockerized) {
                return str_replace(
                    ['http://localhost', 'https://localhost'],
                    ['http://host.docker.internal', 'https://host.docker.internal'],
                    $url
                );
            }
            return $url;
        };

        $this->assertSame(
            'http://host.docker.internal:8080',
            $resolveUrl('http://localhost:8080', true)
        );

        $this->assertSame(
            'http://localhost:8080',
            $resolveUrl('http://localhost:8080', false)
        );
    }
}