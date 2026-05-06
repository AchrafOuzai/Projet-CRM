<?php

namespace App\Entity;

use App\Repository\ApiTokenRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ApiTokenRepository::class)]
class ApiToken
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 64, unique: true)]
    private $token;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    private $user;

    #[ORM\Column(type: 'datetime_immutable')]
    private $expiresAt;

    public function __construct(User $user)
    {
        $this->user = $user;
        $this->token = bin2hex(random_bytes(32));
        $this->expiresAt = new \DateTimeImmutable('+365 days');
    }

    public function getId() { return $this->id; }
    public function getToken() { return $this->token; }
    public function getUser() { return $this->user; }
    public function getExpiresAt() { return $this->expiresAt; }
    public function isValid(): bool { return $this->expiresAt > new \DateTimeImmutable(); }
}