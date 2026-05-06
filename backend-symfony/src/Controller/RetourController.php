<?php

namespace App\Controller;

use App\Entity\Retour;
use App\Repository\RetourRepository;
use App\Repository\TiersRepository;
use App\Repository\CommandeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityNotFoundException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class RetourController extends AbstractController
{
    private $em;
    private $repo;
    private $tiersRepo;
    private $commandeRepo;

    public function __construct(
        EntityManagerInterface $em,
        RetourRepository       $repo,
        TiersRepository        $tiersRepo,
        CommandeRepository     $commandeRepo
    ) {
        $this->em           = $em;
        $this->repo         = $repo;
        $this->tiersRepo    = $tiersRepo;
        $this->commandeRepo = $commandeRepo;
    }

    #[Route('/api/retours', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $source = $request->query->get('source');
        $items  = $source
            ? $this->repo->findBy(['source' => $source], ['id' => 'DESC'])
            : $this->repo->findBy([], ['id' => 'DESC']);

        return $this->json(array_map(fn($r) => $this->serialize($r), $items));
    }

    #[Route('/api/retours', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data   = json_decode($request->getContent(), true);
        $retour = new Retour();
        $this->hydrate($retour, $data);
        $retour->setCreatedAt(new \DateTimeImmutable());
        $this->em->persist($retour);
        $this->em->flush();
        return $this->json($this->serialize($retour), 201);
    }

    #[Route('/api/retours/{id}', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $retour = $this->repo->find($id);
        if (!$retour) return $this->json(['error' => 'Not found'], 404);
        $data = json_decode($request->getContent(), true);
        $this->hydrate($retour, $data);
        $this->em->flush();
        return $this->json($this->serialize($retour));
    }

    #[Route('/api/retours/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $retour = $this->repo->find($id);
        if (!$retour) return $this->json(['error' => 'Not found'], 404);
        $this->em->remove($retour);
        $this->em->flush();
        return $this->json(['success' => true]);
    }

    private function hydrate(Retour $r, array $data): void
    {
        if (isset($data['ref']))              $r->setRef($data['ref']);
        if (isset($data['source']))           $r->setSource($data['source']);
        if (isset($data['refCommande']))      $r->setRefCommande($data['refCommande']);
        if (isset($data['date']))             $r->setDate(new \DateTime($data['date']));
        if (isset($data['client']))           $r->setClient($data['client']);
        if (isset($data['emailClient']))      $r->setEmailClient($data['emailClient']);
        if (isset($data['telephone']))        $r->setTelephone($data['telephone']);
        if (isset($data['montantRembourse'])) $r->setMontantRembourse($data['montantRembourse']);
        if (isset($data['motif']))            $r->setMotif($data['motif']);
        if (isset($data['statut']))           $r->setStatut($data['statut']);
        if (isset($data['commentaire']))      $r->setCommentaire($data['commentaire']);
        if (isset($data['ville']))            $r->setVille($data['ville']);
        if (isset($data['pays']))             $r->setPays($data['pays']);

        if (isset($data['tiersId'])) {
            $r->setTiers($data['tiersId'] ? $this->tiersRepo->find($data['tiersId']) : null);
        }
        if (isset($data['commandeId'])) {
            $r->setCommande($data['commandeId'] ? $this->commandeRepo->find($data['commandeId']) : null);
        }
    }

    private function serialize(Retour $r): array
    {
        $tiersId  = null;
        $tiersNom = null;
        try {
            if ($r->getTiers()) {
                $tiersId  = $r->getTiers()->getId();
                $tiersNom = $r->getTiers()->getNom();
            }
        } catch (EntityNotFoundException $e) {
            $r->setTiers(null);
            $this->em->flush();
        }

        $commandeId  = null;
        $commandeRef = null;
        try {
            if ($r->getCommande()) {
                $commandeId  = $r->getCommande()->getId();
                $commandeRef = $r->getCommande()->getRef();
            }
        } catch (EntityNotFoundException $e) {
            $r->setCommande(null);
            $this->em->flush();
        }

        return [
            'id'               => $r->getId(),
            'ref'              => $r->getRef(),
            'source'           => $r->getSource() ?? 'manuel',
            'refCommande'      => $r->getRefCommande(),
            'date'             => $r->getDate() ? $r->getDate()->format('Y-m-d') : null,
            'client'           => $r->getClient(),
            'emailClient'      => $r->getEmailClient(),
            'telephone'        => $r->getTelephone(),
            'montantRembourse' => $r->getMontantRembourse(),
            'motif'            => $r->getMotif(),
            'statut'           => $r->getStatut() ?? 'En attente',
            'commentaire'      => $r->getCommentaire(),
            'ville'            => $r->getVille(),
            'pays'             => $r->getPays(),
            'tiersId'          => $tiersId,
            'tiersNom'         => $tiersNom,
            'commandeId'       => $commandeId,
            'commandeRef'      => $commandeRef,
        ];
    }
}