<?php

namespace App\Entity;

use App\Repository\ProduitRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProduitRepository::class)]
class Produit
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: 'integer')]
    private $id = null;

    #[ORM\Column(type: 'string', length: 50, unique: true)]
    private $reference = null;

    #[ORM\Column(type: 'string', length: 255)]
    private $designation = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private $categorie = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private $prixAchat = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private $prixVente = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private $prixVente2 = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private $prixVente3 = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private $prixVente4 = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private $prixVente5 = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $stock = null;

    public function getId() { return $this->id; }
    public function getReference() { return $this->reference; }
    public function setReference($reference) { $this->reference = $reference; return $this; }
    public function getDesignation() { return $this->designation; }
    public function setDesignation($designation) { $this->designation = $designation; return $this; }
    public function getCategorie() { return $this->categorie; }
    public function setCategorie($categorie) { $this->categorie = $categorie; return $this; }
    public function getPrixAchat() { return $this->prixAchat; }
    public function setPrixAchat($prixAchat) { $this->prixAchat = $prixAchat; return $this; }
    public function getPrixVente() { return $this->prixVente; }
    public function setPrixVente($prixVente) { $this->prixVente = $prixVente; return $this; }
    public function getPrixVente2() { return $this->prixVente2; }
    public function setPrixVente2($prixVente2) { $this->prixVente2 = $prixVente2; return $this; }
    public function getPrixVente3() { return $this->prixVente3; }
    public function setPrixVente3($prixVente3) { $this->prixVente3 = $prixVente3; return $this; }
    public function getPrixVente4() { return $this->prixVente4; }
    public function setPrixVente4($prixVente4) { $this->prixVente4 = $prixVente4; return $this; }
    public function getPrixVente5() { return $this->prixVente5; }
    public function setPrixVente5($prixVente5) { $this->prixVente5 = $prixVente5; return $this; }
    public function getStock() { return $this->stock; }
    public function setStock($stock) { $this->stock = $stock; return $this; }
}