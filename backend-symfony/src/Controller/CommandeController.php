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
        EmailService $emailService
    ) {
        $this->em           = $em;
        $this->repo         = $repo;
        $this->tiersRepo    = $tiersRepo;
        $this->emailService = $emailService;
    }

    // ── Helper : tenant courant ──────────────────────────
    private function getCurrentTenant()
    {
        $user = $this->getUser();
        if (!$user || !method_exists($user, 'getTenant')) return null;
        return $user->getTenant();
    }

    #[Route('/api/commandes', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $tenant  = $this->getCurrentTenant();
        $tiersId = $request->query->get('tiersId');

        $qb = $this->em->createQueryBuilder()
            ->select('c')
            ->from(Commande::class, 'c')
            ->orderBy('c.id', 'DESC');

        // ── Filtrage par tenant ──────────────────────────
        if ($tenant) {
            $qb->andWhere('c.tenant = :tenant')
               ->setParameter('tenant', $tenant);
        }

        if ($tiersId) {
            $tiers = $this->tiersRepo->find($tiersId);
            if ($tiers) {
                $qb->andWhere('c.tiers = :tiers')
                   ->setParameter('tiers', $tiers);
            }
        }

        // Filtres optionnels
        $filters = ['source', 'confirmation', 'livraison', 'agent'];
        foreach ($filters as $f) {
            $val = $request->query->get($f);
            if ($val) {
                $qb->andWhere("c.$f = :$f")->setParameter($f, $val);
            }
        }

        $search = $request->query->get('search');
        if ($search) {
            $qb->andWhere(
                'c.client LIKE :s OR c.designation LIKE :s OR c.telephone LIKE :s
                 OR c.ref LIKE :s OR c.ville LIKE :s OR c.emailClient LIKE :s'
            )->setParameter('s', '%' . $search . '%');
        }

        $items = $qb->getQuery()->getResult();
        return $this->json(array_map(fn($c) => $this->serialize($c), $items));
    }

    #[Route('/api/commandes/stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        $tenant = $this->getCurrentTenant();

        $qb = $this->em->createQueryBuilder()
            ->select('c')
            ->from(Commande::class, 'c');

        if ($tenant) {
            $qb->where('c.tenant = :tenant')
               ->setParameter('tenant', $tenant);
        }

        $all     = $qb->getQuery()->getResult();
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
        $tenant   = $this->getCurrentTenant();
        $data     = json_decode($request->getContent(), true);
        $commande = new Commande();
        $this->hydrate($commande, $data);
        $commande->setCreatedAt(new \DateTimeImmutable());
        $commande->setTenant($tenant); // ── Assigner le tenant
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

    #[Route('/api/commandes/{id}/send-email', methods: ['POST'])]
    public function sendEmail(int $id): JsonResponse
    {
        $commande = $this->repo->find($id);
        if (!$commande) return $this->json(['error' => 'Commande introuvable'], 404);

        $email = $commande->getEmailClient();
        if (!$email) return $this->json(['error' => 'Pas d\'email client'], 400);

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
            return $this->json(['error' => 'Échec envoi : ' . $e->getMessage()], 500);
        }
    }

    private function hydrate(Commande $c, array $data): void
    {
        if (isset($data['date']))            $c->setDate(new \DateTime($data['date']));
        if (isset($data['designation']))     $c->setDesignation($data['designation']);
        if (isset($data['client']))          $c->setClient($data['client']);
        if (isset($data['telephone']))       $c->setTelephone($data['telephone']);
        if (isset($data['adresse']))         $c->setAdresse($data['adresse']);
        if (isset($data['ville']))           $c->setVille($data['ville']);
        if (isset($data['quantite']))        $c->setQuantite($data['quantite']);
        if (isset($data['prixVenteTotal']))  $c->setPrixVenteTotal($data['prixVenteTotal']);
        if (isset($data['typeCde']))         $c->setTypeCde($data['typeCde']);
        if (isset($data['agent']))           $c->setAgent($data['agent']);
        if (isset($data['confirmation']))    $c->setConfirmation($data['confirmation']);
        if (isset($data['livraison']))       $c->setLivraison($data['livraison']);
        if (isset($data['ref']))             $c->setRef($data['ref']);
        if (isset($data['commentaire']))     $c->setCommentaire($data['commentaire']);
        if (isset($data['fraisLivraison']))  $c->setFraisLivraison($data['fraisLivraison']);
        if (isset($data['whatsap']))         $c->setWhatsap($data['whatsap']);
        if (isset($data['source']))          $c->setSource($data['source']);
        if (isset($data['statutEcommerce'])) $c->setStatutEcommerce($data['statutEcommerce']);
        if (isset($data['modePaiement']))    $c->setModePaiement($data['modePaiement']);
        if (isset($data['pays']))            $c->setPays($data['pays']);
        if (isset($data['emailClient']))     $c->setEmailClient($data['emailClient']);
        if (isset($data['tiersId'])) {
            $c->setTiers($data['tiersId'] ? $this->tiersRepo->find($data['tiersId']) : null);
        }
    }

    public function serialize(Commande $c): array
    {
        $tiersId = $tiersNom = null;
        try {
            $tiers = $c->getTiers();
            if ($tiers) {
                $tiersId  = $tiers->getId();
                $tiersNom = $tiers->getNom();
            }
        } catch (EntityNotFoundException $e) {
            $c->setTiers(null);
            $this->em->flush();
        }

        return [
            'id'              => $c->getId(),
            'date'            => $c->getDate() ? $c->getDate()->format('Y-m-d') : null,
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