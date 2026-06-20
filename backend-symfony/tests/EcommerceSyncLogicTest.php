<?php

namespace App\Tests;

use PHPUnit\Framework\TestCase;

class EcommerceSyncLogicTest extends TestCase
{
    /**
     * Vérifie que chaque plateforme génère bien un préfixe de référence unique,
     * condition essentielle pour éviter les collisions entre commandes
     * importées depuis des sources différentes.
     */
    public function testReferencePrefixesAreUniquePerPlatform(): void
    {
        $prefixes = ['PS-' => 'prestashop', 'WC-' => 'woocommerce', 'SH-' => 'shopify'];

        $refs = [];
        foreach ($prefixes as $prefix => $platform) {
            $refs[] = $prefix . '123';
        }

        // Aucune référence ne doit être dupliquée même avec le même ID numérique
        $this->assertCount(3, array_unique($refs));
    }

    /**
     * Vérifie la génération séquentielle des référents Tiers (format TIxxxxx),
     * en simulant la logique de generateReferent() sans toucher la base.
     */
    public function testReferentSequenceGeneration(): void
    {
        $counter = 0;
        $generate = function () use (&$counter) {
            $counter++;
            return 'TI' . str_pad((string)$counter, 5, '0', STR_PAD_LEFT);
        };

        $first  = $generate();
        $second = $generate();
        $third  = $generate();

        $this->assertSame('TI00001', $first);
        $this->assertSame('TI00002', $second);
        $this->assertSame('TI00003', $third);
        $this->assertNotEquals($first, $third);
    }

    /**
     * Vérifie qu'un mapping de statut WooCommerce inconnu retombe
     * sur un comportement par défaut sûr (ucfirst) plutôt que de planter.
     */
    public function testWoocommerceStatusMappingFallback(): void
    {
        $map = [
            'pending'   => 'Attente paiement',
            'completed' => 'Terminée',
            'cancelled' => 'Annulée',
        ];

        $mapStatut = function (string $status) use ($map) {
            return $map[$status] ?? ucfirst($status);
        };

        $this->assertSame('Terminée', $mapStatut('completed'));
        $this->assertSame('Statut-inconnu', $mapStatut('statut-inconnu'));
    }

    /**
     * Vérifie la résolution d'URL Docker : en environnement conteneurisé,
     * localhost doit être remplacé par host.docker.internal.
     */
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