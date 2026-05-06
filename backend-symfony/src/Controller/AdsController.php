<?php

namespace App\Controller;

use App\Entity\Ads;
use App\Repository\AdsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/ads')]
class AdsController extends AbstractController
{
    private EntityManagerInterface $em;
    private AdsRepository $repo;

    public function __construct(EntityManagerInterface $em, AdsRepository $repo)
    {
        $this->em = $em;
        $this->repo = $repo;
    }

    #[Route('', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return $this->json(array_map([$this, 'serialize'], $this->repo->findAll()));
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $ads = new Ads();
        $this->hydrate($ads, $data);
        $this->em->persist($ads);
        $this->em->flush();
        return $this->json($this->serialize($ads), 201);
    }

    #[Route('/{id}', methods: ['PUT', 'PATCH'])]
    public function update(Ads $ads, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $this->hydrate($ads, $data);
        $this->em->flush();
        return $this->json($this->serialize($ads));
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(Ads $ads): JsonResponse
    {
        $this->em->remove($ads);
        $this->em->flush();
        return $this->json(['success' => true]);
    }

    private function hydrate(Ads $a, array $data): void
    {
        if (isset($data['numeroCampagne']))    $a->setNumeroCampagne($data['numeroCampagne']);
        if (isset($data['date']))              $a->setDate(new \DateTime($data['date']));
        if (isset($data['produit']))           $a->setProduit($data['produit']);
        if (isset($data['objetPublicitaire'])) $a->setObjetPublicitaire($data['objetPublicitaire']);
        if (isset($data['admin']))             $a->setAdmin($data['admin']);
        if (isset($data['dureeJours']))        $a->setDureeJours((int)$data['dureeJours']);
        if (isset($data['montantDollars']))    $a->setMontantDollars((string)$data['montantDollars']);
        if (isset($data['montantDH']))         $a->setMontantDH((string)$data['montantDH']);
        if (isset($data['resultat']))          $a->setResultat($data['resultat']);
        if (isset($data['evaluation']))        $a->setEvaluation($data['evaluation']);
        if (isset($data['totalDollars']))      $a->setTotalDollars((string)$data['totalDollars']);
        if (isset($data['totalDH']))           $a->setTotalDH((string)$data['totalDH']);
        if (isset($data['prospect']))          $a->setProspect((int)$data['prospect']);
    }

    private function serialize(Ads $a): array
    {
        return [
            'id'                => $a->getId(),
            'numeroCampagne'    => $a->getNumeroCampagne(),
            'date'              => $a->getDate() ? $a->getDate()->format('Y-m-d') : null,
            'produit'           => $a->getProduit(),
            'objetPublicitaire' => $a->getObjetPublicitaire(),
            'admin'             => $a->getAdmin(),
            'dureeJours'        => $a->getDureeJours(),
            'montantDollars'    => (float) $a->getMontantDollars(),
            'montantDH'         => (float) $a->getMontantDH(),
            'resultat'          => $a->getResultat(),
            'evaluation'        => $a->getEvaluation(),
            'totalDollars'      => (float) $a->getTotalDollars(),
            'totalDH'           => (float) $a->getTotalDH(),
            'prospect'          => $a->getProspect(),
        ];
    }
}