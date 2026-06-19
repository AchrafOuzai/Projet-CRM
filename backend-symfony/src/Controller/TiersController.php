<?php

namespace App\Controller;

use App\Entity\Tiers;
use App\Repository\TiersRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class TiersController extends AbstractController
{
    private $em;
    private $repo;

    public function __construct(EntityManagerInterface $em, TiersRepository $repo)
    {
        $this->em   = $em;
        $this->repo = $repo;
    }

    private function getCurrentTenant()
    {
        $user = $this->getUser();
        if (!$user || !method_exists($user, 'getTenant')) return null;
        return $user->getTenant();
    }

    #[Route('/api/tiers', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $tenant = $this->getCurrentTenant();

        $criteria = [];
        if ($tenant) $criteria['tenant'] = $tenant;

        $source = $request->query->get('source');
        if ($source) $criteria['source'] = $source;

        $items = $this->repo->findBy($criteria, ['id' => 'DESC']);
        return $this->json(array_map(fn($t) => $this->serialize($t), $items));
    }

    #[Route('/api/tiers', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $tenant = $this->getCurrentTenant();
        $data   = json_decode($request->getContent(), true);
        $tiers  = new Tiers();
        $this->hydrate($tiers, $data);
        $tiers->setReferent($this->generateReferent());
        $tiers->setTenant($tenant); // ── Assigner le tenant
        $this->em->persist($tiers);
        $this->em->flush();
        return $this->json($this->serialize($tiers), 201);
    }

    #[Route('/api/tiers/{id}', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $tiers = $this->repo->find($id);
        if (!$tiers) return $this->json(['error' => 'Not found'], 404);
        $data = json_decode($request->getContent(), true);
        $this->hydrate($tiers, $data);
        $this->em->flush();
        return $this->json($this->serialize($tiers));
    }

    #[Route('/api/tiers/{id}', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $tiers = $this->repo->find($id);
        if (!$tiers) return $this->json(['error' => 'Not found'], 404);
        $this->em->remove($tiers);
        $this->em->flush();
        return $this->json(['success' => true]);
    }

    private function generateReferent(): string
    {
        $conn = $this->em->getConnection();
        $last = $conn->fetchOne(
            "SELECT referent FROM tiers WHERE referent LIKE 'TI%' ORDER BY id DESC LIMIT 1"
        );
        $num = ($last && preg_match('/^TI(\d+)$/', $last, $m)) ? (int)$m[1] + 1 : 1;
        $ref = 'TI' . str_pad($num, 5, '0', STR_PAD_LEFT);
        while ($this->repo->findOneBy(['referent' => $ref]))
            $ref = 'TI' . str_pad(++$num, 5, '0', STR_PAD_LEFT);
        return $ref;
    }

    private function hydrate(Tiers $t, array $data): void
    {
        if (isset($data['nom']))                   $t->setNom($data['nom']);
        if (isset($data['nomAlternatif']))         $t->setNomAlternatif($data['nomAlternatif']);
        if (isset($data['codeBarres']))            $t->setCodeBarres($data['codeBarres']);
        if (isset($data['codeClient']))            $t->setCodeClient($data['codeClient']);
        if (isset($data['compteComptableClient'])) $t->setCompteComptableClient($data['compteComptableClient']);
        if (isset($data['commerciaux']))           $t->setCommerciaux($data['commerciaux']);
        if (isset($data['codePostal']))            $t->setCodePostal($data['codePostal']);
        if (isset($data['typeTiers']))             $t->setTypeTiers($data['typeTiers']);
        if (isset($data['telephone']))             $t->setTelephone($data['telephone']);
        if (isset($data['natureTiers']))           $t->setNatureTiers($data['natureTiers']);
        if (isset($data['etat']))                  $t->setEtat($data['etat']);
        if (isset($data['email']))                 $t->setEmail($data['email']);
        if (isset($data['adresse']))               $t->setAdresse($data['adresse']);
        if (isset($data['ville']))                 $t->setVille($data['ville']);
        if (isset($data['source']))                $t->setSource($data['source']);
        if (isset($data['pays']))                  $t->setPays($data['pays']);
    }

    public function serialize(Tiers $t): array
    {
        return [
            'id'                    => $t->getId(),
            'referent'              => $t->getReferent(),
            'nom'                   => $t->getNom(),
            'nomAlternatif'         => $t->getNomAlternatif()         ?? '',
            'codeBarres'            => $t->getCodeBarres()            ?? '',
            'codeClient'            => $t->getCodeClient()            ?? '',
            'compteComptableClient' => $t->getCompteComptableClient() ?? '',
            'commerciaux'           => $t->getCommerciaux()           ?? '',
            'codePostal'            => $t->getCodePostal()            ?? '',
            'typeTiers'             => is_array($t->getTypeTiers()) ? $t->getTypeTiers() : [],
            'telephone'             => $t->getTelephone()             ?? '',
            'natureTiers'           => $t->getNatureTiers()           ?? '',
            'etat'                  => $t->getEtat()                  ?? 'Actif',
            'email'                 => $t->getEmail()                 ?? '',
            'adresse'               => $t->getAdresse()               ?? '',
            'ville'                 => $t->getVille()                 ?? '',
            'source'                => $t->getSource()                ?? 'manuel',
            'pays'                  => $t->getPays()                  ?? '',
        ];
    }
}