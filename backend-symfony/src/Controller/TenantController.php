<?php

namespace App\Controller;

use App\Entity\Tenant;
use App\Entity\User;
use App\Repository\TenantRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class TenantController extends AbstractController
{
    private $em;
private $repo;
private $hasher;

public function __construct(
    EntityManagerInterface $em,
    TenantRepository $repo,
    UserPasswordHasherInterface $hasher
) {
    $this->em     = $em;
    $this->repo   = $repo;
    $this->hasher = $hasher;
}

    // ── Liste tous les tenants (Super Admin seulement) ──
    #[Route('/api/admin/tenants', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $tenants = $this->repo->findAll();
        return $this->json(array_map(fn($t) => $this->serialize($t), $tenants));
    }

    // ── Créer un tenant + son compte Admin ──────────────
    #[Route('/api/admin/tenants', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['nom']) || empty($data['email']) || empty($data['password'])) {
            return $this->json(['error' => 'nom, email et password sont obligatoires'], 400);
        }

        // Vérifier que l'email n'existe pas déjà
        $existing = $this->repo->findOneBy(['email' => $data['email']]);
        if ($existing) {
            return $this->json(['error' => 'Un tenant avec cet email existe déjà'], 400);
        }

        // Créer le tenant
        $tenant = new Tenant();
        $tenant->setNom($data['nom']);
        $tenant->setEmail($data['email']);
        $tenant->setPlan($data['plan'] ?? 'free');
        $tenant->setStatut('actif');
        $this->em->persist($tenant);

        // Créer le compte Admin du tenant
        $admin = new User();
        $admin->setEmail($data['email']);
        $admin->setNom($data['nom']);
        $admin->setRole('ROLE_ADMIN');
        $admin->setTenant($tenant);
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword(
            $this->hasher->hashPassword($admin, $data['password'])
        );
        $this->em->persist($admin);
        $this->em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Tenant créé avec succès',
            'tenant'  => $this->serialize($tenant)
        ], 201);
    }

    // ── Détail d'un tenant ───────────────────────────────
    #[Route('/api/admin/tenants/{id}', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $tenant = $this->repo->find($id);
        if (!$tenant) return $this->json(['error' => 'Tenant introuvable'], 404);
        return $this->json($this->serialize($tenant));
    }

    // ── Modifier un tenant ───────────────────────────────
    #[Route('/api/admin/tenants/{id}', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $tenant = $this->repo->find($id);
        if (!$tenant) return $this->json(['error' => 'Tenant introuvable'], 404);

        $data = json_decode($request->getContent(), true);
        if (isset($data['nom']))    $tenant->setNom($data['nom']);
        if (isset($data['plan']))   $tenant->setPlan($data['plan']);
        if (isset($data['statut'])) $tenant->setStatut($data['statut']);

        $this->em->flush();
        return $this->json(['success' => true, 'tenant' => $this->serialize($tenant)]);
    }

    // ── Réinitialiser le mot de passe d'un tenant ────────
    #[Route('/api/admin/tenants/{id}/reset-password', methods: ['POST'])]
    public function resetPassword(int $id, Request $request): JsonResponse
    {
        $tenant = $this->repo->find($id);
        if (!$tenant) return $this->json(['error' => 'Tenant introuvable'], 404);

        $data        = json_decode($request->getContent(), true);
        $newPassword = $data['password'] ?? null;

        if (!$newPassword) {
            return $this->json(['error' => 'Nouveau mot de passe obligatoire'], 400);
        }

        // Trouver l'admin du tenant
        $admin = $this->em->getRepository(User::class)->findOneBy([
            'tenant' => $tenant,
            'role'   => 'ROLE_ADMIN'
        ]);

        if (!$admin) return $this->json(['error' => 'Admin du tenant introuvable'], 404);

        $admin->setPassword($this->hasher->hashPassword($admin, $newPassword));
        $this->em->flush();

        return $this->json(['success' => true, 'message' => 'Mot de passe réinitialisé']);
    }

    // ── Suspendre / Activer un tenant ────────────────────
    #[Route('/api/admin/tenants/{id}/toggle-statut', methods: ['POST'])]
    public function toggleStatut(int $id): JsonResponse
    {
        $tenant = $this->repo->find($id);
        if (!$tenant) return $this->json(['error' => 'Tenant introuvable'], 404);

        $tenant->setStatut($tenant->getStatut() === 'actif' ? 'suspendu' : 'actif');
        $this->em->flush();

        return $this->json([
            'success' => true,
            'statut'  => $tenant->getStatut(),
            'message' => 'Statut mis à jour'
        ]);
    }

    // ── Supprimer un tenant ──────────────────────────────
    #[Route('/api/admin/tenants/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $tenant = $this->repo->find($id);
        if (!$tenant) return $this->json(['error' => 'Tenant introuvable'], 404);

        $this->em->remove($tenant);
        $this->em->flush();

        return $this->json(['success' => true]);
    }

    private function serialize(Tenant $t): array
    {
        return [
            'id'        => $t->getId(),
            'nom'       => $t->getNom(),
            'email'     => $t->getEmail(),
            'plan'      => $t->getPlan(),
            'statut'    => $t->getStatut(),
            'createdAt' => $t->getCreatedAt()->format('Y-m-d H:i:s'),
            'nbUsers'   => $t->getUsers()->count(),
        ];
    }
}