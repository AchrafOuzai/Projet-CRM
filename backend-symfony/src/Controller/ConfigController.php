<?php

namespace App\Controller;

use App\Entity\ConfigData;
use App\Repository\ConfigDataRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ConfigController extends AbstractController
{
    private $em;
    private $repo;

    public function __construct(EntityManagerInterface $em, ConfigDataRepository $repo)
    {
        $this->em   = $em;
        $this->repo = $repo;
    }

    #[Route('/api/config', name: 'api_config_get', methods: ['GET'])]
    public function get(): JsonResponse
    {
        $configs = $this->repo->findAll();
        $result  = [];
        foreach ($configs as $config) {
            $result[$config->getCle()] = $config->getValeur();
        }
        return $this->json($result);
    }

    #[Route('/api/config', name: 'api_config_save', methods: ['POST'])]
    public function save(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        foreach ($data as $cle => $valeur) {
            $config = $this->repo->findOneBy(['cle' => $cle]);
            if (!$config) {
                $config = new ConfigData();
                $config->setCle($cle);
                $this->em->persist($config);
            }
            $config->setValeur(is_array($valeur) ? $valeur : [$valeur]);
        }
        $this->em->flush();
        return $this->json(['success' => true]);
    }
}