<?php

namespace App\Service;

use App\Entity\Commande;
use App\Entity\EcommerceConfig;
use App\Entity\Tiers;
use App\Entity\Contact;
use App\Entity\Tenant;
use App\Repository\TiersRepository;
use App\Repository\ContactRepository;
use App\Repository\CommandeRepository;
use Doctrine\ORM\EntityManagerInterface;

class ShopifyService
{
    private $em;
    private $tiersRepo;
    private $contactRepo;
    private $commandeRepo;
    private int $referentCounter = 0;

    public function __construct(
        EntityManagerInterface $em,
        TiersRepository        $tiersRepo,
        ContactRepository      $contactRepo,
        CommandeRepository     $commandeRepo
    ) {
        $this->em           = $em;
        $this->tiersRepo    = $tiersRepo;
        $this->contactRepo  = $contactRepo;
        $this->commandeRepo = $commandeRepo;
    }

    // ✅ Ajoute en haut de chaque service, appelé avant curlRequest
private function resolveUrl(string $url): string
{
    // En Docker, localhost → host.docker.internal
    if (getenv('DOCKERIZED') === 'true') {
        return str_replace(
            ['http://localhost', 'https://localhost'],
            ['http://host.docker.internal', 'https://host.docker.internal'],
            $url
        );
    }
    return $url;
}

