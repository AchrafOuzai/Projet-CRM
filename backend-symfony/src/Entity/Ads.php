<?php

namespace App\Entity;

use App\Repository\AdsRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AdsRepository::class)]
class Ads
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: 'integer')]
    private $id = null;

    #[ORM\Column(length: 50, nullable: true)]
    private $numeroCampagne = null;

    #[ORM\Column(type: 'date')]
    private $date = null;

    #[ORM\Column(length: 255, nullable: true)]
    private $produit = null;

    #[ORM\Column(length: 100, nullable: true)]
    private $objetPublicitaire = null;

    #[ORM\Column(length: 100, nullable: true)]
    private $admin = null;

    #[ORM\Column(nullable: true)]
    private $dureeJours = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private $montantDollars = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private $montantDH = null;

    #[ORM\Column(length: 100, nullable: true)]
    private $resultat = null;

    #[ORM\Column(length: 20, nullable: true)]
    private $evaluation = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private $totalDollars = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private $totalDH = null;

    #[ORM\Column(nullable: true)]
    private $prospect = null;

    public function getId() { return $this->id; }

    public function getNumeroCampagne() { return $this->numeroCampagne; }
    public function setNumeroCampagne($numeroCampagne) { $this->numeroCampagne = $numeroCampagne; return $this; }

    public function getDate() { return $this->date; }
    public function setDate($date) { $this->date = $date; return $this; }

    public function getProduit() { return $this->produit; }
    public function setProduit($produit) { $this->produit = $produit; return $this; }

    public function getObjetPublicitaire() { return $this->objetPublicitaire; }
    public function setObjetPublicitaire($objetPublicitaire) { $this->objetPublicitaire = $objetPublicitaire; return $this; }

    public function getAdmin() { return $this->admin; }
    public function setAdmin($admin) { $this->admin = $admin; return $this; }

    public function getDureeJours() { return $this->dureeJours; }
    public function setDureeJours($dureeJours) { $this->dureeJours = $dureeJours; return $this; }

    public function getMontantDollars() { return $this->montantDollars; }
    public function setMontantDollars($montantDollars) { $this->montantDollars = $montantDollars; return $this; }

    public function getMontantDH() { return $this->montantDH; }
    public function setMontantDH($montantDH) { $this->montantDH = $montantDH; return $this; }

    public function getResultat() { return $this->resultat; }
    public function setResultat($resultat) { $this->resultat = $resultat; return $this; }

    public function getEvaluation() { return $this->evaluation; }
    public function setEvaluation($evaluation) { $this->evaluation = $evaluation; return $this; }

    public function getTotalDollars() { return $this->totalDollars; }
    public function setTotalDollars($totalDollars) { $this->totalDollars = $totalDollars; return $this; }

    public function getTotalDH() { return $this->totalDH; }
    public function setTotalDH($totalDH) { $this->totalDH = $totalDH; return $this; }

    public function getProspect() { return $this->prospect; }
    public function setProspect($prospect) { $this->prospect = $prospect; return $this; }
}