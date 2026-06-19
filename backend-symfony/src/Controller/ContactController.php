<?php

namespace App\Controller;

use App\Entity\Contact;
use App\Repository\ContactRepository;
use App\Repository\TiersRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityNotFoundException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ContactController extends AbstractController
{
    private $em;
    private $repo;
    private $tiersRepo;

    public function __construct(
        EntityManagerInterface $em,
        ContactRepository $repo,
        TiersRepository $tiersRepo
    ) {
        $this->em        = $em;
        $this->repo      = $repo;
        $this->tiersRepo = $tiersRepo;
    }

    private function getCurrentTenant()
    {
        $user = $this->getUser();
        if (!$user || !method_exists($user, 'getTenant')) return null;
        return $user->getTenant();
    }

    #[Route('/api/contacts', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $tenant  = $this->getCurrentTenant();
        $tiersId = $request->query->get('tiersId');

        if ($tiersId) {
            // Vérifier que le tiers appartient au tenant courant
            $tiers = $this->tiersRepo->find($tiersId);
            if (!$tiers) return $this->json([]);
            if ($tenant && $tiers->getTenant() && $tiers->getTenant()->getId() !== $tenant->getId()) {
                return $this->json(['error' => 'Accès refusé'], 403);
            }
            $items = $this->repo->findBy(['tiers' => $tiers]);
        } else {
            // Récupérer tous les contacts des tiers du tenant courant
            if ($tenant) {
                $qb = $this->em->createQueryBuilder()
                    ->select('c')
                    ->from(Contact::class, 'c')
                    ->join('c.tiers', 't')
                    ->where('t.tenant = :tenant')
                    ->setParameter('tenant', $tenant)
                    ->orderBy('c.id', 'DESC');
                $items = $qb->getQuery()->getResult();
            } else {
                $items = $this->repo->findAll();
            }
        }

        $result = [];
        foreach ($items as $c) {
            try {
                $result[] = $this->serialize($c);
            } catch (EntityNotFoundException $e) {
                $this->em->remove($c);
            }
        }
        $this->em->flush();
        return $this->json($result);
    }

    #[Route('/api/contacts', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data    = json_decode($request->getContent(), true);
        $contact = new Contact();
        $this->hydrate($contact, $data);
        $this->em->persist($contact);
        $this->em->flush();
        return $this->json($this->serialize($contact), 201);
    }

    #[Route('/api/contacts/{id}', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $contact = $this->repo->find($id);
        if (!$contact) return $this->json(['error' => 'Not found'], 404);
        $data = json_decode($request->getContent(), true);
        $this->hydrate($contact, $data);
        $this->em->flush();
        return $this->json($this->serialize($contact));
    }

    #[Route('/api/contacts/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $contact = $this->repo->find($id);
        if (!$contact) return $this->json(['error' => 'Not found'], 404);
        $this->em->remove($contact);
        $this->em->flush();
        return $this->json(['success' => true]);
    }

    private function hydrate(Contact $c, array $data): void
    {
        if (isset($data['nom']))           $c->setNom($data['nom']);
        if (isset($data['prenom']))        $c->setPrenom($data['prenom']);
        if (isset($data['telephone']))     $c->setTelephone($data['telephone']);
        if (isset($data['telPortable']))   $c->setTelPortable($data['telPortable']);
        if (isset($data['email']))         $c->setEmail($data['email']);
        if (isset($data['nomAlternatif'])) $c->setNomAlternatif($data['nomAlternatif']);
        if (isset($data['visibilite']))    $c->setVisibilite($data['visibilite']);
        if (isset($data['etat']))          $c->setEtat($data['etat']);
        if (isset($data['tiersId'])) {
            $tiers = $this->tiersRepo->find($data['tiersId']);
            if ($tiers) $c->setTiers($tiers);
        }
    }

    private function serialize(Contact $c): array
    {
        $tiersId = $tiersNom = null;
        try {
            $tiers = $c->getTiers();
            if ($tiers) {
                $tiersId  = $tiers->getId();
                $tiersNom = $tiers->getNom();
            }
        } catch (EntityNotFoundException $e) {
            throw $e;
        }
        return [
            'id'            => $c->getId(),
            'nom'           => $c->getNom(),
            'prenom'        => $c->getPrenom(),
            'telephone'     => $c->getTelephone(),
            'telPortable'   => $c->getTelPortable(),
            'email'         => $c->getEmail(),
            'nomAlternatif' => $c->getNomAlternatif(),
            'visibilite'    => $c->getVisibilite(),
            'etat'          => $c->getEtat(),
            'tiersId'       => $tiersId,
            'tiersNom'      => $tiersNom,
        ];
    }
}