    private function curlRequest(string $url, string $accessToken): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-Shopify-Access-Token: ' . $accessToken,
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_USERAGENT, 'CRM-Sync/1.0');
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);
        return ['response' => $response, 'httpCode' => $httpCode, 'error' => $error];
    }

    private function extractNom(array $customer, array $billing = [], array $shipping = []): string
    {
        foreach ([$customer, $billing, $shipping] as $src) {
            $nom = trim(($src['first_name'] ?? '') . ' ' . ($src['last_name'] ?? ''));
            if ($nom) return $nom;
            $nom = trim($src['name'] ?? '');
            if ($nom) return $nom;
        }
        $email = $customer['email'] ?? $billing['email'] ?? null;
        if ($email) return explode('@', $email)[0];
        if (!empty($customer['id'])) return 'Client-SH-' . $customer['id'];
        return 'Client Shopify';
    }

    private function findExistingTiersByShopifyId(int $shopifyId, ?Tenant $tenant): ?Tiers
    {
        return $this->tiersRepo->findOneBy([
            'nomAlternatif' => 'SH-' . $shopifyId,
            'tenant'        => $tenant
        ]);
    }

    private function mapFulfillment(string $status): string
    {
        $map = [
            'fulfilled'   => 'Livrée',
            'partial'     => 'Partiellement livrée',
            'unfulfilled' => 'En attente',
            'restocked'   => 'Retour',
        ];
        return $map[$status] ?? ucfirst($status);
    }

    public function testConnection(string $shopUrl, string $accessToken): array
    {
        $url    = rtrim($shopUrl, '/') . '/admin/api/2024-01/shop.json';
        $result = $this->curlRequest($url, $accessToken);
        if ($result['error'])
            return ['success' => false, 'message' => 'Erreur curl: ' . $result['error']];
        if ($result['httpCode'] === 200) {
            $data = json_decode($result['response'], true);
            return ['success' => true, 'message' => 'Connexion Shopify réussie',
                    'name' => $data['shop']['name'] ?? parse_url($shopUrl, PHP_URL_HOST)];
        }
        return ['success' => false,
                'message' => 'HTTP ' . $result['httpCode'] . ' : ' . substr($result['response'], 0, 200)];
    }

    public function syncOrders(EcommerceConfig $config): array
    {
        $this->referentCounter = 0; // ✅ Reset
        $tenant      = $config->getTenant();
        $shopUrl     = rtrim($config->getShopUrl(), '/');
        $accessToken = $config->getApiKey();
        $lastId      = $config->getLastOrderId() ?? 0;
        $newOrders   = 0;
        $updOrders   = 0;
        $errors      = [];

        $url    = $shopUrl . '/admin/api/2024-01/orders.json?status=any&limit=50&order=id+asc'
                . ($lastId ? '&since_id=' . $lastId : '');
        $result = $this->curlRequest($url, $accessToken);

        if ($result['httpCode'] !== 200)
            return ['success' => false,
                    'message' => 'Erreur API Shopify: HTTP ' . $result['httpCode'],
                    'newOrders' => 0];

        $data   = json_decode($result['response'], true);
        $orders = $data['orders'] ?? [];

        foreach ($orders as $order) {
            try {
                $ref      = 'SH-' . $order['id'];
                $existing = $this->commandeRepo->findOneBy(['ref' => $ref, 'tenant' => $tenant]);

                if ($existing) {
                    $existing->setStatutEcommerce($this->mapFulfillment($order['fulfillment_status'] ?? 'unfulfilled'));
                    $existing->setPrixVenteTotal((float)($order['total_price'] ?? 0));
                    $existing->setModePaiement($order['payment_gateway'] ?? '');
                    $existing->setFraisLivraison(
                        (float)($order['total_shipping_price_set']['shop_money']['amount'] ?? 0)
                    );
                    if ((int)$order['id'] > $lastId) $lastId = (int)$order['id'];
                    $updOrders++;
                    continue;
                }

                $customer = $order['customer']         ?? [];
                $billing  = $order['billing_address']  ?? [];
                $shipping = $order['shipping_address'] ?? [];
                $nom      = $this->extractNom($customer, $billing, $shipping);
                $tiers    = $this->syncCustomerToTiers($customer, $billing, $shipping, 'shopify', $tenant);

                $commande = new Commande();
                $commande->setDate(new \DateTime($order['created_at'] ?? 'now'));
                $commande->setClient($nom);
                $commande->setEmailClient($customer['email'] ?? $billing['email'] ?? null);
                $commande->setTelephone($billing['phone'] ?? $shipping['phone'] ?? $customer['phone'] ?? '');
                $commande->setAdresse(trim(($billing['address1'] ?? '') . ' ' . ($billing['address2'] ?? '')));
                $commande->setVille($billing['city'] ?? $shipping['city'] ?? '');
                $commande->setPays($billing['country'] ?? $shipping['country'] ?? '');
                $commande->setDesignation('Commande Shopify #' . ($order['order_number'] ?? $order['id']));
                $commande->setRef($ref);
                $commande->setPrixVenteTotal((float)($order['total_price'] ?? 0));
                $commande->setQuantite(array_sum(array_column($order['line_items'] ?? [], 'quantity')) ?: 1);
                $commande->setTypeCde('Shopify');
                $commande->setSource('shopify');
                $commande->setStatutEcommerce($this->mapFulfillment($order['fulfillment_status'] ?? 'unfulfilled'));
                $commande->setModePaiement($order['payment_gateway'] ?? '');
                $commande->setConfirmation('Confirmée');
                $commande->setLivraison('En attente');
                $commande->setFraisLivraison(
                    (float)($order['total_shipping_price_set']['shop_money']['amount'] ?? 0)
                );
                $commande->setCreatedAt(new \DateTimeImmutable());
                $commande->setCommentaire('Importé depuis Shopify' . (!empty($order['note']) ? ' — ' . $order['note'] : ''));
                $commande->setAgent('');
                $commande->setWhatsap('');
                $commande->setTenant($tenant);
                if ($tiers) $commande->setTiers($tiers);

                $this->em->persist($commande);
                if ((int)$order['id'] > $lastId) $lastId = (int)$order['id'];
                $newOrders++;

            } catch (\Exception $e) {
                $errors[] = 'Commande #' . ($order['id'] ?? '?') . ': ' . $e->getMessage();
            }
        }

        $config->setLastOrderId($lastId);
        $config->setLastSync(new \DateTimeImmutable());
        $this->em->flush();

        return [
            'success'   => true,
            'newOrders' => $newOrders,
            'updOrders' => $updOrders,
            'errors'    => $errors,
            'message'   => $newOrders . ' nouvelle(s) commande(s), ' . $updOrders . ' mise(s) à jour'
        ];
    }

    public function syncCustomers(EcommerceConfig $config): array
    {
        $this->referentCounter = 0; // ✅ Reset
        $tenant       = $config->getTenant();
        $shopUrl      = rtrim($config->getShopUrl(), '/');
        $accessToken  = $config->getApiKey();
        $newCustomers = 0;
        $errors       = [];

        $url    = $shopUrl . '/admin/api/2024-01/customers.json?limit=100';
        $result = $this->curlRequest($url, $accessToken);

        if ($result['httpCode'] !== 200)
            return ['success' => false,
                    'message' => 'Erreur API Shopify clients: HTTP ' . $result['httpCode']
                                . ' — ' . substr($result['response'], 0, 200),
                    'newCustomers' => 0];

        $customers = json_decode($result['response'], true)['customers'] ?? [];

        foreach ($customers as $customer) {
            try {
                $shopifyId      = (int)($customer['id'] ?? 0);
                $defaultAddress = $customer['default_address'] ?? [];

                if ($shopifyId && $this->findExistingTiersByShopifyId($shopifyId, $tenant)) continue;

                $billing = [
                    'email'      => $customer['email']      ?? null,
                    'first_name' => $customer['first_name'] ?? $defaultAddress['first_name'] ?? '',
                    'last_name'  => $customer['last_name']  ?? $defaultAddress['last_name']  ?? '',
                    'name'       => $customer['name']       ?? $defaultAddress['name']        ?? '',
                    'phone'      => $customer['phone']      ?? $defaultAddress['phone']       ?? null,
                    'address1'   => $defaultAddress['address1']   ?? null,
                    'address2'   => $defaultAddress['address2']   ?? null,
                    'city'       => $defaultAddress['city']       ?? null,
                    'zip'        => $defaultAddress['zip']        ?? null,
                    'country'    => $defaultAddress['country']    ?? null,
                ];

                $tiers = $this->syncCustomerToTiers($customer, $billing, [], 'shopify', $tenant);
                if ($tiers) $newCustomers++;

            } catch (\Exception $e) {
                $errors[] = 'Client #' . ($customer['id'] ?? '?') . ': ' . $e->getMessage();
            }
        }

        $config->setLastCustomerSync(new \DateTimeImmutable());
        $this->em->flush();

        return ['success' => true, 'newCustomers' => $newCustomers, 'errors' => $errors,
                'message' => $newCustomers . ' client(s) synchronisé(s)'];
    }

    private function syncCustomerToTiers(
        array   $customer,
        array   $billing,
        array   $shipping,
        string  $source,
        ?Tenant $tenant
    ): ?Tiers {
        $shopifyId = (int)($customer['id'] ?? 0);
        $email     = $customer['email'] ?? $billing['email'] ?? null;
        $nom       = $this->extractNom($customer, $billing, $shipping);

        if ($shopifyId) {
            $existing = $this->findExistingTiersByShopifyId($shopifyId, $tenant);
            if ($existing) return $existing;
        }

        if ($email) {
            $existing = $this->tiersRepo->findOneBy(['email' => $email, 'tenant' => $tenant]);
            if ($existing) return $existing;

            $contacts = $this->contactRepo->findBy(['email' => $email]);
            foreach ($contacts as $contact) {
                $t = $contact->getTiers();
                if ($t && $t->getTenant() && $tenant &&
                    $t->getTenant()->getId() === $tenant->getId()) {
                    return $t;
                }
            }
        }

        $tiers = new Tiers();
        $tiers->setNom($nom);
        $tiers->setEmail($email);
        $tiers->setNomAlternatif($shopifyId ? 'SH-' . $shopifyId : null);
        $tiers->setTelephone($customer['phone'] ?? $billing['phone'] ?? null);
        $tiers->setAdresse(
            trim(($billing['address1'] ?? '') . ' ' . ($billing['address2'] ?? '')) ?: null
        );
        $tiers->setVille($billing['city']     ?? $shipping['city']    ?? null);
        $tiers->setCodePostal($billing['zip'] ?? $shipping['zip']     ?? null);
        $tiers->setPays($billing['country']   ?? $shipping['country'] ?? null);
        $tiers->setTypeTiers(['Client']);
        $tiers->setNatureTiers('Particulier');
        $tiers->setEtat('Actif');
        $tiers->setSource($source);
        $tiers->setReferent($this->generateReferent());
        $tiers->setTenant($tenant);

        $this->em->persist($tiers);

        $parts     = explode(' ', $nom, 2);
        $firstName = $customer['first_name'] ?? $billing['first_name'] ?? ($parts[0] ?? $nom);
        $lastName  = $customer['last_name']  ?? $billing['last_name']  ?? ($parts[1] ?? '');

        $contact = new Contact();
        $contact->setNom($lastName ?: $nom);
        $contact->setPrenom($firstName ?: null);
        $contact->setEmail($email);
        $contact->setTelephone($customer['phone'] ?? null);
        $contact->setEtat('Actif');
        $contact->setVisibilite($source);
        $contact->setTiers($tiers);
        $this->em->persist($contact);

        return $tiers;
    }

    private function generateReferent(): string
    {
        if ($this->referentCounter === 0) {
            $last = $this->em->getConnection()->fetchOne(
                "SELECT referent FROM tiers WHERE referent LIKE 'TI%'
                 ORDER BY LENGTH(referent) DESC, referent DESC LIMIT 1"
            );
            $this->referentCounter = ($last && preg_match('/^TI(\d+)$/', $last, $m))
                ? (int)$m[1] : 0;
        }
        $this->referentCounter++;
        return 'TI' . str_pad($this->referentCounter, 5, '0', STR_PAD_LEFT);
    }

    public function syncRetours(EcommerceConfig $config): array
    {
        $this->referentCounter = 0;
        $tenant      = $config->getTenant();
        $shopUrl     = rtrim($config->getShopUrl(), '/');
        $accessToken = $config->getApiKey();
        $newRetours  = 0;
        $errors      = [];

        $url    = $shopUrl . '/admin/api/2024-01/orders.json?status=any&financial_status=refunded&limit=50';
        $result = $this->curlRequest($url, $accessToken);

        if ($result['httpCode'] !== 200)
            return ['success' => false,
                    'message' => 'Erreur API Shopify: HTTP ' . $result['httpCode'],
                    'newRetours' => 0];

        $orders = json_decode($result['response'], true)['orders'] ?? [];

        foreach ($orders as $order) {
            $refunds  = $order['refunds'] ?? [];
            $billing  = $order['billing_address'] ?? [];
            $customer = $order['customer'] ?? [];
            $email    = $customer['email'] ?? null;

            foreach ($refunds as $refund) {
                try {
                    $ref      = 'SH-REFUND-' . $refund['id'];
                    $existing = $this->em->getRepository(\App\Entity\Retour::class)
                                         ->findOneBy(['ref' => $ref, 'tenant' => $tenant]);
                    if ($existing) continue;

                    $refCommande = 'SH-' . $order['id'];
                    $commande    = $this->commandeRepo->findOneBy(['ref' => $refCommande, 'tenant' => $tenant]);
                    $tiers       = null;

                    $shopifyCustomerId = (int)($customer['id'] ?? 0);
                    if ($shopifyCustomerId)
                        $tiers = $this->findExistingTiersByShopifyId($shopifyCustomerId, $tenant);
                    if (!$tiers && $email)
                        $tiers = $this->tiersRepo->findOneBy(['email' => $email, 'tenant' => $tenant]);

                    $montant = 0.0;
                    foreach ($refund['transactions'] ?? [] as $transaction)
                        $montant += abs((float)($transaction['amount'] ?? 0));
                    if ($montant === 0.0) $montant = abs((float)($order['total_price'] ?? 0));

                    $motifs = array_map(fn($li) => $li['reason'] ?? '', $refund['refund_line_items'] ?? []);
                    $nom    = $this->extractNom($customer, $billing);

                    $retour = new \App\Entity\Retour();
                    $retour->setRef($ref)->setSource('shopify')->setRefCommande($refCommande);
                    $retour->setDate(new \DateTime($refund['created_at'] ?? 'now'));
                    $retour->setClient($nom)->setEmailClient($email);
                    $retour->setTelephone($customer['phone'] ?? $billing['phone'] ?? null);
                    $retour->setMontantRembourse($montant);
                    $retour->setMotif(implode(', ', array_filter($motifs)) ?: 'Remboursement Shopify');
                    $retour->setStatut('Remboursé');
                    $retour->setVille($billing['city'] ?? null)->setPays($billing['country'] ?? null);
                    $retour->setCommentaire('Importé depuis Shopify — Note: ' . ($refund['note'] ?? 'aucune'));
                    $retour->setCreatedAt(new \DateTimeImmutable())->setTenant($tenant);
                    if ($tiers)    $retour->setTiers($tiers);
                    if ($commande) $retour->setCommande($commande);

                    $this->em->persist($retour);
                    $newRetours++;

                } catch (\Exception $e) {
                    $errors[] = 'Refund #' . ($refund['id'] ?? '?') . ': ' . $e->getMessage();
                }
            }
        }

        $this->em->flush();

        return ['success' => true, 'newRetours' => $newRetours, 'errors' => $errors,
                'message' => $newRetours . ' retour(s) importé(s) depuis Shopify'];
    }
}