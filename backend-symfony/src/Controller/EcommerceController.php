<?php

namespace App\Controller;

use App\Entity\EcommerceConfig;
use App\Repository\EcommerceConfigRepository;
use App\Service\PrestashopService;
use App\Service\WoocommerceService;
use App\Service\ShopifyService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class EcommerceController extends AbstractController
{
    private $em;
    private $repo;
    private $prestashopService;
    private $woocommerceService;
    private $shopifyService;

    public function __construct(
        EntityManagerInterface    $em,
        EcommerceConfigRepository $repo,
        PrestashopService         $prestashopService,
        WoocommerceService        $woocommerceService,
        ShopifyService            $shopifyService
    ) {
        $this->em                 = $em;
        $this->repo               = $repo;
        $this->prestashopService  = $prestashopService;
        $this->woocommerceService = $woocommerceService;
        $this->shopifyService     = $shopifyService;
    }

    #[Route('/api/ecommerce/status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        $platforms = ['prestashop', 'woocommerce', 'shopify'];
        $result    = [];

        foreach ($platforms as $type) {
            // Retourner TOUTES les configs de ce type (multi-instances)
            $configs = $this->repo->findBy(['type' => $type]);
            $result[$type] = array_map(function($config) {
                $lastSync         = $config->getLastSync();
                $lastCustomerSync = $config->getLastCustomerSync();
                return [
                    'id'               => $config->getId(),
                    'connected'        => $config->getIsActive(),
                    'shopUrl'          => $config->getShopUrl(),
                    'lastSync'         => $lastSync         ? $lastSync->format('Y-m-d H:i:s')         : null,
                    'lastOrderId'      => $config->getLastOrderId(),
                    'lastCustomerSync' => $lastCustomerSync ? $lastCustomerSync->format('Y-m-d H:i:s') : null,
                ];
            }, $configs);
        }

        return $this->json($result);
    }

    #[Route('/api/ecommerce/connect', methods: ['POST'])]
    public function connect(Request $request): JsonResponse
    {
        $data   = json_decode($request->getContent(), true);
        $type   = $data['type']   ?? '';
        $url    = $data['url']    ?? '';
        $apiKey = $data['apiKey'] ?? '';

        if (!in_array($type, ['prestashop', 'woocommerce', 'shopify']))
            return $this->json(['success' => false, 'message' => 'Type invalide'], 400);

        if (!$url || !$apiKey)
            return $this->json(['success' => false, 'message' => 'URL et clé API requises'], 400);

        // Test connexion
        if ($type === 'prestashop')
            $result = $this->prestashopService->testConnection($url, $apiKey);
        elseif ($type === 'woocommerce')
            $result = $this->woocommerceService->testConnection($url, $apiKey);
        else
            $result = $this->shopifyService->testConnection($url, $apiKey);

        if (!$result['success'])
            return $this->json(['success' => false, 'message' => $result['message']], 400);

        // Créer une NOUVELLE instance (multi-connexion)
        $config = new EcommerceConfig();
        $config->setType($type);
        $config->setShopUrl($url);
        $config->setApiKey($apiKey);
        $config->setIsActive(true);
        $config->setLastSync(new \DateTimeImmutable());

        $this->em->persist($config);
        $this->em->flush();

        return $this->json([
            'success' => true,
            'id'      => $config->getId(),
            'message' => 'Connexion ' . ucfirst($type) . ' réussie !',
            'name'    => $result['name'] ?? parse_url($url, PHP_URL_HOST)
        ]);
    }

    #[Route('/api/ecommerce/disconnect', methods: ['POST'])]
    public function disconnect(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $id   = $data['id']   ?? null;
        $type = $data['type'] ?? '';

        if ($id) {
            $config = $this->repo->find($id);
        } else {
            // Déconnecter la plus récente du type
            $configs = $this->repo->findBy(['type' => $type], ['id' => 'DESC']);
            $config  = $configs[0] ?? null;
        }

        if ($config) {
            $config->setIsActive(false);
            $this->em->flush();
        }

        return $this->json(['success' => true]);
    }

    #[Route('/api/ecommerce/sync/orders', methods: ['POST'])]
    public function syncOrders(Request $request): JsonResponse
    {
        $data   = json_decode($request->getContent(), true);
        $type   = $data['type'] ?? '';
        $id     = $data['id']   ?? null;

        $config = $id
            ? $this->repo->find($id)
            : $this->repo->findOneBy(['type' => $type, 'isActive' => true]);

        if (!$config)
            return $this->json(['success' => false, 'message' => ucfirst($type) . ' non connecté'], 400);

        if ($type === 'prestashop')
            $result = $this->prestashopService->syncOrders($config);
        elseif ($type === 'woocommerce')
            $result = $this->woocommerceService->syncOrders($config);
        elseif ($type === 'shopify')
            $result = $this->shopifyService->syncOrders($config);
        else
            $result = ['success' => false, 'message' => 'Type non supporté'];

        return $this->json($result);
    }

    #[Route('/api/ecommerce/sync/customers', methods: ['POST'])]
    public function syncCustomers(Request $request): JsonResponse
    {
        $data   = json_decode($request->getContent(), true);
        $type   = $data['type'] ?? '';
        $id     = $data['id']   ?? null;

        $config = $id
            ? $this->repo->find($id)
            : $this->repo->findOneBy(['type' => $type, 'isActive' => true]);

        if (!$config)
            return $this->json(['success' => false, 'message' => ucfirst($type) . ' non connecté'], 400);

        if ($type === 'prestashop')
            $result = $this->prestashopService->syncCustomers($config);
        elseif ($type === 'woocommerce')
            $result = $this->woocommerceService->syncCustomers($config);
        elseif ($type === 'shopify')
            $result = $this->shopifyService->syncCustomers($config);
        else
            $result = ['success' => false, 'message' => 'Type non supporté'];

        return $this->json($result);
    }
    #[Route('/api/ecommerce/sync/retours', methods: ['POST'])]
public function syncRetours(Request $request): JsonResponse
{
    $data   = json_decode($request->getContent(), true);
    $type   = $data['type'] ?? '';
    $id     = $data['id']   ?? null;

    $config = $id
        ? $this->repo->find($id)
        : $this->repo->findOneBy(['type' => $type, 'isActive' => true]);

    if (!$config)
        return $this->json(['success' => false, 'message' => ucfirst($type) . ' non connecté'], 400);

    if ($type === 'prestashop')
        $result = $this->prestashopService->syncRetours($config);
    elseif ($type === 'woocommerce')
        $result = $this->woocommerceService->syncRetours($config);
    elseif ($type === 'shopify')
        $result = $this->shopifyService->syncRetours($config);
    else
        $result = ['success' => false, 'message' => 'Type non supporté'];

    return $this->json($result);
}
}