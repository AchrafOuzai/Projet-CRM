<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Repository\CommandeRepository;
use App\Repository\TiersRepository;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityNotFoundException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class CommandeController extends AbstractController
{
    private $em;
    private $repo;
    private $tiersRepo;
    private $emailService;

    public function __construct(
        EntityManagerInterface $em,
        CommandeRepository $repo,
        TiersRepository $tiersRepo,
        EmailService $emailService          // ← injecté automatiquement par Symfony
    ) {
        $this->em           = $em;
        $this->repo         = $repo;
        $this->tiersRepo    = $tiersRepo;
        $this->emailService = $emailService;
    }

    #[Route('/api/commandes', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $tiersId = $request->query->get('tiersId');
        if ($tiersId) {
            $tiers = $this->tiersRepo->find($tiersId);
            $items = $tiers ? $this->repo->findBy(['tiers' => $tiers]) : [];
        } else {
            $items = $this->repo->findAll();
        }
        return $this->json(array_map(fn($c) => $this->serialize($c), $items));
    }

    #[Route('/api/commandes/stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        $all     = $this->repo->findAll();
        $total   = count($all);
        $ca      = array_sum(array_map(fn($c) => $c->getPrixVenteTotal() ?? 0, $all));
        $conf    = count(array_filter($all, fn($c) => $c->getConfirmation() === 'Confirmée'));
        $retours = count(array_filter($all, fn($c) => $c->getLivraison() === 'Retour'));

        return $this->json([
            'total'            => $total,
            'ca'               => $ca,
            'tauxConfirmation' => $total ? round($conf / $total * 100) : 0,
            'tauxRetour'       => $total ? round($retours / $total * 100) : 0,
        ]);
    }

    #[Route('/api/commandes', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data     = json_decode($request->getContent(), true);
        $commande = new Commande();
        $this->hydrate($commande, $data);
        $commande->setCreatedAt(new \DateTimeImmutable());
        $this->em->persist($commande);
        $this->em->flush();
        return $this->json($this->serialize($commande), 201);
    }

    #[Route('/api/commandes/{id}', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $commande = $this->repo->find($id);
        if (!$commande) return $this->json(['error' => 'Not found'], 404);
        $data = json_decode($request->getContent(), true);
        $this->hydrate($commande, $data);
        $this->em->flush();
        return $this->json($this->serialize($commande));
    }

    #[Route('/api/commandes/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $commande = $this->repo->find($id);
        if (!$commande) return $this->json(['error' => 'Not found'], 404);
        $this->em->remove($commande);
        $this->em->flush();
        return $this->json(['success' => true]);
    }

    // ─── NOUVEAU : envoyer email de confirmation à la demande ───────────────
    #[Route('/api/commandes/{id}/send-email', methods: ['POST'])]
    public function sendEmail(int $id): JsonResponse
    {
        $commande = $this->repo->find($id);
        if (!$commande) {
            return $this->json(['error' => 'Commande introuvable'], 404);
        }

        $email = $commande->getEmailClient();
        if (!$email) {
            return $this->json(['error' => 'Cette commande n\'a pas d\'email client'], 400);
        }

        try {
            $this->emailService->sendOrderConfirmation([
                'ref'            => $commande->getRef(),
                'client'         => $commande->getClient(),
                'designation'    => $commande->getDesignation(),
                'prixVenteTotal' => $commande->getPrixVenteTotal(),
                'ville'          => $commande->getVille(),
                'emailClient'    => $email,
            ]);

            return $this->json(['success' => true, 'message' => 'Email envoyé à ' . $email]);

        } catch (\Exception $e) {
            return $this->json(['error' => 'Échec envoi email : ' . $e->getMessage()], 500);
        }
    }
    // ────────────────────────────────────────────────────────────────────────

    private function hydrate(Commande $c, array $data): void
    {
        if (isset($data['date']))             $c->setDate(new \DateTime($data['date']));
        if (isset($data['designation']))      $c->setDesignation($data['designation']);
        if (isset($data['client']))           $c->setClient($data['client']);
        if (isset($data['telephone']))        $c->setTelephone($data['telephone']);
        if (isset($data['adresse']))          $c->setAdresse($data['adresse']);
        if (isset($data['ville']))            $c->setVille($data['ville']);
        if (isset($data['quantite']))         $c->setQuantite($data['quantite']);
        if (isset($data['prixVenteTotal']))   $c->setPrixVenteTotal($data['prixVenteTotal']);
        if (isset($data['typeCde']))          $c->setTypeCde($data['typeCde']);
        if (isset($data['agent']))            $c->setAgent($data['agent']);
        if (isset($data['confirmation']))     $c->setConfirmation($data['confirmation']);
        if (isset($data['livraison']))        $c->setLivraison($data['livraison']);
        if (isset($data['ref']))              $c->setRef($data['ref']);
        if (isset($data['commentaire']))      $c->setCommentaire($data['commentaire']);
        if (isset($data['fraisLivraison']))   $c->setFraisLivraison($data['fraisLivraison']);
        if (isset($data['whatsap']))          $c->setWhatsap($data['whatsap']);
        if (isset($data['source']))           $c->setSource($data['source']);
        if (isset($data['statutEcommerce']))  $c->setStatutEcommerce($data['statutEcommerce']);
        if (isset($data['modePaiement']))     $c->setModePaiement($data['modePaiement']);
        if (isset($data['pays']))             $c->setPays($data['pays']);
        if (isset($data['emailClient']))      $c->setEmailClient($data['emailClient']);

        if (isset($data['tiersId'])) {
            $c->setTiers($data['tiersId'] ? $this->tiersRepo->find($data['tiersId']) : null);
        }
    }

    public function serialize(Commande $c): array
    {
        $date     = $c->getDate();
        $tiersId  = null;
        $tiersNom = null;

        try {
            $tiers = $c->getTiers();
            if ($tiers !== null) {
                $tiersId  = $tiers->getId();
                $tiersNom = $tiers->getNom();
            }
        } catch (EntityNotFoundException $e) {
            $c->setTiers(null);
            $this->em->flush();
        }

        return [
            'id'              => $c->getId(),
            'date'            => $date ? $date->format('Y-m-d') : null,
            'designation'     => $c->getDesignation(),
            'client'          => $c->getClient(),
            'telephone'       => $c->getTelephone(),
            'adresse'         => $c->getAdresse(),
            'ville'           => $c->getVille(),
            'quantite'        => $c->getQuantite(),
            'prixVenteTotal'  => $c->getPrixVenteTotal(),
            'typeCde'         => $c->getTypeCde(),
            'agent'           => $c->getAgent(),
            'confirmation'    => $c->getConfirmation(),
            'livraison'       => $c->getLivraison(),
            'ref'             => $c->getRef(),
            'commentaire'     => $c->getCommentaire(),
            'fraisLivraison'  => $c->getFraisLivraison(),
            'whatsap'         => $c->getWhatsap(),
            'tiersId'         => $tiersId,
            'tiersNom'        => $tiersNom,
            'source'          => $c->getSource() ?? 'manuel',
            'statutEcommerce' => $c->getStatutEcommerce(),
            'modePaiement'    => $c->getModePaiement(),
            'pays'            => $c->getPays(),
            'emailClient'     => $c->getEmailClient(),
        ];
    }
}