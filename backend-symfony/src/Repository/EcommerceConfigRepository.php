<?php

namespace App\Repository;

use App\Entity\EcommerceConfig;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class EcommerceConfigRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EcommerceConfig::class);
    }

    public function findByType(string $type): ?EcommerceConfig
    {
        return $this->findOneBy(['type' => $type]);
    }

    public function findActiveByType(string $type): ?EcommerceConfig
    {
        return $this->findOneBy(['type' => $type, 'isActive' => true]);
    }
}