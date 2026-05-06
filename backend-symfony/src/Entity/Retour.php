<?php

namespace App\Entity;

use App\Repository\RetourRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RetourRepository::class)]
class Retour
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 100, nullable: true, unique: true)]
    private $ref; // 'PS-SLIP-123', 'WC-REFUND-456', 'SH-REFUND-789'

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private $source; // 'prestashop', 'woocommerce', 'shopify', 'manuel'

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private $refCommande; // ref de la commande liée ex: 'PS-7'

    #[ORM\Column(type: 'date', nullable: true)]
    private $date;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $client;

    #[ORM\Column(type: 'string', length: 150, nullable: true)]
    private $emailClient;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private $telephone;

    #[ORM\Column(type: 'float', nullable: true)]
    private $montantRembourse;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private $motif;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private $statut; // 'En attente', 'Remboursé', 'Refusé'

    #[ORM\Column(type: 'text', nullable: true)]
    private $commentaire;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private $ville;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private $pays;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private $createdAt;

    #[ORM\ManyToOne(targetEntity: Tiers::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private $tiers;

    #[ORM\ManyToOne(targetEntity: Commande::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private $commande;

    public function getId()                  { return $this->id; }
    public function getRef()                 { return $this->ref; }
    public function setRef($v)               { $this->ref = $v; return $this; }
    public function getSource()              { return $this->source; }
    public function setSource($v)            { $this->source = $v; return $this; }
    public function getRefCommande()         { return $this->refCommande; }
    public function setRefCommande($v)       { $this->refCommande = $v; return $this; }
    public function getDate()                { return $this->date; }
    public function setDate($v)              { $this->date = $v; return $this; }
    public function getClient()              { return $this->client; }
    public function setClient($v)            { $this->client = $v; return $this; }
    public function getEmailClient()         { return $this->emailClient; }
    public function setEmailClient($v)       { $this->emailClient = $v; return $this; }
    public function getTelephone()           { return $this->telephone; }
    public function setTelephone($v)         { $this->telephone = $v; return $this; }
    public function getMontantRembourse()    { return $this->montantRembourse; }
    public function setMontantRembourse($v)  { $this->montantRembourse = $v; return $this; }
    public function getMotif()               { return $this->motif; }
    public function setMotif($v)             { $this->motif = $v; return $this; }
    public function getStatut()              { return $this->statut; }
    public function setStatut($v)            { $this->statut = $v; return $this; }
    public function getCommentaire()         { return $this->commentaire; }
    public function setCommentaire($v)       { $this->commentaire = $v; return $this; }
    public function getVille()               { return $this->ville; }
    public function setVille($v)             { $this->ville = $v; return $this; }
    public function getPays()                { return $this->pays; }
    public function setPays($v)              { $this->pays = $v; return $this; }
    public function getCreatedAt()           { return $this->createdAt; }
    public function setCreatedAt($v)         { $this->createdAt = $v; return $this; }
    public function getTiers()               { return $this->tiers; }
    public function setTiers($v)             { $this->tiers = $v; return $this; }
    public function getCommande()            { return $this->commande; }
    public function setCommande($v)          { $this->commande = $v; return $this; }
}