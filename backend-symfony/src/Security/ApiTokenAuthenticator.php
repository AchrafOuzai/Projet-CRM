<?php

namespace App\Security;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class ApiTokenAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    private UserRepository $userRepository;
    private string $jwtSecret;

    public function __construct(UserRepository $userRepository, string $jwtSecret)
    {
        $this->userRepository = $userRepository;
        $this->jwtSecret      = $jwtSecret;
    }

    public function supports(Request $request): ?bool
    {
        return $request->headers->has('Authorization')
            && str_starts_with($request->headers->get('Authorization'), 'Bearer ');
    }

    public function authenticate(Request $request): Passport
    {
        $authHeader = $request->headers->get('Authorization');
        $token      = substr($authHeader, 7); // Enlève "Bearer "

        try {
            // Décode et vérifie le JWT
            $payload = JWT::decode($token, new Key($this->jwtSecret, 'HS256'));
            $email   = $payload->sub;

        } catch (ExpiredException $e) {
            throw new CustomUserMessageAuthenticationException('Token expiré');
        } catch (SignatureInvalidException $e) {
            throw new CustomUserMessageAuthenticationException('Signature JWT invalide');
        } catch (\Exception $e) {
            throw new CustomUserMessageAuthenticationException('Token JWT invalide');
        }

        // Vérifie que l'utilisateur existe toujours en base
        $user = $this->userRepository->findOneBy(['email' => $email]);
        if (!$user) {
            throw new CustomUserMessageAuthenticationException('Utilisateur introuvable');
        }

        return new SelfValidatingPassport(
            new UserBadge($email)
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null; // Continue la requête normalement
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse([
            'error'   => 'Token invalide ou expiré',
            'message' => $exception->getMessage()
        ], 401);
    }

    public function start(Request $request, AuthenticationException $authException = null): Response
    {
        return new JsonResponse([
            'error'   => 'Authentication required',
            'message' => 'Token JWT manquant ou invalide'
        ], 401);
    }
}