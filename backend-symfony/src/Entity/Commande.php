<?php

namespace App\Entity;

use App\Repository\CommandeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommandeRepository::class)]
class Commande
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: 'integer')]
    private $id = null;

    #[ORM\Column(type: 'date')]
    private $date = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $designation;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $client;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private $telephone;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private $adresse;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private $ville;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $quantite;

    #[ORM\Column(type: 'float', nullable: true)]
    private $prixVenteTotal;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private $typeCde;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private $agent;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private $confirmation;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private $livraison;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private $ref;

    #[ORM\Column(type: 'text', nullable: true)]
    private $commentaire;

    #[ORM\Column(type: 'float', nullable: true)]
    private $fraisLivraison;

    #[ORM\Column(type: 'string', length: 10, nullable: true)]
    private $whatsap;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private $createdAt = null;

    #[ORM\ManyToOne(targetEntity: Tiers::class)]
    #[ORM\JoinColumn(name: 'tiers_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private $tiers = null;

    // Champs e-commerce
    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private $source; // 'prestashop', 'woocommerce', 'manuel'

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private $statutEcommerce; // statut original depuis la plateforme

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private $modePaiement; // Payment method

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private $pays; // Delivery country

    #[ORM\Column(type: 'string', length: 150, nullable: true)]
    private $emailClient; // Email du client

    public function getId()                { return $this->id; }
    public function getDate()              { return $this->date; }
    public function setDate($v)            { $this->date = $v; return $this; }
    public function getDesignation()       { return $this->designation; }
    public function setDesignation($v)     { $this->designation = $v; return $this; }
    public function getClient()            { return $this->client; }
    public function setClient($v)          { $this->client = $v; return $this; }
    public function getTelephone()         { return $this->telephone; }
    public function setTelephone($v)       { $this->telephone = $v; return $this; }
    public function getAdresse()           { return $this->adresse; }
    public function setAdresse($v)         { $this->adresse = $v; return $this; }
    public function getVille()             { return $this->ville; }
    public function setVille($v)           { $this->ville = $v; return $this; }
    public function getQuantite()          { return $this->quantite; }
    public function setQuantite($v)        { $this->quantite = $v; return $this; }
    public function getPrixVenteTotal()    { return $this->prixVenteTotal; }
    public function setPrixVenteTotal($v)  { $this->prixVenteTotal = $v; return $this; }
    public function getTypeCde()           { return $this->typeCde; }
    public function setTypeCde($v)         { $this->typeCde = $v; return $this; }
    public function getAgent()             { return $this->agent; }
    public function setAgent($v)           { $this->agent = $v; return $this; }
    public function getConfirmation()      { return $this->confirmation; }
    public function setConfirmation($v)    { $this->confirmation = $v; return $this; }
    public function getLivraison()         { return $this->livraison; }
    public function setLivraison($v)       { $this->livraison = $v; return $this; }
    public function getRef()               { return $this->ref; }
    public function setRef($v)             { $this->ref = $v; return $this; }
    public function getCommentaire()       { return $this->commentaire; }
    public function setCommentaire($v)     { $this->commentaire = $v; return $this; }
    public function getFraisLivraison()    { return $this->fraisLivraison; }
    public function setFraisLivraison($v)  { $this->fraisLivraison = $v; return $this; }
    public function getWhatsap()           { return $this->whatsap; }
    public function setWhatsap($v)         { $this->whatsap = $v; return $this; }
    public function getCreatedAt()         { return $this->createdAt; }
    public function setCreatedAt($v)       { $this->createdAt = $v; return $this; }
    public function getTiers()             { return $this->tiers; }
    public function setTiers($v)           { $this->tiers = $v; return $this; }
    public function getSource()            { return $this->source; }
    public function setSource($v)          { $this->source = $v; return $this; }
    public function getStatutEcommerce()   { return $this->statutEcommerce; }
    public function setStatutEcommerce($v) { $this->statutEcommerce = $v; return $this; }
    public function getModePaiement()      { return $this->modePaiement; }
    public function setModePaiement($v)    { $this->modePaiement = $v; return $this; }
    public function getPays()              { return $this->pays; }
    public function setPays($v)            { $this->pays = $v; return $this; }
    public function getEmailClient()       { return $this->emailClient; }
    public function setEmailClient($v)     { $this->emailClient = $v; return $this; }
}