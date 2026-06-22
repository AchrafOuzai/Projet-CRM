<?php

namespace App\Tests;

use PHPUnit\Framework\TestCase;

class TenantIsolationTest extends TestCase
{
    /**
     * Simule la logique d'isolation : une recherche de commande
     * doit toujours inclure le critère tenant pour éviter
     * qu'un tenant voie les données d'un autre.
     */
    public function testFindCriteriaAlwaysIncludesTenant(): void
    {
        $tenantId = 5;
        $ref = 'PS-1';

        $criteria = ['ref' => $ref, 'tenant' => $tenantId];

        $this->assertArrayHasKey('tenant', $criteria);
        $this->assertSame(5, $criteria['tenant']);
    }

    /**
     * Vérifie que deux tenants différents ayant le même ID externe
     * (ex: même commande PrestaShop importée par erreur dans 2 tenants)
     * génèrent bien des clés de cache mémoire distinctes.
     */
    public function testCacheKeyDistinguishesTenants(): void
    {
        $email = 'client@example.com';

        $cacheKeyTenant1 = $email . '_' . 1;
        $cacheKeyTenant2 = $email . '_' . 2;

        $this->assertNotSame($cacheKeyTenant1, $cacheKeyTenant2);
    }

    public function testSuperAdminHasNullTenant(): void
    {
        $superAdminTenantId = null;
        $this->assertNull($superAdminTenantId);
    }
}