<?php

namespace App\Controller;

use App\Entity\Depense;
use App\Repository\DepenseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/depenses')]
class DepenseController extends AbstractController
{
    private EntityManagerInterface $em;
    private DepenseRepository $repo;

    public function __construct(EntityManagerInterface $em, DepenseRepository $repo)
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
        $depense = new Depense();
        $this->hydrate($depense, $data);
        $this->em->persist($depense);
        $this->em->flush();
        return $this->json($this->serialize($depense), 201);
    }

    #[Route('/{id}', methods: ['PUT', 'PATCH'])]
    public function update(Depense $depense, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $this->hydrate($depense, $data);
        $this->em->flush();
        return $this->json($this->serialize($depense));
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(Depense $depense): JsonResponse
    {
        $this->em->remove($depense);
        $this->em->flush();
        return $this->json(['success' => true]);
    }

    private function hydrate(Depense $d, array $data): void
    {
        if (isset($data['date']))        $d->setDate(new \DateTime($data['date']));
        if (isset($data['designation'])) $d->setDesignation($data['designation']);
        if (isset($data['montant']))     $d->setMontant((string)$data['montant']);
        if (isset($data['categorie']))   $d->setCategorie($data['categorie'] ?? null);
        if (isset($data['commentaire'])) $d->setCommentaire($data['commentaire'] ?? null);
    }

    private function serialize(Depense $d): array
    {
        return [
            'id'          => $d->getId(),
            'date'        => $d->getDate() ? $d->getDate()->format('Y-m-d') : null,
            'designation' => $d->getDesignation(),
            'montant'     => (float) $d->getMontant(),
            'categorie'   => $d->getCategorie(),
            'commentaire' => $d->getCommentaire(),
        ];
    }
}