<?php

namespace App\Controller;

use App\Entity\Livraison;
use App\Repository\LivraisonRepository;
use App\Repository\CommandeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityNotFoundException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class LivraisonController extends AbstractController
{
    private $em;
    private $repo;
    private $commandeRepo;

    public function __construct(
        EntityManagerInterface $em,
        LivraisonRepository $repo,
        CommandeRepository $commandeRepo
    ) {
        $this->em           = $em;
        $this->repo         = $repo;
        $this->commandeRepo = $commandeRepo;
    }

    private function getCurrentTenant()
    {
        $user = $this->getUser();
        if (!$user || !method_exists($user, 'getTenant')) return null;
        return $user->getTenant();
    }

    #[Route('/api/livraisons', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $tenant = $this->getCurrentTenant();
        $page   = max(1, (int)$request->query->get('page', 1));
        $limit  = 10;
        $offset = ($page - 1) * $limit;
        $search = $request->query->get('search', '');
        $statut = $request->query->get('statut', '');

        $qb = $this->em->createQueryBuilder()
            ->select('l')
            ->from(Livraison::class, 'l')
            ->leftJoin('l.commande', 'c')
            ->orderBy('l.id', 'DESC');

        // ── Filtrage par tenant via la commande associée ──
        if ($tenant) {
            $qb->andWhere('c.tenant = :tenant')
               ->setParameter('tenant', $tenant);
        }

        if ($search) {
            $qb->andWhere(
                'l.numeroSuivi LIKE :s OR l.transporteur LIKE :s
                 OR c.ref LIKE :s OR c.client LIKE :s'
            )->setParameter('s', '%' . $search . '%');
        }

        if ($statut) {
            $qb->andWhere('l.statut = :statut')
               ->setParameter('statut', $statut);
        }

        $total = count($qb->getQuery()->getResult());

        $items = $qb->setFirstResult($offset)
                    ->setMaxResults($limit)
                    ->getQuery()
                    ->getResult();

        $result = [];
        foreach ($items as $l) {
            try {
                $result[] = $this->serialize($l);
            } catch (EntityNotFoundException $e) {
                $l->setCommande(null);
                $this->em->flush();
                $result[] = $this->serialize($l);
            }
        }

        return $this->json([
            'data'  => $result,
            'total' => $total,
            'page'  => $page,
            'pages' => ceil($total / $limit),
            'limit' => $limit,
        ]);
    }

    #[Route('/api/livraisons', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data      = json_decode($request->getContent(), true);
        $livraison = new Livraison();
        $this->hydrate($livraison, $data);
        $this->em->persist($livraison);
        $this->em->flush();
        return $this->json($this->serialize($livraison), 201);
    }

    #[Route('/api/livraisons/{id}', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $livraison = $this->repo->find($id);
        if (!$livraison) return $this->json(['error' => 'Not found'], 404);
        $data = json_decode($request->getContent(), true);
        $this->hydrate($livraison, $data);
        $this->em->flush();
        return $this->json($this->serialize($livraison));
    }

    #[Route('/api/livraisons/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $livraison = $this->repo->find($id);
        if (!$livraison) return $this->json(['error' => 'Not found'], 404);
        $this->em->remove($livraison);
        $this->em->flush();
        return $this->json(['success' => true]);
    }

    private function hydrate(Livraison $l, array $data): void
    {
        if (isset($data['numeroSuivi']))    $l->setNumeroSuivi($data['numeroSuivi']);
        if (isset($data['transporteur']))   $l->setTransporteur($data['transporteur']);
        if (isset($data['statut']))         $l->setStatut($data['statut']);
        if (isset($data['commentaire']))    $l->setCommentaire($data['commentaire']);
        if (isset($data['dateExpedition'])) {
            $l->setDateExpedition(
                $data['dateExpedition'] ? new \DateTime($data['dateExpedition']) : null
            );
        }
        if (isset($data['commandeId'])) {
            $commande = $data['commandeId']
                ? $this->commandeRepo->find($data['commandeId'])
                : null;
            $l->setCommande($commande);
        }
    }

    private function serialize(Livraison $l): array
    {
        $commandeId = $commandeRef = $commandeClient = null;
        try {
            $commande = $l->getCommande();
            if ($commande) {
                $commandeId     = $commande->getId();
                $commandeRef    = $commande->getRef();
                $commandeClient = $commande->getClient();
            }
        } catch (EntityNotFoundException $e) {
            $l->setCommande(null);
        }
        $date = $l->getDateExpedition();
        return [
            'id'             => $l->getId(),
            'numeroSuivi'    => $l->getNumeroSuivi(),
            'transporteur'   => $l->getTransporteur(),
            'dateExpedition' => $date ? $date->format('Y-m-d') : null,
            'statut'         => $l->getStatut(),
            'commentaire'    => $l->getCommentaire(),
            'commandeId'     => $commandeId,
            'commandeRef'    => $commandeRef,
            'commandeClient' => $commandeClient,
        ];
    }
}