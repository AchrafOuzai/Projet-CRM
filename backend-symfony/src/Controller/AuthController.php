<?php

namespace App\Controller;

use App\Entity\ApiToken;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class AuthController extends AbstractController
{
    private $em;
    private $userRepository;
    private $hasher;

    public function __construct(
        EntityManagerInterface $em,
        UserRepository $userRepository,
        UserPasswordHasherInterface $hasher
    ) {
        $this->em = $em;
        $this->userRepository = $userRepository;
        $this->hasher = $hasher;
    }

    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email    = $data['email'] ?? '';
        $password = $data['password'] ?? '';

        $user = $this->userRepository->findOneBy(['email' => $email]);

        if (!$user || !$this->hasher->isPasswordValid($user, $password)) {
            return $this->json(['error' => 'Email ou mot de passe incorrect'], 401);
        }

        $token = new ApiToken($user);
        $this->em->persist($token);
        $this->em->flush();

        return $this->json([
            'token' => $token->getToken(),
            'email' => $user->getUserIdentifier(),
            'nom'   => $user->getNom(),
        ]);
    }

    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }
        return $this->json([
            'email' => $user->getUserIdentifier(),
            'roles' => $user->getRoles(),
        ]);
    }
}