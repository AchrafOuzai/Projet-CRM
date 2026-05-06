<?php

namespace App\Controller;

use App\Entity\PrestashopConfig;
use App\Repository\PrestashopConfigRepository;
use App\Service\PrestashopService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class PrestashopController extends AbstractController
{
    private $em;
    private $repo;
    private $service;

    public function __construct(
        EntityManagerInterface $em,
        PrestashopConfigRepository $repo,
        PrestashopService $service
    ) {
        $this->em      = $em;
        $this->repo    = $repo;
        $this->service = $service;
    }

    #[Route('/api/prestashop/config', methods: ['GET'])]
    public function getConfig(): JsonResponse
    {
        $config = $this->repo->findOneBy([]);
        if (!$config) {
            return $this->json(['connected' => false, 'shopUrl' => '', 'lastSync' => null, 'lastOrderId' => 0]);
        }
        return $this->json([
            'connected'    => $config->getIsActive(),
            'shopUrl'      => $config->getShopUrl(),
            'lastSync'     => $config->getLastSync() ? $config->getLastSync()->format('Y-m-d H:i:s') : null,
            'lastOrderId'  => $config->getLastOrderId(),
        ]);
    }

    #[Route('/api/prestashop/connect', methods: ['POST'])]
    public function connect(Request $request): JsonResponse
    {
        $data   = json_decode($request->getContent(), true);
        $url    = $data['url']    ?? '';
        $apiKey = $data['apiKey'] ?? '';

        if (!$url || !$apiKey) {
            return $this->json(['success' => false, 'message' => 'URL et clé API requises'], 400);
        }

        // Tester la connexion
        $result = $this->service->testConnection($url, $apiKey);

        if (!$result['success']) {
            return $this->json(['success' => false, 'message' => $result['message']], 400);
        }

        // Sauvegarder la config
        $config = $this->repo->findOneBy([]) ?? new PrestashopConfig();
        $config->setShopUrl($url);
        $config->setApiKey($apiKey);
        $config->setIsActive(true);
        $config->setLastSync(new \DateTimeImmutable());

        $this->em->persist($config);
        $this->em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Connexion réussie !',
            'name'    => parse_url($url, PHP_URL_HOST)
        ]);
    }

    #[Route('/api/prestashop/disconnect', methods: ['POST'])]
    public function disconnect(): JsonResponse
    {
        $config = $this->repo->findOneBy([]);
        if ($config) {
            $config->setIsActive(false);
            $this->em->flush();
        }
        return $this->json(['success' => true]);
    }

    #[Route('/api/prestashop/sync', methods: ['POST'])]
    public function sync(): JsonResponse
    {
        $config = $this->repo->findOneBy(['isActive' => true]);
        if (!$config) {
            return $this->json(['success' => false, 'message' => 'PrestaShop non connecté'], 400);
        }

        $result = $this->service->syncOrders($config);
        return $this->json($result);
    }

    #[Route('/api/prestashop/status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        $config = $this->repo->findOneBy([]);
        if (!$config || !$config->getIsActive()) {
            return $this->json(['connected' => false]);
        }

        return $this->json([
            'connected'   => true,
            'shopUrl'     => $config->getShopUrl(),
            'lastSync'    => $config->getLastSync() ? $config->getLastSync()->format('Y-m-d H:i:s') : null,
            'lastOrderId' => $config->getLastOrderId(),
        ]);
    }
}