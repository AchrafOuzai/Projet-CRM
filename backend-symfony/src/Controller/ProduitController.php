<?php

namespace App\Controller;

use App\Entity\Produit;
use App\Repository\ProduitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ProduitController extends AbstractController
{
    private $em;
    private $repo;

    public function __construct(EntityManagerInterface $em, ProduitRepository $repo)
    {
        $this->em   = $em;
        $this->repo = $repo;
    }

    #[Route('/api/produits', name: 'api_produits_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $produits = $this->repo->findAll();
        $data = [];
        foreach ($produits as $p) {
            $data[] = $this->serialize($p);
        }
        return $this->json($data);
    }

    #[Route('/api/produits', name: 'api_produits_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $p = new Produit();
        $this->hydrate($p, $data);
        $this->em->persist($p);
        $this->em->flush();
        return $this->json($this->serialize($p), 201);
    }

    #[Route('/api/produits/{id}', name: 'api_produits_update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $p = $this->repo->find($id);
        if (!$p) return $this->json(['error' => 'Non trouvé'], 404);
        $data = json_decode($request->getContent(), true);
        $this->hydrate($p, $data);
        $this->em->flush();
        return $this->json($this->serialize($p));
    }

    #[Route('/api/produits/{id}', name: 'api_produits_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $p = $this->repo->find($id);
        if (!$p) return $this->json(['error' => 'Non trouvé'], 404);
        $this->em->remove($p);
        $this->em->flush();
        return $this->json(['success' => true]);
    }

    private function hydrate(Produit $p, array $data): void
    {
        if (isset($data['reference']))   $p->setReference($data['reference']);
        if (isset($data['designation'])) $p->setDesignation($data['designation']);
        if (isset($data['categorie']))   $p->setCategorie($data['categorie']);
        if (isset($data['prixAchat']))   $p->setPrixAchat((string)$data['prixAchat']);
        if (isset($data['prixVente']))   $p->setPrixVente((string)$data['prixVente']);
        if (isset($data['prixVente2']))  $p->setPrixVente2((string)$data['prixVente2']);
        if (isset($data['prixVente3']))  $p->setPrixVente3((string)$data['prixVente3']);
        if (isset($data['prixVente4']))  $p->setPrixVente4((string)$data['prixVente4']);
        if (isset($data['prixVente5']))  $p->setPrixVente5((string)$data['prixVente5']);
        if (isset($data['stock']))       $p->setStock((int)$data['stock']);
    }

    private function serialize(Produit $p): array
    {
        return [
            'id'          => $p->getId(),
            'reference'   => $p->getReference(),
            'designation' => $p->getDesignation(),
            'categorie'   => $p->getCategorie(),
            'prixAchat'   => (float)$p->getPrixAchat(),
            'prixVente'   => (float)$p->getPrixVente(),
            'prixVente2'  => (float)$p->getPrixVente2(),
            'prixVente3'  => (float)$p->getPrixVente3(),
            'prixVente4'  => (float)$p->getPrixVente4(),
            'prixVente5'  => (float)$p->getPrixVente5(),
            'stock'       => $p->getStock(),
        ];
    }
}