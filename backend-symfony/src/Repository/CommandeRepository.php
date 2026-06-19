<?php

namespace App\Repository;

use App\Entity\Commande;
use App\Entity\Tenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CommandeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Commande::class);
    }

    // ✅ Filtre par tenant + date + livraison pour le dashboard
    public function findByTenantWithFilters(
        Tenant  $tenant,
        ?string $dateDebut,
        ?string $dateFin,
        ?string $livraison
    ): array {
        $qb = $this->createQueryBuilder('c')
                   ->andWhere('c.tenant = :tenant')
                   ->setParameter('tenant', $tenant);

        if ($dateDebut) {
            $qb->andWhere('c.date >= :dateDebut')
               ->setParameter('dateDebut', new \DateTime($dateDebut));
        }
        if ($dateFin) {
            $qb->andWhere('c.date <= :dateFin')
               ->setParameter('dateFin', new \DateTime($dateFin));
        }
        if ($livraison) {
            $qb->andWhere('c.livraison = :livraison')
               ->setParameter('livraison', $livraison);
        }

        return $qb->orderBy('c.date', 'DESC')->getQuery()->getResult();
    }

    // ✅ findWithFilters existant — ajout du filtre tenant
    public function findWithFilters(array $filters, ?Tenant $tenant = null): array
    {
        $qb = $this->createQueryBuilder('c');

        if ($tenant) {
            $qb->andWhere('c.tenant = :tenant')->setParameter('tenant', $tenant);
        }
        if (!empty($filters['dateDebut'])) {
            $qb->andWhere('c.date >= :dateDebut')->setParameter('dateDebut', new \DateTime($filters['dateDebut']));
        }
        if (!empty($filters['dateFin'])) {
            $qb->andWhere('c.date <= :dateFin')->setParameter('dateFin', new \DateTime($filters['dateFin']));
        }
        if (!empty($filters['agent'])) {
            $qb->andWhere('c.agent = :agent')->setParameter('agent', $filters['agent']);
        }
        if (!empty($filters['confirmation'])) {
            $qb->andWhere('c.confirmation = :confirmation')->setParameter('confirmation', $filters['confirmation']);
        }
        if (!empty($filters['livraison'])) {
            $qb->andWhere('c.livraison = :livraison')->setParameter('livraison', $filters['livraison']);
        }
        if (!empty($filters['ville'])) {
            $qb->andWhere('c.ville = :ville')->setParameter('ville', $filters['ville']);
        }
        if (!empty($filters['search'])) {
            $qb->andWhere('c.client LIKE :search OR c.designation LIKE :search OR c.telephone LIKE :search OR c.ref LIKE :search')
               ->setParameter('search', '%' . $filters['search'] . '%');
        }

        return $qb->orderBy('c.date', 'DESC')->getQuery()->getArrayResult();
    }

    // ✅ getStats tenant-aware
    public function getStats(?Tenant $tenant = null): array
    {
        $qb = $this->createQueryBuilder('c');
        if ($tenant) {
            $qb->andWhere('c.tenant = :tenant')->setParameter('tenant', $tenant);
        }

        $commandes  = $qb->getQuery()->getResult();
        $total      = count($commandes);
        $confirmees = count(array_filter($commandes, fn($c) => $c->getConfirmation() === 'Confirmée'));
        $livrees    = count(array_filter($commandes, fn($c) => $c->getLivraison()    === 'Livrée'));
        $retours    = count(array_filter($commandes, fn($c) => $c->getLivraison()    === 'Retour'));
        $ca         = array_sum(array_map(
            fn($c) => $c->getLivraison() === 'Livrée' ? (float)$c->getPrixVenteTotal() : 0,
            $commandes
        ));

        return [
            'total'            => $total,
            'confirmees'       => $confirmees,
            'livrees'          => $livrees,
            'retours'          => $retours,
            'ca'               => $ca,
            'tauxConfirmation' => $total      ? round($confirmees / $total      * 100) : 0,
            'tauxLivraison'    => $confirmees ? round($livrees    / $confirmees * 100) : 0,
            'tauxRetour'       => $livrees    ? round($retours    / $livrees    * 100) : 0,
        ];
    }
}