<?php

namespace App\Controller;

use App\Repository\CommandeRepository;
use App\Repository\ProduitRepository;
use App\Repository\DepenseRepository;
use App\Repository\AdsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractController
{
    private $commandeRepo;
    private $produitRepo;
    private $depenseRepo;
    private $adsRepo;

    public function __construct(
        CommandeRepository $commandeRepo,
        ProduitRepository  $produitRepo,
        DepenseRepository  $depenseRepo,
        AdsRepository      $adsRepo
    ) {
        $this->commandeRepo = $commandeRepo;
        $this->produitRepo  = $produitRepo;
        $this->depenseRepo  = $depenseRepo;
        $this->adsRepo      = $adsRepo;
    }

    #[Route('/api/dashboard/stats', name: 'api_dashboard_stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        $commandes = $this->commandeRepo->findAll();
        $produits  = $this->produitRepo->findAll();
        $depenses  = $this->depenseRepo->findAll();

        $total      = count($commandes);
        $confirmees = array_filter($commandes, fn($c) => $c->getConfirmation() === 'Confirmée');
        $livrees    = array_filter($commandes, fn($c) => $c->getLivraison()    === 'Livrée');
        $expediees  = array_filter($commandes, fn($c) => $c->getLivraison()    === 'Expédiée');
        $payees     = array_filter($commandes, fn($c) => $c->getLivraison()    === 'Payée');
        $retours    = array_filter($commandes, fn($c) => $c->getLivraison()    === 'Retour');
        $annulees   = array_filter($commandes, fn($c) => $c->getLivraison()    === 'Annulée');

        // Statuts confirmation
        $pasInteresse   = array_filter($commandes, fn($c) => $c->getConfirmation() === 'Pas intéressé');
        $pasReponse     = array_filter($commandes, fn($c) => $c->getConfirmation() === 'Pas de réponse');
        $injoignable    = array_filter($commandes, fn($c) => $c->getConfirmation() === 'Injoignable');
        $fauxNumero     = array_filter($commandes, fn($c) => $c->getConfirmation() === 'Faux numéro');
        $deuxiemeAppel  = array_filter($commandes, fn($c) => $c->getConfirmation() === '2ème appel');

        $ca             = array_sum(array_map(fn($c) => (float)$c->getPrixVenteTotal(), $livrees));
        $totalDepenses  = array_sum(array_map(fn($d) => (float)$d->getMontant(), $depenses));

        // Ventes par mois
        $parMois = [];
        foreach ($commandes as $c) {
            $mois = $c->getDate() ? $c->getDate()->format('n') : 0;
            if (!isset($parMois[$mois])) {
                $parMois[$mois] = ['commandes' => 0, 'livrees' => 0, 'ventes' => 0, 'payees' => 0, 'ventesPay' => 0];
            }
            $parMois[$mois]['commandes']++;
            if ($c->getLivraison() === 'Livrée') {
                $parMois[$mois]['livrees']++;
                $parMois[$mois]['ventes'] += (float)$c->getPrixVenteTotal();
            }
            if ($c->getLivraison() === 'Payée') {
                $parMois[$mois]['payees']++;
                $parMois[$mois]['ventesPay'] += (float)$c->getPrixVenteTotal();
            }
        }

        // Ventes par produit
        $parProduit = [];
        foreach ($commandes as $c) {
            $key = $c->getDesignation() ?? 'Inconnu';
            if (!isset($parProduit[$key])) {
                $parProduit[$key] = ['commandes' => 0, 'qte' => 0, 'livrees' => 0, 'ventes' => 0];
            }
            $parProduit[$key]['commandes']++;
            $parProduit[$key]['qte'] += (int)$c->getQuantite();
            if ($c->getLivraison() === 'Livrée') {
                $parProduit[$key]['livrees']++;
                $parProduit[$key]['ventes'] += (float)$c->getPrixVenteTotal();
            }
        }

        // Par ville
        $parVille = [];
        foreach ($commandes as $c) {
            $key = $c->getVille() ?? 'Inconnue';
            $parVille[$key] = ($parVille[$key] ?? 0) + 1;
        }

        // Par agent
        $parAgent = [];
        foreach ($commandes as $c) {
            $key = $c->getAgent() ?: 'Sans agent';
            if (!isset($parAgent[$key])) {
                $parAgent[$key] = ['total' => 0, 'confirmees' => 0, 'livrees' => 0];
            }
            $parAgent[$key]['total']++;
            if ($c->getConfirmation() === 'Confirmée') $parAgent[$key]['confirmees']++;
            if ($c->getLivraison()    === 'Livrée')    $parAgent[$key]['livrees']++;
        }

        // Par source (e-commerce stats)
        $parSource = [];
        foreach ($commandes as $c) {
            $key = $c->getSource() ?? 'manuel';
            $parSource[$key] = ($parSource[$key] ?? 0) + 1;
        }

        return $this->json([
            'stats' => [
                'total'            => $total,
                'confirmees'       => count($confirmees),
                'livrees'          => count($livrees),
                'expediees'        => count($expediees),
                'payees'           => count($payees),
                'retours'          => count($retours),
                'annulees'         => count($annulees),
                'pasInteresse'     => count($pasInteresse),
                'pasReponse'       => count($pasReponse),
                'injoignable'      => count($injoignable),
                'fauxNumero'       => count($fauxNumero),
                'deuxiemeAppel'    => count($deuxiemeAppel),
                'ca'               => $ca,
                'totalDepenses'    => $totalDepenses,
                'benefice'         => $ca - $totalDepenses,
                'tauxConfirmation' => $total ? round(count($confirmees) / $total * 100) : 0,
                'tauxLivraison'    => count($confirmees) ? round(count($livrees) / count($confirmees) * 100) : 0,
                'tauxRetour'       => count($confirmees) ? round(count($retours)  / count($confirmees) * 100) : 0,
                'stockTotal'       => array_sum(array_map(fn($p) => $p->getStock() ?? 0, $produits)),
            ],
            'ventesParMois'    => $parMois,
            'ventesParProduit' => $parProduit,
            'parVille'         => $parVille,
            'agents'           => $parAgent,
            'parSource'        => $parSource,
        ]);
    }
}