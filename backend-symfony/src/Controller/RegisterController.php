<?php

namespace App\Controller;

use App\Entity\Tenant;
use App\Entity\User;
use App\Repository\TenantRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class RegisterController extends AbstractController
{
    private $em;
    private $tenantRepo;
    private $userRepo;
    private $hasher;

    public function __construct(
        EntityManagerInterface $em,
        TenantRepository $tenantRepo,
        UserRepository $userRepo,
        UserPasswordHasherInterface $hasher
    ) {
        $this->em         = $em;
        $this->tenantRepo = $tenantRepo;
        $this->userRepo   = $userRepo;
        $this->hasher     = $hasher;
    }

    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $nom      = trim($data['nom']      ?? '');
        $email    = trim($data['email']    ?? '');
        $password = trim($data['password'] ?? '');
        $plan     = $data['plan'] ?? 'free';

        // Validation
        if (!$nom || !$email || !$password) {
            return $this->json([
                'error' => 'nom, email et password sont obligatoires'
            ], 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => 'Email invalide'], 400);
        }

        if (strlen($password) < 6) {
            return $this->json([
                'error' => 'Le mot de passe doit contenir au moins 6 caractères'
            ], 400);
        }

        // Vérifier email non utilisé
        $existingUser   = $this->userRepo->findOneBy(['email' => $email]);
        $existingTenant = $this->tenantRepo->findOneBy(['email' => $email]);

        if ($existingUser || $existingTenant) {
            return $this->json([
                'error' => 'Cet email est déjà utilisé'
            ], 400);
        }

        // Créer le tenant
        $tenant = new Tenant();
        $tenant->setNom($nom);
        $tenant->setEmail($email);
        $tenant->setPlan($plan);
        $tenant->setStatut('actif');
        $this->em->persist($tenant);

        // Créer le compte Admin du tenant
        $user = new User();
        $user->setEmail($email);
        $user->setNom($nom);
        $user->setRole('ROLE_ADMIN');
        $user->setRoles(['ROLE_ADMIN']);
        $user->setTenant($tenant);
        $user->setPermissions(null);
        $user->setPassword(
            $this->hasher->hashPassword($user, $password)
        );
        $this->em->persist($user);
        $this->em->flush();

        return $this->json([
            'success'  => true,
            'message'  => 'Compte créé avec succès',
            'tenantId' => $tenant->getId()
        ], 201);
    }
}