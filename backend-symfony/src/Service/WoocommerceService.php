<?php

namespace App\Service;

use App\Entity\Commande;
use App\Entity\EcommerceConfig;
use App\Entity\Tiers;
use App\Entity\Contact;
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

    public function __construct(
        EntityManagerInterface $em,
        TiersRepository $tiersRepo,
        ContactRepository $contactRepo,
        CommandeRepository $commandeRepo
    ) {
        $this->em           = $em;
        $this->tiersRepo    = $tiersRepo;
        $this->contactRepo  = $contactRepo;
        $this->commandeRepo = $commandeRepo;
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
        $keys      = $this->parseApiKey($config->getApiKey());
        $shopUrl   = rtrim($config->getShopUrl(), '/');
        $lastId    = $config->getLastOrderId() ?? 0;
        $newOrders = 0;
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
                $existing = $this->commandeRepo->findOneBy(['ref' => $ref]);
                if ($existing) {
                    if ((int)$order['id'] > $lastId) $lastId = (int)$order['id'];
                    continue;
                }

                $billing  = $order['billing']  ?? [];
                $shipping = $order['shipping'] ?? [];

                if (!empty($order['customer_id'])) {
                    $cd = $this->getCustomer(
                        $shopUrl, $keys['username'], $keys['password'],
                        (int)$order['customer_id']
                    );
                    if (!empty($cd)) {
                        if (empty($billing['first_name'])) $billing['first_name'] = $cd['first_name'] ?? '';
                        if (empty($billing['last_name']))  $billing['last_name']  = $cd['last_name']  ?? '';
                        if (empty($billing['email']))      $billing['email']      = $cd['email']      ?? '';
                        if (empty($billing['first_name']) && empty($billing['last_name']))
                            $billing['first_name'] = $cd['username'] ?? '';
                    }
                }

                $tiers   = $this->syncCustomerToTiers($billing, $shipping, 'woocommerce');
                $nom     = trim(($billing['first_name'] ?? '') . ' ' . ($billing['last_name'] ?? ''));
                $statut  = $this->mapStatut($order['status'] ?? '');
                $origine = $order['created_via'] ?? 'WooCommerce';
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
                $commande->setStatutEcommerce($statut);
                $commande->setModePaiement($order['payment_method_title'] ?? $order['payment_method'] ?? '');
                $commande->setConfirmation('Confirmée');
                $commande->setLivraison('En attente');
                $commande->setFraisLivraison((float)($order['shipping_total'] ?? 0));
                $commande->setCreatedAt(new \DateTimeImmutable());
                $commande->setCommentaire(
                    'Importé depuis WooCommerce — Origine: ' . $origine .
                    (!empty($order['customer_note']) ? ' — Note: ' . $order['customer_note'] : '')
                );
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

                // Fallbacks depuis les données principales du customer
                if (empty($billing['first_name'])) $billing['first_name'] = $customer['first_name'] ?? '';
                if (empty($billing['last_name']))  $billing['last_name']  = $customer['last_name']  ?? '';
                // FIX: l'email vient toujours du customer, pas du billing
                $billing['email'] = $customer['email'] ?? $billing['email'] ?? null;

                if (empty($billing['first_name']) && empty($billing['last_name']))
                    $billing['first_name'] = $customer['username'] ?? '';
                if (empty($billing['first_name']) && empty($billing['last_name']) && !empty($billing['email']))
                    $billing['first_name'] = explode('@', $billing['email'])[0];

                $tiers = $this->syncCustomerToTiers($billing, [], 'woocommerce');
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

    private function syncCustomerToTiers(array $billing, array $shipping, string $source): ?Tiers
    {
        $nom   = trim(($billing['first_name'] ?? '') . ' ' . ($billing['last_name'] ?? ''));
        $email = $billing['email'] ?? null;

        if (!$nom && $email) $nom = explode('@', $email)[0];
        if (!$nom) return null;

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

        $ville   = $billing['city']      ?? $shipping['city']      ?? null;
        $adresse = trim(($billing['address_1'] ?? '') . ' ' . ($billing['address_2'] ?? '')) ?: null;
        $cp      = $billing['postcode']  ?? $shipping['postcode']  ?? null;
        $pays    = $billing['country']   ?? $shipping['country']   ?? null;

        $tiers = new Tiers();
        $tiers->setNom($nom);
        $tiers->setEmail($email);
        $tiers->setTelephone($billing['phone'] ?? null);
        $tiers->setAdresse($adresse);
        $tiers->setVille($ville);
        $tiers->setCodePostal($cp);
        $tiers->setPays($pays);
        $tiers->setTypeTiers(['Client']);
        $tiers->setNatureTiers('Particulier');
        $tiers->setEtat('Actif');
        $tiers->setSource($source);
        $tiers->setReferent($this->generateReferent());

        $this->em->persist($tiers);
        $this->em->flush();

        $contact = new Contact();
        $contact->setNom($billing['last_name'] ?? $nom);
        $contact->setPrenom($billing['first_name'] ?? null);
        $contact->setEmail($email);
        $contact->setTelephone($billing['phone'] ?? null);
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

    private function getCustomer(string $shopUrl, string $u, string $p, int $id): array
    {
        if (!$id) return [];
        $result = $this->curlRequest(rtrim($shopUrl, '/') . '/wp-json/wc/v3/customers/' . $id, $u, $p);
        if ($result['httpCode'] !== 200) return [];
        return json_decode($result['response'], true) ?? [];
    }

    public function syncRetours(EcommerceConfig $config): array
    {
        $keys       = $this->parseApiKey($config->getApiKey());
        $shopUrl    = rtrim($config->getShopUrl(), '/');
        $newRetours = 0;
        $errors     = [];

        // FIX: récupérer AUSSI les commandes annulées, pas seulement refunded
        $statuses = ['refunded', 'cancelled'];

        foreach ($statuses as $status) {
            $url    = $shopUrl . '/wp-json/wc/v3/orders?per_page=50&status=' . $status;
            $result = $this->curlRequest($url, $keys['username'], $keys['password']);

            if ($result['httpCode'] !== 200) continue;

            $orders = json_decode($result['response'], true) ?? [];

            foreach ($orders as $order) {
                $billing = $order['billing'] ?? [];
                $email   = $billing['email'] ?? null;

                // Pour les commandes annulées sans remboursement
                // on crée directement un retour
                if ($status === 'cancelled') {
                    try {
                        $ref      = 'WC-CANCEL-' . $order['id'];
                        $existing = $this->em->getRepository(\App\Entity\Retour::class)
                                             ->findOneBy(['ref' => $ref]);
                        if ($existing) continue;

                        $refCommande = 'WC-' . $order['id'];
                        $commande    = $this->commandeRepo->findOneBy(['ref' => $refCommande]);
                        $tiers       = null;
                        if ($email) $tiers = $this->tiersRepo->findOneBy(['email' => $email]);

                        $nom = trim(($billing['first_name'] ?? '') . ' ' . ($billing['last_name'] ?? ''));

                        $retour = new \App\Entity\Retour();
                        $retour->setRef($ref);
                        $retour->setSource('woocommerce');
                        $retour->setRefCommande($refCommande);
                        $retour->setDate(new \DateTime($order['date_modified'] ?? 'now'));
                        $retour->setClient($nom ?: ($email ?? 'Client WooCommerce'));
                        $retour->setEmailClient($email);
                        $retour->setTelephone($billing['phone'] ?? null);
                        $retour->setMontantRembourse((float)($order['total'] ?? 0));
                        $retour->setMotif('Commande annulée');
                        $retour->setStatut('Annulée');
                        $retour->setVille($billing['city'] ?? null);
                        $retour->setPays($billing['country'] ?? null);
                        $retour->setCommentaire('Commande WooCommerce annulée #' . $order['id']);
                        $retour->setCreatedAt(new \DateTimeImmutable());

                        if ($tiers)    $retour->setTiers($tiers);
                        if ($commande) $retour->setCommande($commande);

                        $this->em->persist($retour);
                        $newRetours++;

                    } catch (\Exception $e) {
                        $errors[] = 'Annulation #' . ($order['id'] ?? '?') . ': ' . $e->getMessage();
                    }
                    continue;
                }

                // Pour les commandes remboursées — récupérer les refunds détaillés
                $refundsUrl    = $shopUrl . '/wp-json/wc/v3/orders/' . $order['id'] . '/refunds';
                $refundsResult = $this->curlRequest($refundsUrl, $keys['username'], $keys['password']);

                if ($refundsResult['httpCode'] !== 200) continue;

                $refunds = json_decode($refundsResult['response'], true) ?? [];

                // Si pas de refunds détaillés mais statut refunded
                // créer un retour global basé sur la commande
                if (empty($refunds)) {
                    try {
                        $ref      = 'WC-REFUND-' . $order['id'];
                        $existing = $this->em->getRepository(\App\Entity\Retour::class)
                                             ->findOneBy(['ref' => $ref]);
                        if ($existing) continue;

                        $refCommande = 'WC-' . $order['id'];
                        $commande    = $this->commandeRepo->findOneBy(['ref' => $refCommande]);
                        $tiers       = null;
                        if ($email) $tiers = $this->tiersRepo->findOneBy(['email' => $email]);

                        $nom = trim(($billing['first_name'] ?? '') . ' ' . ($billing['last_name'] ?? ''));

                        $retour = new \App\Entity\Retour();
                        $retour->setRef($ref);
                        $retour->setSource('woocommerce');
                        $retour->setRefCommande($refCommande);
                        $retour->setDate(new \DateTime($order['date_modified'] ?? 'now'));
                        $retour->setClient($nom ?: ($email ?? 'Client WooCommerce'));
                        $retour->setEmailClient($email);
                        $retour->setTelephone($billing['phone'] ?? null);
                        // FIX: montant depuis le total de la commande si refund amount vide
                        $retour->setMontantRembourse(abs((float)($order['total'] ?? 0)));
                        $retour->setMotif('Remboursement WooCommerce');
                        $retour->setStatut('Remboursé');
                        $retour->setVille($billing['city'] ?? null);
                        $retour->setPays($billing['country'] ?? null);
                        $retour->setCommentaire('Commande WooCommerce remboursée #' . $order['id']);
                        $retour->setCreatedAt(new \DateTimeImmutable());

                        if ($tiers)    $retour->setTiers($tiers);
                        if ($commande) $retour->setCommande($commande);

                        $this->em->persist($retour);
                        $newRetours++;

                    } catch (\Exception $e) {
                        $errors[] = 'Remboursement #' . ($order['id'] ?? '?') . ': ' . $e->getMessage();
                    }
                    continue;
                }

                foreach ($refunds as $refund) {
                    try {
                        $ref      = 'WC-REFUND-' . $refund['id'];
                        $existing = $this->em->getRepository(\App\Entity\Retour::class)
                                             ->findOneBy(['ref' => $ref]);
                        if ($existing) continue;

                        $refCommande = 'WC-' . $order['id'];
                        $commande    = $this->commandeRepo->findOneBy(['ref' => $refCommande]);
                        $tiers       = null;
                        if ($email) $tiers = $this->tiersRepo->findOneBy(['email' => $email]);

                        $nom = trim(($billing['first_name'] ?? '') . ' ' . ($billing['last_name'] ?? ''));

                        // FIX: montant peut être une string vide ou négative
                        $montant = abs((float)($refund['amount'] ?? 0));
                        if ($montant === 0.0) {
                            $montant = abs((float)($order['total'] ?? 0));
                        }

                        $retour = new \App\Entity\Retour();
                        $retour->setRef($ref);
                        $retour->setSource('woocommerce');
                        $retour->setRefCommande($refCommande);
                        $retour->setDate(new \DateTime($refund['date_created'] ?? 'now'));
                        $retour->setClient($nom ?: ($email ?? 'Client WooCommerce'));
                        $retour->setEmailClient($email);
                        $retour->setTelephone($billing['phone'] ?? null);
                        $retour->setMontantRembourse($montant);
                        $retour->setMotif(
                            !empty($refund['reason']) ? $refund['reason'] : 'Remboursement WooCommerce'
                        );
                        $retour->setStatut('Remboursé');
                        $retour->setVille($billing['city'] ?? null);
                        $retour->setPays($billing['country'] ?? null);
                        $retour->setCommentaire('Importé depuis WooCommerce — Refund #' . $refund['id']);
                        $retour->setCreatedAt(new \DateTimeImmutable());

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

        return [
            'success'    => true,
            'newRetours' => $newRetours,
            'errors'     => $errors,
            'message'    => $newRetours . ' retour(s) importé(s) depuis WooCommerce'
        ];
    }
}