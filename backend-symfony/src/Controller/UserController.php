<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class UserController extends AbstractController
{
    private $em;
private $repo;
private $hasher;

public function __construct(
    EntityManagerInterface $em,
    UserRepository $repo,
    UserPasswordHasherInterface $hasher
) {
    $this->em     = $em;
    $this->repo   = $repo;
    $this->hasher = $hasher;
}

    // ── Liste les agents du tenant connecté ──────────────
    #[Route('/api/settings/users', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $currentUser = $this->getUser();
        $tenant      = method_exists($currentUser, 'getTenant')
                       ? $currentUser->getTenant()
                       : null;

        if (!$tenant) {
            return $this->json(['error' => 'Tenant introuvable'], 403);
        }

        $users = $this->repo->findBy(['tenant' => $tenant]);

        return $this->json(array_map(fn($u) => $this->serialize($u), $users));
    }

    // ── Créer un agent pour le tenant connecté ───────────
    #[Route('/api/settings/users', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $currentUser = $this->getUser();
        $tenant      = method_exists($currentUser, 'getTenant')
                       ? $currentUser->getTenant()
                       : null;

        if (!$tenant) {
            return $this->json(['error' => 'Tenant introuvable'], 403);
        }

        $data = json_decode($request->getContent(), true);

        if (empty($data['email']) || empty($data['password']) || empty($data['nom'])) {
            return $this->json(['error' => 'email, password et nom sont obligatoires'], 400);
        }

        // Vérifier que l'email n'existe pas déjà
        $existing = $this->repo->findOneBy(['email' => $data['email']]);
        if ($existing) {
            return $this->json(['error' => 'Cet email est déjà utilisé'], 400);
        }

        $user = new User();
        $user->setEmail($data['email']);
        $user->setNom($data['nom']);
        $user->setRole('ROLE_AGENT');
        $user->setTenant($tenant);
        $user->setRoles(['ROLE_AGENT']);
        $user->setPermissions($data['permissions'] ?? []);
        $user->setPassword(
            $this->hasher->hashPassword($user, $data['password'])
        );

        $this->em->persist($user);
        $this->em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Agent créé avec succès',
            'user'    => $this->serialize($user)
        ], 201);
    }

    // ── Modifier un agent ────────────────────────────────
    #[Route('/api/settings/users/{id}', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $user = $this->repo->find($id);
        if (!$user) return $this->json(['error' => 'Utilisateur introuvable'], 404);

        $data = json_decode($request->getContent(), true);

        if (isset($data['nom']))         $user->setNom($data['nom']);
        if (isset($data['permissions'])) $user->setPermissions($data['permissions']);
        if (!empty($data['password'])) {
            $user->setPassword($this->hasher->hashPassword($user, $data['password']));
        }

        $this->em->flush();
        return $this->json(['success' => true, 'user' => $this->serialize($user)]);
    }

    // ── Supprimer un agent ───────────────────────────────
    #[Route('/api/settings/users/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $user = $this->repo->find($id);
        if (!$user) return $this->json(['error' => 'Utilisateur introuvable'], 404);

        // Ne pas supprimer un admin
        if ($user->getRole() === 'ROLE_ADMIN') {
            return $this->json(['error' => 'Impossible de supprimer un admin'], 403);
        }

        $this->em->remove($user);
        $this->em->flush();
        return $this->json(['success' => true]);
    }

    private function serialize(User $u): array
    {
        return [
            'id'          => $u->getId(),
            'email'       => $u->getEmail(),
            'nom'         => $u->getNom(),
            'role'        => $u->getRole(),
            'permissions' => $u->getPermissions() ?? [],
        ];
    }
}