<?php

namespace App\Entity;

use App\Repository\ContactRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ContactRepository::class)]
class Contact
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 100)]
    private $nom;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private $prenom;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private $telephone;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private $telPortable;

    #[ORM\Column(type: 'string', length: 150, nullable: true)]
    private $email;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private $nomAlternatif;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private $visibilite;

    #[ORM\Column(type: 'string', length: 50)]
    private $etat = 'Actif';

    #[ORM\ManyToOne(targetEntity: Tiers::class)]
    #[ORM\JoinColumn(name: 'tiers_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private $tiers;

    public function getId()            { return $this->id; }
    public function getNom()           { return $this->nom; }
    public function setNom($v)         { $this->nom = $v; return $this; }
    public function getPrenom()        { return $this->prenom; }
    public function setPrenom($v)      { $this->prenom = $v; return $this; }
    public function getTelephone()     { return $this->telephone; }
    public function setTelephone($v)   { $this->telephone = $v; return $this; }
    public function getTelPortable()   { return $this->telPortable; }
    public function setTelPortable($v) { $this->telPortable = $v; return $this; }
    public function getEmail()         { return $this->email; }
    public function setEmail($v)       { $this->email = $v; return $this; }
    public function getNomAlternatif() { return $this->nomAlternatif; }
    public function setNomAlternatif($v) { $this->nomAlternatif = $v; return $this; }
    public function getVisibilite()    { return $this->visibilite; }
    public function setVisibilite($v)  { $this->visibilite = $v; return $this; }
    public function getEtat()          { return $this->etat; }
    public function setEtat($v)        { $this->etat = $v; return $this; }
    public function getTiers()         { return $this->tiers; }
    public function setTiers($v)       { $this->tiers = $v; return $this; }
}