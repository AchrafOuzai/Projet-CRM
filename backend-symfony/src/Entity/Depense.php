<?php

namespace App\Entity;

use App\Repository\DepenseRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DepenseRepository::class)]
class Depense
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: 'integer')]
    private $id = null;

    #[ORM\Column(type: 'date')]
    private $date = null;

    #[ORM\Column(length: 255)]
    private $designation = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private $montant = null;

    #[ORM\Column(length: 100, nullable: true)]
    private $categorie = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private $commentaire = null;

    public function getId() { return $this->id; }

    public function getDate() { return $this->date; }
    public function setDate($date) { $this->date = $date; return $this; }

    public function getDesignation() { return $this->designation; }
    public function setDesignation($designation) { $this->designation = $designation; return $this; }

    public function getMontant() { return $this->montant; }
    public function setMontant($montant) { $this->montant = $montant; return $this; }

    public function getCategorie() { return $this->categorie; }
    public function setCategorie($categorie) { $this->categorie = $categorie; return $this; }

    public function getCommentaire() { return $this->commentaire; }
    public function setCommentaire($commentaire) { $this->commentaire = $commentaire; return $this; }
}