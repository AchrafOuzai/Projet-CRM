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

class WoocommerceService
{
    private $em;
    private $tiersRepo;
    private $contactRepo;
    private $commandeRepo;
    private int $referentCounter = 0;
    /** @var array<string, Tiers> Cache email→Tiers créés en mémoire durant la sync */
    private array $tiersCache = [];

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

    private function curlRequest(string $url, string $u, string $p): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, $u . ':' . $p);
        curl_setopt($ch, CURLOPT_USERAGENT, 'CRM-Sync/1.0');
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);
        return ['response' => $response, 'httpCode' => $httpCode, 'error' => $error];
    }

    private function parseApiKey(string $apiKey): array
    {
        $parts = explode(':', $apiKey, 2);
        return ['username' => $parts[0] ?? '', 'password' => $parts[1] ?? ''];
    }

    private function mapStatut(string $status): string
    {
        $map = [
            'pending'    => 'Attente paiement',
            'processing' => 'En cours',
            'on-hold'    => 'En attente',
            'completed'  => 'Terminée',
            'cancelled'  => 'Annulée',
            'refunded'   => 'Remboursée',
            'failed'     => 'Échouée',
            'trash'      => 'Supprimée',
        ];
        return $map[$status] ?? ucfirst($status);
    }

    public function testConnection(string $shopUrl, string $apiKey): array
    {
        $keys   = $this->parseApiKey($apiKey);
        $url    = rtrim($shopUrl, '/') . '/wp-json/wc/v3/orders?per_page=1';
        $result = $this->curlRequest($url, $keys['username'], $keys['password']);
        if ($result['error'])
            return ['success' => false, 'message' => 'Erreur curl: ' . $result['error']];
        if ($result['httpCode'] === 200)
            return ['success' => true, 'message' => 'Connexion WooCommerce réussie',
                    'name' => parse_url($shopUrl, PHP_URL_HOST)];
        return ['success' => false,
                'message' => 'HTTP ' . $result['httpCode'] . ' : ' . substr($result['response'], 0, 200)];
    }

    public function syncOrders(EcommerceConfig $config): array
    {
        $this->referentCounter = 0; // ✅ Reset
        $this->tiersCache = [];     // ✅ Reset cache mémoire
        $tenant    = $config->getTenant();
        $keys      = $this->parseApiKey($config->getApiKey());
        $shopUrl   = rtrim($config->getShopUrl(), '/');
        $lastId    = $config->getLastOrderId() ?? 0;
        $newOrders = 0;
        $updOrders = 0;
        $errors    = [];

        $url    = $shopUrl . '/wp-json/wc/v3/orders?per_page=50&orderby=id&order=asc';
        $result = $this->curlRequest($url, $keys['username'], $keys['password']);

        if ($result['httpCode'] !== 200)
            return ['success' => false,
                    'message' => 'Erreur WooCommerce: HTTP ' . $result['httpCode'],
                    'newOrders' => 0];

        $orders = json_decode($result['response'], true) ?? [];

        foreach ($orders as $order) {
            try {
                $ref      = 'WC-' . $order['id'];
                $existing = $this->commandeRepo->findOneBy(['ref' => $ref, 'tenant' => $tenant]);

                if ($existing) {
                    $existing->setStatutEcommerce($this->mapStatut($order['status'] ?? ''));
                    $existing->setPrixVenteTotal((float)($order['total'] ?? 0));
                    $existing->setModePaiement($order['payment_method_title'] ?? $order['payment_method'] ?? '');
                    $existing->setFraisLivraison((float)($order['shipping_total'] ?? 0));
                    if ((int)$order['id'] > $lastId) $lastId = (int)$order['id'];
                    $updOrders++;
                    continue;
                }

                $billing  = $order['billing']  ?? [];
                $shipping = $order['shipping'] ?? [];

                if (!empty($order['customer_id'])) {
                    $cd = $this->getCustomer($shopUrl, $keys['username'], $keys['password'], (int)$order['customer_id']);
                    if (!empty($cd)) {
                        if (empty($billing['first_name'])) $billing['first_name'] = $cd['first_name'] ?? '';
                        if (empty($billing['last_name']))  $billing['last_name']  = $cd['last_name']  ?? '';
                        if (empty($billing['email']))      $billing['email']      = $cd['email']      ?? '';
                        if (empty($billing['first_name']) && empty($billing['last_name']))
                            $billing['first_name'] = $cd['username'] ?? '';
                    }
                }

                $tiers   = $this->syncCustomerToTiers($billing, $shipping, 'woocommerce', $tenant);
                $nom     = trim(($billing['first_name'] ?? '') . ' ' . ($billing['last_name'] ?? ''));
                $nbItems = count($order['line_items'] ?? []);

                $commande = new Commande();
                $commande->setDate(new \DateTime($order['date_created'] ?? 'now'));
                $commande->setClient($nom ?: ($billing['email'] ?? 'Client WooCommerce'));
                $commande->setEmailClient($billing['email'] ?? null);
                $commande->setTelephone($billing['phone'] ?? '');
                $commande->setAdresse(trim(($billing['address_1'] ?? '') . ' ' . ($billing['address_2'] ?? '')));
                $commande->setVille($billing['city'] ?? $shipping['city'] ?? '');
                $commande->setPays($billing['country'] ?? $shipping['country'] ?? '');
                $commande->setDesignation('Commande WooCommerce #' . $order['id']);
                $commande->setRef($ref);
                $commande->setPrixVenteTotal((float)($order['total'] ?? 0));
                $commande->setQuantite($nbItems ?: 1);
                $commande->setTypeCde('WooCommerce');
                $commande->setSource('woocommerce');
                $commande->setStatutEcommerce($this->mapStatut($order['status'] ?? ''));
                $commande->setModePaiement($order['payment_method_title'] ?? $order['payment_method'] ?? '');
                $commande->setConfirmation('Confirmée');
                $commande->setLivraison('En attente');
                $commande->setFraisLivraison((float)($order['shipping_total'] ?? 0));
                $commande->setCreatedAt(new \DateTimeImmutable());
                $commande->setCommentaire(
                    'Importé depuis WooCommerce' .
                    (!empty($order['customer_note']) ? ' — Note: ' . $order['customer_note'] : '')
                );
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
        $this->tiersCache = [];     // ✅ Reset cache mémoire
        $tenant       = $config->getTenant();
        $keys         = $this->parseApiKey($config->getApiKey());
        $shopUrl      = rtrim($config->getShopUrl(), '/');
        $newCustomers = 0;
        $errors       = [];

        $url    = $shopUrl . '/wp-json/wc/v3/customers?per_page=100&role=all';
        $result = $this->curlRequest($url, $keys['username'], $keys['password']);

        if ($result['httpCode'] !== 200)
            return ['success' => false,
                    'message' => 'Erreur API: HTTP ' . $result['httpCode'],
                    'newCustomers' => 0];

        $customers = json_decode($result['response'], true) ?? [];

        foreach ($customers as $customer) {
            try {
                $billing = $customer['billing'] ?? [];
                if (empty($billing['first_name'])) $billing['first_name'] = $customer['first_name'] ?? '';
                if (empty($billing['last_name']))  $billing['last_name']  = $customer['last_name']  ?? '';
                $billing['email'] = $customer['email'] ?? $billing['email'] ?? null;
                if (empty($billing['first_name']) && empty($billing['last_name']))
                    $billing['first_name'] = $customer['username'] ?? '';
                if (empty($billing['first_name']) && empty($billing['last_name']) && !empty($billing['email']))
                    $billing['first_name'] = explode('@', $billing['email'])[0];

                $tiers = $this->syncCustomerToTiers($billing, [], 'woocommerce', $tenant);
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
        array   $billing,
        array   $shipping,
        string  $source,
        ?Tenant $tenant
    ): ?Tiers {
        $nom   = trim(($billing['first_name'] ?? '') . ' ' . ($billing['last_name'] ?? ''));
        $email = $billing['email'] ?? null;

        if (!$nom && $email) $nom = explode('@', $email)[0];
        if (!$nom) return null;

        // ✅ 1. Cache mémoire intra-sync
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
        $tiers->setTelephone($billing['phone'] ?? null);
        $tiers->setAdresse(trim(($billing['address_1'] ?? '') . ' ' . ($billing['address_2'] ?? '')) ?: null);
        $tiers->setVille($billing['city']          ?? $shipping['city']     ?? null);
        $tiers->setCodePostal($billing['postcode'] ?? $shipping['postcode'] ?? null);
        $tiers->setPays($billing['country']        ?? $shipping['country']  ?? null);
        $tiers->setTypeTiers(['Client']);
        $tiers->setNatureTiers('Particulier');
        $tiers->setEtat('Actif');
        $tiers->setSource($source);
        $tiers->setReferent($this->generateReferent($tenant));
        $tiers->setTenant($tenant);

        $this->em->persist($tiers);

        $contact = new Contact();
        $contact->setNom($billing['last_name'] ?? $nom);
        $contact->setPrenom($billing['first_name'] ?? null);
        $contact->setEmail($email);
        $contact->setTelephone($billing['phone'] ?? null);
        $contact->setEtat('Actif');
        $contact->setVisibilite($source);
        $contact->setTiers($tiers);
        $this->em->persist($contact);

        // ✅ Flush immédiat pour attribuer l'ID avant la prochaine itération
        $this->em->flush();

        // ✅ Mise en cache mémoire
        $this->tiersCache[$cacheKey] = $tiers;

        return $tiers;
    }

    private function generateReferent(?Tenant $tenant = null): string
    {
        if ($this->referentCounter === 0) {
            // ✅ Filtre par tenant
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

    private function getCustomer(string $shopUrl, string $u, string $p, int $id): array
    {
        if (!$id) return [];
        $result = $this->curlRequest(rtrim($shopUrl, '/') . '/wp-json/wc/v3/customers/' . $id, $u, $p);
        if ($result['httpCode'] !== 200) return [];
        return json_decode($result['response'], true) ?? [];
    }

    public function syncRetours(EcommerceConfig $config): array
    {
        $this->referentCounter = 0;
        $this->tiersCache = []; // ✅ Reset cache
        $tenant     = $config->getTenant();
        $keys       = $this->parseApiKey($config->getApiKey());
        $shopUrl    = rtrim($config->getShopUrl(), '/');
        $newRetours = 0;
        $errors     = [];
        $statuses   = ['refunded', 'cancelled'];

        foreach ($statuses as $status) {
            $url    = $shopUrl . '/wp-json/wc/v3/orders?per_page=50&status=' . $status;
            $result = $this->curlRequest($url, $keys['username'], $keys['password']);
            if ($result['httpCode'] !== 200) continue;

            $orders = json_decode($result['response'], true) ?? [];

            foreach ($orders as $order) {
                $billing = $order['billing'] ?? [];
                $email   = $billing['email'] ?? null;

                if ($status === 'cancelled') {
                    try {
                        $ref      = 'WC-CANCEL-' . $order['id'];
                        $existing = $this->em->getRepository(\App\Entity\Retour::class)
                                             ->findOneBy(['ref' => $ref, 'tenant' => $tenant]);
                        if ($existing) continue;

                        $refCommande = 'WC-' . $order['id'];
                        $commande    = $this->commandeRepo->findOneBy(['ref' => $refCommande, 'tenant' => $tenant]);
                        $tiers       = $email
                            ? $this->tiersRepo->findOneBy(['email' => $email, 'tenant' => $tenant])
                            : null;
                        $nom = trim(($billing['first_name'] ?? '') . ' ' . ($billing['last_name'] ?? ''));

                        $retour = new \App\Entity\Retour();
                        $retour->setRef($ref)->setSource('woocommerce')->setRefCommande($refCommande);
                        $retour->setDate(new \DateTime($order['date_modified'] ?? 'now'));
                        $retour->setClient($nom ?: ($email ?? 'Client WooCommerce'));
                        $retour->setEmailClient($email)->setTelephone($billing['phone'] ?? null);
                        $retour->setMontantRembourse((float)($order['total'] ?? 0));
                        $retour->setMotif('Commande annulée')->setStatut('Annulée');
                        $retour->setVille($billing['city'] ?? null)->setPays($billing['country'] ?? null);
                        $retour->setCommentaire('Commande WooCommerce annulée #' . $order['id']);
                        $retour->setCreatedAt(new \DateTimeImmutable())->setTenant($tenant);
                        if ($tiers)    $retour->setTiers($tiers);
                        if ($commande) $retour->setCommande($commande);

                        $this->em->persist($retour);
                        $newRetours++;
                    } catch (\Exception $e) {
                        $errors[] = 'Annulation #' . ($order['id'] ?? '?') . ': ' . $e->getMessage();
                    }
                    continue;
                }

                $refundsUrl    = $shopUrl . '/wp-json/wc/v3/orders/' . $order['id'] . '/refunds';
                $refundsResult = $this->curlRequest($refundsUrl, $keys['username'], $keys['password']);
                if ($refundsResult['httpCode'] !== 200) continue;
                $refunds = json_decode($refundsResult['response'], true) ?? [];

                $refundList = empty($refunds)
                    ? [['id' => $order['id'], 'amount' => $order['total'], 'date_created' => $order['date_modified'], 'reason' => '', '_fallback' => true]]
                    : $refunds;

                foreach ($refundList as $refund) {
                    try {
                        $isFallback = $refund['_fallback'] ?? false;
                        $ref        = $isFallback ? 'WC-REFUND-' . $order['id'] : 'WC-REFUND-' . $refund['id'];
                        $existing   = $this->em->getRepository(\App\Entity\Retour::class)
                                               ->findOneBy(['ref' => $ref, 'tenant' => $tenant]);
                        if ($existing) continue;

                        $refCommande = 'WC-' . $order['id'];
                        $commande    = $this->commandeRepo->findOneBy(['ref' => $refCommande, 'tenant' => $tenant]);
                        $tiers       = $email
                            ? $this->tiersRepo->findOneBy(['email' => $email, 'tenant' => $tenant])
                            : null;
                        $nom     = trim(($billing['first_name'] ?? '') . ' ' . ($billing['last_name'] ?? ''));
                        $montant = abs((float)($refund['amount'] ?? 0));
                        if ($montant === 0.0) $montant = abs((float)($order['total'] ?? 0));

                        $retour = new \App\Entity\Retour();
                        $retour->setRef($ref)->setSource('woocommerce')->setRefCommande($refCommande);
                        $retour->setDate(new \DateTime($refund['date_created'] ?? 'now'));
                        $retour->setClient($nom ?: ($email ?? 'Client WooCommerce'));
                        $retour->setEmailClient($email)->setTelephone($billing['phone'] ?? null);
                        $retour->setMontantRembourse($montant);
                        $retour->setMotif(!empty($refund['reason']) ? $refund['reason'] : 'Remboursement WooCommerce');
                        $retour->setStatut('Remboursé');
                        $retour->setVille($billing['city'] ?? null)->setPays($billing['country'] ?? null);
                        $retour->setCommentaire('Importé depuis WooCommerce — Refund #' . ($refund['id'] ?? $order['id']));
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
        }

        $this->em->flush();

        return ['success' => true, 'newRetours' => $newRetours, 'errors' => $errors,
                'message' => $newRetours . ' retour(s) importé(s) depuis WooCommerce'];
    }
}