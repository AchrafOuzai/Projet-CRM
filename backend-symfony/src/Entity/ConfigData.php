<?php

namespace App\Entity;

use App\Repository\ConfigDataRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ConfigDataRepository::class)]
class ConfigData
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: 'integer')]
    private $id = null;

    #[ORM\Column(type: 'string', length: 50, unique: true)]
    private $cle = null;

    #[ORM\Column(type: 'json')]
    private $valeur = [];

    public function getId() { return $this->id; }

    public function getCle() { return $this->cle; }
    public function setCle($cle) { $this->cle = $cle; return $this; }

    public function getValeur() { return $this->valeur; }
    public function setValeur($valeur) { $this->valeur = $valeur; return $this; }
}