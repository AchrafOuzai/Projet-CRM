<?php
namespace App\Controller;
use App\Repository\UserRepository;
use Firebase\JWT\JWT;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class AuthController extends AbstractController
{
    private UserRepository $userRepository;
    private UserPasswordHasherInterface $hasher;
    private string $jwtSecret;

    public function __construct(
        UserRepository $userRepository,
        UserPasswordHasherInterface $hasher,
        string $jwtSecret
    ) {
        $this->userRepository = $userRepository;
        $this->hasher         = $hasher;
        $this->jwtSecret      = $jwtSecret;
    }

    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data     = json_decode($request->getContent(), true);
        $email    = $data['email']    ?? '';
        $password = $data['password'] ?? '';

        $user = $this->userRepository->findOneBy(['email' => $email]);

        if (!$user || !$this->hasher->isPasswordValid($user, $password)) {
            return $this->json(['error' => 'Email ou mot de passe incorrect'], 401);
        }

        // ✅ Blocage tenant suspendu
        $tenant = $user->getTenant();
        if ($tenant && $tenant->getStatut() === 'suspendu') {
            return $this->json([
                'error' => 'Votre compte a été suspendu. Veuillez contacter l\'administrateur.'
            ], 403);
        }

        $now     = time();
        $payload = [
            'iss'         => 'ShopCRM',
            'iat'         => $now,
            'exp'         => $now + (365 * 24 * 3600),
            'sub'         => $user->getUserIdentifier(),
            'roles'       => $user->getRoles(),
            'nom'         => $user->getNom(),
            'role'        => $user->getRole(),
            'tenantId'    => $tenant ? $tenant->getId()  : null,
            'tenantNom'   => $tenant ? $tenant->getNom() : null,
            'permissions' => $user->getPermissions() ?? [],
        ];

        $token = JWT::encode($payload, $this->jwtSecret, 'HS256');

        return $this->json([
            'token'       => $token,
            'email'       => $user->getUserIdentifier(),
            'nom'         => $user->getNom(),
            'role'        => $user->getRole(),
            'tenantId'    => $tenant ? $tenant->getId()  : null,
            'tenantNom'   => $tenant ? $tenant->getNom() : null,
            'permissions' => $user->getPermissions() ?? [],
        ]);
    }

    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        $tenant = method_exists($user, 'getTenant') ? $user->getTenant() : null;

        // ✅ Vérification aussi sur /api/me (cas token déjà existant)
        if ($tenant && $tenant->getStatut() === 'suspendu') {
            return $this->json(['error' => 'Compte suspendu'], 403);
        }

        return $this->json([
            'email'       => $user->getUserIdentifier(),
            'roles'       => $user->getRoles(),
            'nom'         => method_exists($user, 'getNom')         ? $user->getNom()         : '',
            'role'        => method_exists($user, 'getRole')        ? $user->getRole()        : '',
            'tenantId'    => $tenant ? $tenant->getId()  : null,
            'tenantNom'   => $tenant ? $tenant->getNom() : null,
            'permissions' => method_exists($user, 'getPermissions') ? $user->getPermissions() : [],
        ]);
    }
}