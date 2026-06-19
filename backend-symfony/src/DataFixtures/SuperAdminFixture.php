<?php
// src/DataFixtures/SuperAdminFixture.php
namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface; 
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class SuperAdminFixture extends Fixture implements FixtureGroupInterface 
{
    private UserPasswordHasherInterface $hasher;

    public function __construct(UserPasswordHasherInterface $hasher)
    {
        $this->hasher = $hasher;
    }

    // ← ajouter cette méthode
    public static function getGroups(): array
    {
        return ['superadmin'];
    }

    public function load(ObjectManager $manager): void
    {
        $existing = $manager->getRepository(User::class)
                            ->findOneBy(['role' => 'ROLE_SUPER_ADMIN']);
        if ($existing) {
            echo "Super Admin existe déjà.\n";
            return;
        }

        $admin = new User();
        $admin->setEmail('is-MOENN@shopcrm.com');
        $admin->setNom('is-MOENN');
        $admin->setRole('ROLE_SUPER_ADMIN');
        $admin->setRoles(['ROLE_SUPER_ADMIN']);
        $admin->setTenant(null);
        $admin->setPassword(
            $this->hasher->hashPassword($admin, 'Admin@2026!')
        );

        $manager->persist($admin);
        $manager->flush();
        echo "✅ Super Admin is-MOENN créé !\n";
    }
}