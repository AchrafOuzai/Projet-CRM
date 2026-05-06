<?php

namespace App\Service;

use App\Entity\Commande;
use App\Entity\EcommerceConfig;
use App\Entity\Tiers;
use App\Entity\Contact;
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

    public function __construct(
        EntityManagerInterface $em,
        EcommerceConfigRepository $repo,
        TiersRepository $tiersRepo,
        ContactRepository $contactRepo,
        CommandeRepository $commandeRepo
    ) {
        $this->em           = $em;
        $this->repo         = $repo;
        $this->tiersRepo    = $tiersRepo;
        $this->contactRepo  = $contactRepo;
        $this->commandeRepo = $commandeRepo;
    }

    private function curlRequest(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_USERAGENT, 'CRM-Sync/1.0');
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);
        return ['response' => $response, 'httpCode' => $httpCode, 'error' => $error];
    }

    public function testConnection(string $shopUrl, string $apiKey): array
    {
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
        $shopUrl   = rtrim($config->getShopUrl(), '/');
        $apiKey    = $config->getApiKey();
        $lastId    = $config->getLastOrderId() ?? 0;
        $newOrders = 0;
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
                $existing = $this->commandeRepo->findOneBy(['ref' => $ref]);
                if ($existing) {
                    if ((int)$order['id'] > $lastId) $lastId = (int)$order['id'];
                    continue;
                }

                $customer = $this->getCustomer($shopUrl, $apiKey, $order['id_customer'] ?? 0);
                $address  = $this->getAddress($shopUrl, $apiKey, $order['id_address_delivery'] ?? 0);
                $tiers    = $this->syncCustomerToTiers($customer, $address, 'prestashop');

                // Statut PrestaShop
                $orderState = $this->getOrderState(
                    $shopUrl, $apiKey, $order['current_state'] ?? 0
                );

                $commande = new Commande();
                $commande->setDate(new \DateTime($order['date_add'] ?? 'now'));
                $commande->setClient(
                    trim(($customer['firstname'] ?? '') . ' ' . ($customer['lastname'] ?? ''))
                    ?: 'Client PrestaShop'
                );
                $commande->setEmailClient($customer['email'] ?? null);
                $commande->setTelephone(
                    $address['phone'] ?? $address['phone_mobile'] ?? ''
                );
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

        return ['success' => true, 'newOrders' => $newOrders, 'errors' => $errors,
                'message' => $newOrders . ' nouvelle(s) commande(s) importée(s)'];
    }

    public function syncCustomers(EcommerceConfig $config): array
    {
        $shopUrl      = rtrim($config->getShopUrl(), '/');
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
                $tiers = $this->syncCustomerToTiers($customer, [], 'prestashop');
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

    private function syncCustomerToTiers(array $customer, array $address, string $source): ?Tiers
    {
        if (empty($customer)) return null;

        $email = $customer['email'] ?? null;
        $nom   = trim(($customer['firstname'] ?? '') . ' ' . ($customer['lastname'] ?? ''));
        if (!$nom && $email) $nom = explode('@', $email)[0];
        if (!$nom) return null;

        // Chercher tiers existant par email
        if ($email) {
            $existing = $this->tiersRepo->findOneBy(['email' => $email]);
            if ($existing) return $existing;

            $contacts = $this->contactRepo->findBy(['email' => $email]);
            foreach ($contacts as $contact) {
                $t = $contact->getTiers();
                if ($t && $this->tiersRepo->find($t->getId())) return $t;
                $this->em->remove($contact);
            }
            if (count($contacts) > 0) $this->em->flush();
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
        $tiers->setReferent($this->generateReferent());

        $this->em->persist($tiers);
        $this->em->flush();

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

        return $tiers;
    }

    private function generateReferent(): string
    {
        $conn = $this->em->getConnection();
        $last = $conn->fetchOne("SELECT referent FROM tiers WHERE referent LIKE 'TI%' ORDER BY id DESC LIMIT 1");
        $num  = ($last && preg_match('/^TI(\d+)$/', $last, $m)) ? (int)$m[1] + 1 : 1;
        $ref  = 'TI' . str_pad($num, 5, '0', STR_PAD_LEFT);
        while ($this->tiersRepo->findOneBy(['referent' => $ref]))
            $ref = 'TI' . str_pad(++$num, 5, '0', STR_PAD_LEFT);
        return $ref;
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
    $shopUrl    = rtrim($config->getShopUrl(), '/');
    $apiKey     = $config->getApiKey();
    $newRetours = 0;
    $errors     = [];

    // Récupérer les order_slip (notes de crédit = retours PrestaShop)
    $url    = $shopUrl . '/api/order_slip?ws_key=' . $apiKey
            . '&output_format=JSON&display=full&limit=100';
    $result = $this->curlRequest($url);

    if ($result['httpCode'] !== 200) {
        return [
            'success'    => false,
            'message'    => 'Erreur API PrestaShop order_slip: HTTP ' . $result['httpCode'],
            'newRetours' => 0
        ];
    }

    $data      = json_decode($result['response'], true);
    $slips     = $data['order_slips'] ?? [];

    foreach ($slips as $slip) {
        try {
            $ref      = 'PS-SLIP-' . $slip['id'];
            $existing = $this->em->getRepository(\App\Entity\Retour::class)
                                 ->findOneBy(['ref' => $ref]);
            if ($existing) continue;

            // Récupérer la commande liée
            $orderId    = $slip['id_order'] ?? 0;
            $refCommande = 'PS-' . $orderId;
            $commande   = $this->commandeRepo->findOneBy(['ref' => $refCommande]);

            // Récupérer les infos client via la commande
            $customer = [];
            if ($orderId) {
                $orderUrl    = $shopUrl . '/api/orders/' . $orderId
                             . '?ws_key=' . $apiKey . '&output_format=JSON';
                $orderResult = $this->curlRequest($orderUrl);
                if ($orderResult['httpCode'] === 200) {
                    $orderData   = json_decode($orderResult['response'], true);
                    $customerId  = $orderData['order']['id_customer'] ?? 0;
                    if ($customerId) {
                        $customer = $this->getCustomer($shopUrl, $apiKey, $customerId);
                    }
                }
            }

            $tiers = null;
            if (!empty($customer['email'])) {
                $tiers = $this->tiersRepo->findOneBy(['email' => $customer['email']]);
            }

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

            if ($tiers)    $retour->setTiers($tiers);
            if ($commande) $retour->setCommande($commande);

            $this->em->persist($retour);
            $newRetours++;

        } catch (\Exception $e) {
            $errors[] = 'Slip #' . ($slip['id'] ?? '?') . ': ' . $e->getMessage();
        }
    }

    $this->em->flush();

    return [
        'success'    => true,
        'newRetours' => $newRetours,
        'errors'     => $errors,
        'message'    => $newRetours . ' retour(s) importé(s) depuis PrestaShop'
    ];
}
}