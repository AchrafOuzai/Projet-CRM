<?php

namespace App\Service;

use App\Entity\Commande;
use App\Entity\EcommerceConfig;
use App\Entity\Tiers;
use App\Entity\Contact;
use App\Entity\Tenant;
use App\Repository\EcommerceConfigRepository;
use App\Repository\TiersRepository;
use App\Repository\ContactRepository;
use App\Repository\CommandeRepository;
use Doctrine\ORM\EntityManagerInterface;

class PrestashopService
{
    private $em;
    private $repo;
    private $tiersRepo;
    private $contactRepo;
    private $commandeRepo;
    private int $referentCounter = 0;
    /** @var array<string, Tiers> Cache email→Tiers créés en mémoire durant la sync */
    private array $tiersCache = [];

    public function __construct(
        EntityManagerInterface    $em,
        EcommerceConfigRepository $repo,
        TiersRepository           $tiersRepo,
        ContactRepository         $contactRepo,
        CommandeRepository        $commandeRepo
    ) {
        $this->em           = $em;
        $this->repo         = $repo;
        $this->tiersRepo    = $tiersRepo;
        $this->contactRepo  = $contactRepo;
        $this->commandeRepo = $commandeRepo;
    }

    // ✅ Résout localhost → host.docker.internal quand on est dans Docker
    private function resolveUrl(string $url): string
    {
        if (getenv('DOCKERIZED') === 'true') {
            return str_replace(
                ['http://localhost', 'https://localhost', 'http://127.0.0.1', 'https://127.0.0.1'],
                ['http://host.docker.internal', 'https://host.docker.internal', 'http://host.docker.internal', 'https://host.docker.internal'],
                $url
            );
        }
        return $url;
    }

