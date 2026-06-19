<?php

namespace App\Controller;

use App\Repository\CommandeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractController
{
    private $commandeRepo;

    public function __construct(CommandeRepository $commandeRepo)
    {
        $this->commandeRepo = $commandeRepo;
    }

    #[Route('/api/dashboard/stats', name: 'api_dashboard_stats', methods: ['GET'])]
    public function stats(Request $request): JsonResponse
    {
        $user   = $this->getUser();
        $tenant = ($user && method_exists($user, 'getTenant')) ? $user->getTenant() : null;

        if (!$tenant) {
            return $this->json([
                'stats'            => $this->emptyStats(),
                'ventesParMois'    => [],
                'ventesParProduit' => [],
                'parVille'         => [],
                'parSource'        => [],
            ]);
        }

        $dateDebut = $request->query->get('dateDebut');
        $dateFin   = $request->query->get('dateFin');
        $livraison = $request->query->get('livraison');

        if ($dateDebut || $dateFin || $livraison) {
            $commandes = $this->commandeRepo->findByTenantWithFilters($tenant, $dateDebut, $dateFin, $livraison);
        } else {
            $commandes = $this->commandeRepo->findBy(['tenant' => $tenant]);
        }

        $total         = count($commandes);
        $confirmees    = array_filter($commandes, fn($c) => $c->getConfirmation() === 'Confirmée');
        $livrees       = array_filter($commandes, fn($c) => $c->getLivraison()    === 'Livrée');
        $expediees     = array_filter($commandes, fn($c) => $c->getLivraison()    === 'Expédiée');
        $payees        = array_filter($commandes, fn($c) => $c->getLivraison()    === 'Payée');
        $retours       = array_filter($commandes, fn($c) => $c->getLivraison()    === 'Retour');
        $annulees      = array_filter($commandes, fn($c) => $c->getLivraison()    === 'Annulée');
        $pasInteresse  = array_filter($commandes, fn($c) => $c->getConfirmation() === 'Pas intéressé');
        $pasReponse    = array_filter($commandes, fn($c) => $c->getConfirmation() === 'Pas de réponse');
        $injoignable   = array_filter($commandes, fn($c) => $c->getConfirmation() === 'Injoignable');
        $fauxNumero    = array_filter($commandes, fn($c) => $c->getConfirmation() === 'Faux numéro');
        $deuxiemeAppel = array_filter($commandes, fn($c) => $c->getConfirmation() === '2ème appel');

        $ca = array_sum(array_map(fn($c) => (float)$c->getPrixVenteTotal(), $livrees));

        // ── Ventes par mois ──────────────────────────────
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
        ksort($parMois);

        // ── Ventes par désignation ───────────────────────
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
        arsort($parProduit);

        // ── Par ville ────────────────────────────────────
        $parVille = [];
        foreach ($commandes as $c) {
            $key = $c->getVille() ?? 'Inconnue';
            if ($key) $parVille[$key] = ($parVille[$key] ?? 0) + 1;
        }
        arsort($parVille);

        // ── Par source ───────────────────────────────────
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
                'totalDepenses'    => 0,
                'benefice'         => $ca,
                'tauxConfirmation' => $total ? round(count($confirmees) / $total * 100) : 0,
                'tauxLivraison'    => count($confirmees) ? round(count($livrees) / count($confirmees) * 100) : 0,
                'tauxRetour'       => count($confirmees) ? round(count($retours)  / count($confirmees) * 100) : 0,
                'stockTotal'       => 0,
            ],
            'ventesParMois'    => $parMois,
            'ventesParProduit' => $parProduit,
            'parVille'         => $parVille,
            'parSource'        => $parSource,
        ]);
    }

    private function emptyStats(): array
    {
        return [
            'total' => 0, 'confirmees' => 0, 'livrees' => 0, 'expediees' => 0,
            'payees' => 0, 'retours' => 0, 'annulees' => 0,
            'pasInteresse' => 0, 'pasReponse' => 0, 'injoignable' => 0,
            'fauxNumero' => 0, 'deuxiemeAppel' => 0,
            'ca' => 0, 'totalDepenses' => 0, 'benefice' => 0,
            'tauxConfirmation' => 0, 'tauxLivraison' => 0,
            'tauxRetour' => 0, 'stockTotal' => 0,
        ];
    }
}