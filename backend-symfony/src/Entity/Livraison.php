<?php

namespace App\Entity;

use App\Repository\LivraisonRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LivraisonRepository::class)]
class Livraison
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private $numeroSuivi;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private $transporteur;

    #[ORM\Column(type: 'date', nullable: true)]
    private $dateExpedition;

    #[ORM\Column(type: 'string', length: 50)]
    private $statut = 'En transit';

    #[ORM\Column(type: 'text', nullable: true)]
    private $commentaire;

    #[ORM\ManyToOne(targetEntity: Commande::class)]
    #[ORM\JoinColumn(name: 'commande_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private $commande;

    public function getId()                { return $this->id; }
    public function getNumeroSuivi()       { return $this->numeroSuivi; }
    public function setNumeroSuivi($v)     { $this->numeroSuivi = $v; return $this; }
    public function getTransporteur()      { return $this->transporteur; }
    public function setTransporteur($v)    { $this->transporteur = $v; return $this; }
    public function getDateExpedition()    { return $this->dateExpedition; }
    public function setDateExpedition($v)  { $this->dateExpedition = $v; return $this; }
    public function getStatut()            { return $this->statut; }
    public function setStatut($v)          { $this->statut = $v; return $this; }
    public function getCommentaire()       { return $this->commentaire; }
    public function setCommentaire($v)     { $this->commentaire = $v; return $this; }
    public function getCommande()          { return $this->commande; }
    public function setCommande($v)        { $this->commande = $v; return $this; }
}