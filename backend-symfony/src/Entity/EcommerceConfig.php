<?php

namespace App\Entity;

use App\Repository\EcommerceConfigRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EcommerceConfigRepository::class)]
class EcommerceConfig
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 50)]
    private $type; // 'prestashop' | 'woocommerce' | 'shopify'

    #[ORM\Column(type: 'string', length: 255)]
    private $shopUrl;

    #[ORM\Column(type: 'string', length: 255)]
    private $apiKey;

    #[ORM\Column(type: 'boolean')]
    private $isActive = false;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private $lastSync;

    #[ORM\Column(type: 'integer', nullable: true)]
    private $lastOrderId = 0;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private $lastCustomerSync;

    public function getId()                  { return $this->id; }

    public function getType()                { return $this->type; }
    public function setType($v)              { $this->type = $v; return $this; }

    public function getShopUrl()             { return $this->shopUrl; }
    public function setShopUrl($v)           { $this->shopUrl = $v; return $this; }

    public function getApiKey()              { return $this->apiKey; }
    public function setApiKey($v)            { $this->apiKey = $v; return $this; }

    public function getIsActive()            { return $this->isActive; }
    public function setIsActive($v)          { $this->isActive = $v; return $this; }

    public function getLastSync()            { return $this->lastSync; }
    public function setLastSync($v)          { $this->lastSync = $v; return $this; }

    public function getLastOrderId()         { return $this->lastOrderId; }
    public function setLastOrderId($v)       { $this->lastOrderId = $v; return $this; }

    public function getLastCustomerSync()    { return $this->lastCustomerSync; }
    public function setLastCustomerSync($v)  { $this->lastCustomerSync = $v; return $this; }
}