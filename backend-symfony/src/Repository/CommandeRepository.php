<?php

namespace App\Repository;

use App\Entity\Commande;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CommandeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Commande::class);
    }

    public function findWithFilters(array $filters): array
    {
        $qb = $this->createQueryBuilder('c');

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
               ->setParameter('search', '%'.$filters['search'].'%');
        }

        return $qb->orderBy('c.date', 'DESC')->getQuery()->getArrayResult();
    }

    public function getStats(): array
    {
        $total = $this->count([]);
        $confirmees = $this->count(['confirmation' => 'Confirmée']);
        $livrees = $this->count(['livraison' => 'Livrée']);
        $retours = $this->count(['livraison' => 'Retour']);

        $ca = $this->createQueryBuilder('c')
            ->select('SUM(c.prixVenteTotal)')
            ->where('c.livraison = :livraison')
            ->setParameter('livraison', 'Livrée')
            ->getQuery()->getSingleScalarResult() ?? 0;

        return [
            'total' => $total,
            'confirmees' => $confirmees,
            'livrees' => $livrees,
            'retours' => $retours,
            'ca' => (float) $ca,
            'tauxConfirmation' => $total ? round($confirmees / $total * 100) : 0,
            'tauxLivraison' => $confirmees ? round($livrees / $confirmees * 100) : 0,
            'tauxRetour' => $livrees ? round($retours / $livrees * 100) : 0,
        ];
    }
}