    private function curlRequest(string $url): array
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // ✅ on gère la redirection nous-mêmes
    curl_setopt($ch, CURLOPT_USERAGENT, 'CRM-Sync/1.0');
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);

    // ✅ Si PrestaShop redirige (302), on réécrit l'URL vers host.docker.internal et on suit nous-mêmes
    if (in_array($httpCode, [301, 302, 307]) && getenv('DOCKERIZED') === 'true') {
        $location = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        curl_close($ch);

        if ($location) {
            $location = $this->resolveUrl($location); // réécrit localhost → host.docker.internal
            return $this->curlRequest($location); // suit manuellement, une seule fois en pratique
        }
    }

    curl_close($ch);
    return ['response' => $response, 'httpCode' => $httpCode, 'error' => $error];
}

    public function testConnection(string $shopUrl, string $apiKey): array
    {
        $shopUrl = $this->resolveUrl($shopUrl); // ✅
        $url    = rtrim($shopUrl, '/') . '/api/?ws_key=' . $apiKey . '&output_format=JSON';
        $result = $this->curlRequest($url);
        if ($result['error'])
            return ['success' => false, 'message' => 'Erreur curl: ' . $result['error']];
        if ($result['httpCode'] === 200) {
            $data = json_decode($result['response'], true);
            return ['success' => true, 'message' => 'Connexion réussie',
                    'name' => $data['api']['shop_name'] ?? parse_url($shopUrl, PHP_URL_HOST)];
        }
        return ['success' => false,
                'message' => 'HTTP ' . $result['httpCode'] . ' : ' . substr($result['response'], 0, 200)];
    }

    public function syncOrders(EcommerceConfig $config): array
    {
        $this->referentCounter = 0;
        $this->tiersCache = [];
        $tenant    = $config->getTenant();
        $shopUrl   = $this->resolveUrl(rtrim($config->getShopUrl(), '/')); // ✅
        $apiKey    = $config->getApiKey();
        $lastId    = $config->getLastOrderId() ?? 0;
        $newOrders = 0;
        $updOrders = 0;
        $errors    = [];

        $url    = $shopUrl . '/api/orders?ws_key=' . $apiKey
                . '&output_format=JSON&display=full&limit=50&sort=[id_ASC]';
        $result = $this->curlRequest($url);

        if ($result['httpCode'] !== 200)
            return ['success' => false,
                    'message' => 'Erreur API PrestaShop: HTTP ' . $result['httpCode'],
                    'newOrders' => 0];

        $data   = json_decode($result['response'], true);
        $orders = $data['orders'] ?? [];

        foreach ($orders as $order) {
            try {
                $ref      = 'PS-' . $order['id'];
                $existing = $this->commandeRepo->findOneBy(['ref' => $ref, 'tenant' => $tenant]);

                if ($existing) {
                    $orderState = $this->getOrderState($shopUrl, $apiKey, $order['current_state'] ?? 0);
                    $existing->setStatutEcommerce($orderState);
                    $existing->setPrixVenteTotal((float)($order['total_paid'] ?? 0));
                    $existing->setModePaiement($order['payment'] ?? '');
                    $existing->setFraisLivraison((float)($order['total_shipping'] ?? 0));
                    if ((int)$order['id'] > $lastId) $lastId = (int)$order['id'];
                    $updOrders++;
                    continue;
                }

                $customer   = $this->getCustomer($shopUrl, $apiKey, $order['id_customer'] ?? 0);
                $address    = $this->getAddress($shopUrl, $apiKey, $order['id_address_delivery'] ?? 0);
                $tiers      = $this->syncCustomerToTiers($customer, $address, 'prestashop', $tenant);
                $orderState = $this->getOrderState($shopUrl, $apiKey, $order['current_state'] ?? 0);

                $commande = new Commande();
                $commande->setDate(new \DateTime($order['date_add'] ?? 'now'));
                $commande->setClient(
                    trim(($customer['firstname'] ?? '') . ' ' . ($customer['lastname'] ?? ''))
                    ?: 'Client PrestaShop'
                );
                $commande->setEmailClient($customer['email'] ?? null);
                $commande->setTelephone($address['phone'] ?? $address['phone_mobile'] ?? '');
                $commande->setAdresse(
                    trim(($address['address1'] ?? '') . ' ' . ($address['address2'] ?? ''))
                );
                $commande->setVille($address['city'] ?? '');
                $commande->setPays($address['country'] ?? '');
                $commande->setDesignation('Commande PrestaShop #' . $order['id']);
                $commande->setRef($ref);
                $commande->setPrixVenteTotal((float)($order['total_paid'] ?? 0));
                $commande->setQuantite(1);
                $commande->setTypeCde('PrestaShop');
                $commande->setSource('prestashop');
                $commande->setStatutEcommerce($orderState);
                $commande->setModePaiement($order['payment'] ?? '');
                $commande->setConfirmation('Confirmée');
                $commande->setLivraison('En attente');
                $commande->setFraisLivraison((float)($order['total_shipping'] ?? 0));
                $commande->setCreatedAt(new \DateTimeImmutable());
                $commande->setCommentaire('Importé depuis PrestaShop');
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
        $this->referentCounter = 0;
        $this->tiersCache = [];
        $tenant       = $config->getTenant();
        $shopUrl      = $this->resolveUrl(rtrim($config->getShopUrl(), '/')); // ✅
        $apiKey       = $config->getApiKey();
        $newCustomers = 0;
        $errors       = [];

        $url    = $shopUrl . '/api/customers?ws_key=' . $apiKey
                . '&output_format=JSON&display=full&limit=100';
        $result = $this->curlRequest($url);

        if ($result['httpCode'] !== 200)
            return ['success' => false,
                    'message' => 'Erreur API: HTTP ' . $result['httpCode'],
                    'newCustomers' => 0];

        $data      = json_decode($result['response'], true);
        $customers = $data['customers'] ?? [];

        foreach ($customers as $customer) {
            try {
                $tiers = $this->syncCustomerToTiers($customer, [], 'prestashop', $tenant);
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
        array   $address,
        string  $source,
        ?Tenant $tenant
    ): ?Tiers {
        if (empty($customer)) return null;

        $email = $customer['email'] ?? null;
        $nom   = trim(($customer['firstname'] ?? '') . ' ' . ($customer['lastname'] ?? ''));
        if (!$nom && $email) $nom = explode('@', $email)[0];
        if (!$nom) return null;

        $cacheKey = ($email ?? $nom) . '_' . ($tenant ? $tenant->getId() : '0');
        if (isset($this->tiersCache[$cacheKey])) {
            return $this->tiersCache[$cacheKey];
        }

        if ($email) {
            $existing = $this->tiersRepo->findOneBy(['email' => $email, 'tenant' => $tenant]);
            if ($existing) {
                $this->tiersCache[$cacheKey] = $existing;
                return $existing;
            }
        }

        if ($email) {
            $contacts = $this->contactRepo->findBy(['email' => $email]);
            foreach ($contacts as $contact) {
                $t = $contact->getTiers();
                if ($t && $t->getTenant() && $tenant &&
                    $t->getTenant()->getId() === $tenant->getId()) {
                    $this->tiersCache[$cacheKey] = $t;
                    return $t;
                }
            }
        }

        $tiers = new Tiers();
        $tiers->setNom($nom);
        $tiers->setEmail($email);
        $tiers->setTelephone(
            $address['phone'] ?? $address['phone_mobile']
            ?? $customer['phone'] ?? $customer['phone_mobile'] ?? null
        );
        $tiers->setAdresse(
            trim(($address['address1'] ?? '') . ' ' . ($address['address2'] ?? '')) ?: null
        );
        $tiers->setVille($address['city'] ?? null);
        $tiers->setCodePostal($address['postcode'] ?? null);
        $tiers->setPays($address['country'] ?? null);
        $tiers->setTypeTiers(['Client']);
        $tiers->setNatureTiers('Particulier');
        $tiers->setEtat('Actif');
        $tiers->setSource($source);
        $tiers->setReferent($this->generateReferent($tenant));
        $tiers->setTenant($tenant);

        $this->em->persist($tiers);

        $contact = new Contact();
        $contact->setNom($customer['lastname'] ?? $nom);
        $contact->setPrenom($customer['firstname'] ?? null);
        $contact->setEmail($email);
        $contact->setTelephone($customer['phone'] ?? null);
        $contact->setTelPortable($customer['phone_mobile'] ?? null);
        $contact->setEtat('Actif');
        $contact->setVisibilite($source);
        $contact->setTiers($tiers);
        $this->em->persist($contact);

        $this->em->flush();

        $this->tiersCache[$cacheKey] = $tiers;

        return $tiers;
    }

    private function generateReferent(?Tenant $tenant = null): string
    {
        if ($this->referentCounter === 0) {
            $tenantId = $tenant ? $tenant->getId() : null;
            if ($tenantId) {
                $last = $this->em->getConnection()->fetchOne(
                    "SELECT referent FROM tiers WHERE referent LIKE 'TI%' AND tenant_id = ?
                     ORDER BY LENGTH(referent) DESC, referent DESC LIMIT 1",
                    [$tenantId]
                );
            } else {
                $last = $this->em->getConnection()->fetchOne(
                    "SELECT referent FROM tiers WHERE referent LIKE 'TI%'
                     ORDER BY LENGTH(referent) DESC, referent DESC LIMIT 1"
                );
            }
            $this->referentCounter = ($last && preg_match('/^TI(\d+)$/', $last, $m))
                ? (int)$m[1] : 0;
        }
        $this->referentCounter++;
        return 'TI' . str_pad($this->referentCounter, 5, '0', STR_PAD_LEFT);
    }

    private function getCustomer(string $shopUrl, string $apiKey, int $id): array
    {
        if (!$id) return [];
        $url    = rtrim($shopUrl, '/') . '/api/customers/' . $id
                . '?ws_key=' . $apiKey . '&output_format=JSON';
        $result = $this->curlRequest($url);
        if ($result['httpCode'] !== 200) return [];
        return json_decode($result['response'], true)['customer'] ?? [];
    }

    private function getAddress(string $shopUrl, string $apiKey, int $id): array
    {
        if (!$id) return [];
        $url    = rtrim($shopUrl, '/') . '/api/addresses/' . $id
                . '?ws_key=' . $apiKey . '&output_format=JSON';
        $result = $this->curlRequest($url);
        if ($result['httpCode'] !== 200) return [];
        return json_decode($result['response'], true)['address'] ?? [];
    }

    private function getOrderState(string $shopUrl, string $apiKey, int $id): string
    {
        if (!$id) return '';
        $url    = rtrim($shopUrl, '/') . '/api/order_states/' . $id
                . '?ws_key=' . $apiKey . '&output_format=JSON';
        $result = $this->curlRequest($url);
        if ($result['httpCode'] !== 200) return '';
        $data = json_decode($result['response'], true);
        $name = $data['order_state']['name'] ?? [];
        if (is_array($name)) {
            return $name[0]['value'] ?? $name['value'] ?? '';
        }
        return (string)$name;
    }

    public function syncRetours(EcommerceConfig $config): array
    {
        $this->referentCounter = 0;
        $this->tiersCache = [];
        $tenant     = $config->getTenant();
        $shopUrl    = $this->resolveUrl(rtrim($config->getShopUrl(), '/')); // ✅
        $apiKey     = $config->getApiKey();
        $newRetours = 0;
        $errors     = [];

        $url    = $shopUrl . '/api/order_slip?ws_key=' . $apiKey
                . '&output_format=JSON&display=full&limit=100';
        $result = $this->curlRequest($url);

        if ($result['httpCode'] !== 200) {
            return ['success' => false,
                    'message' => 'Erreur API PrestaShop order_slip: HTTP ' . $result['httpCode'],
                    'newRetours' => 0];
        }

        $data  = json_decode($result['response'], true);
        $slips = $data['order_slips'] ?? [];

        foreach ($slips as $slip) {
            try {
                $ref      = 'PS-SLIP-' . $slip['id'];
                $existing = $this->em->getRepository(\App\Entity\Retour::class)
                                     ->findOneBy(['ref' => $ref, 'tenant' => $tenant]);
                if ($existing) continue;

                $orderId     = $slip['id_order'] ?? 0;
                $refCommande = 'PS-' . $orderId;
                $commande    = $this->commandeRepo->findOneBy(['ref' => $refCommande, 'tenant' => $tenant]);

                $customer = [];
                if ($orderId) {
                    $orderUrl    = $shopUrl . '/api/orders/' . $orderId
                                 . '?ws_key=' . $apiKey . '&output_format=JSON';
                    $orderResult = $this->curlRequest($orderUrl);
                    if ($orderResult['httpCode'] === 200) {
                        $orderData  = json_decode($orderResult['response'], true);
                        $customerId = $orderData['order']['id_customer'] ?? 0;
                        if ($customerId)
                            $customer = $this->getCustomer($shopUrl, $apiKey, $customerId);
                    }
                }

                $tiers = null;
                if (!empty($customer['email']))
                    $tiers = $this->tiersRepo->findOneBy(['email' => $customer['email'], 'tenant' => $tenant]);

                $retour = new \App\Entity\Retour();
                $retour->setRef($ref);
                $retour->setSource('prestashop');
                $retour->setRefCommande($refCommande);
                $retour->setDate(new \DateTime($slip['date_add'] ?? 'now'));
                $retour->setClient(
                    trim(($customer['firstname'] ?? '') . ' ' . ($customer['lastname'] ?? ''))
                    ?: 'Client PrestaShop'
                );
                $retour->setEmailClient($customer['email'] ?? null);
                $retour->setTelephone($customer['phone'] ?? null);
                $retour->setMontantRembourse((float)($slip['amount'] ?? 0));
                $retour->setMotif($slip['reason'] ?? 'Retour PrestaShop');
                $retour->setStatut('En attente');
                $retour->setCommentaire('Importé depuis PrestaShop — Slip #' . $slip['id']);
                $retour->setCreatedAt(new \DateTimeImmutable());
                $retour->setTenant($tenant);

                if ($tiers)    $retour->setTiers($tiers);
                if ($commande) $retour->setCommande($commande);

                $this->em->persist($retour);
                $newRetours++;

            } catch (\Exception $e) {
                $errors[] = 'Slip #' . ($slip['id'] ?? '?') . ': ' . $e->getMessage();
            }
        }

        $this->em->flush();

        return ['success' => true, 'newRetours' => $newRetours, 'errors' => $errors,
                'message' => $newRetours . ' retour(s) importé(s) depuis PrestaShop'];
    }
}