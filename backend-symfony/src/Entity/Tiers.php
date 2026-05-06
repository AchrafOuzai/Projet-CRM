<?php

namespace App\Entity;

use App\Repository\TiersRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TiersRepository::class)]
class Tiers
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 20, unique: true, nullable: true)]
    private $referent;

    #[ORM\Column(type: 'string', length: 255)]
    private $nom;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $nomAlternatif;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private $codeBarres;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private $codeClient;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private $compteComptableClient;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $commerciaux;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private $codePostal;

    #[ORM\Column(type: 'json')]
    private $typeTiers = [];

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private $telephone;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private $natureTiers;

    #[ORM\Column(type: 'string', length: 50)]
    private $etat = 'Actif';

    #[ORM\Column(type: 'string', length: 150, nullable: true)]
    private $email;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $adresse;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private $ville;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private $source; // 'prestashop', 'woocommerce', 'manuel'

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private $pays;

    public function getId()                      { return $this->id; }
    public function getReferent()                { return $this->referent; }
    public function setReferent($v)              { $this->referent = $v; return $this; }
    public function getNom()                     { return $this->nom; }
    public function setNom($v)                   { $this->nom = $v; return $this; }
    public function getNomAlternatif()           { return $this->nomAlternatif; }
    public function setNomAlternatif($v)         { $this->nomAlternatif = $v; return $this; }
    public function getCodeBarres()              { return $this->codeBarres; }
    public function setCodeBarres($v)            { $this->codeBarres = $v; return $this; }
    public function getCodeClient()              { return $this->codeClient; }
    public function setCodeClient($v)            { $this->codeClient = $v; return $this; }
    public function getCompteComptableClient()   { return $this->compteComptableClient; }
    public function setCompteComptableClient($v) { $this->compteComptableClient = $v; return $this; }
    public function getCommerciaux()             { return $this->commerciaux; }
    public function setCommerciaux($v)           { $this->commerciaux = $v; return $this; }
    public function getCodePostal()              { return $this->codePostal; }
    public function setCodePostal($v)            { $this->codePostal = $v; return $this; }
    public function getTypeTiers()               { return $this->typeTiers; }
    public function setTypeTiers($v)             { $this->typeTiers = $v; return $this; }
    public function getTelephone()               { return $this->telephone; }
    public function setTelephone($v)             { $this->telephone = $v; return $this; }
    public function getNatureTiers()             { return $this->natureTiers; }
    public function setNatureTiers($v)           { $this->natureTiers = $v; return $this; }
    public function getEtat()                    { return $this->etat; }
    public function setEtat($v)                  { $this->etat = $v; return $this; }
    public function getEmail()                   { return $this->email; }
    public function setEmail($v)                 { $this->email = $v; return $this; }
    public function getAdresse()                 { return $this->adresse; }
    public function setAdresse($v)               { $this->adresse = $v; return $this; }
    public function getVille()                   { return $this->ville; }
    public function setVille($v)                 { $this->ville = $v; return $this; }
    public function getSource()                  { return $this->source; }
    public function setSource($v)                { $this->source = $v; return $this; }
    public function getPays()                    { return $this->pays; }
    public function setPays($v)                  { $this->pays = $v; return $this; }
}