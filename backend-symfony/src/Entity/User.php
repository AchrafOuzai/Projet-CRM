<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'user')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 100, unique: true)]
    private ?string $email = null;

    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $password = null;

    #[ORM\Column(type: 'string', length: 100)]
    private ?string $nom = null;

    // ── NOUVEAU : rôle unique simplifié ──────────────────
    #[ORM\Column(type: 'string', length: 50, options: ['default' => 'ROLE_AGENT'])]
    private string $role = 'ROLE_AGENT';

    // ── NOUVEAU : lien vers le tenant (null = Super Admin) 
    #[ORM\ManyToOne(targetEntity: Tenant::class, inversedBy: 'users')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Tenant $tenant = null;

    // ── NOUVEAU : permissions par module (pour les agents)
    // ex: ["commandes", "livraisons", "tiers"]
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $permissions = null;

    public function getId(): ?int { return $this->id; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail($email): self { $this->email = $email; return $this; }

    public function getUserIdentifier(): string { return (string) $this->email; }

    public function getRoles(): array
    {
        $roles   = $this->roles;
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }
    public function setRoles(array $roles): self { $this->roles = $roles; return $this; }

    public function getPassword(): string { return $this->password; }
    public function setPassword($password): self { $this->password = $password; return $this; }

    public function getNom(): ?string { return $this->nom; }
    public function setNom($nom): self { $this->nom = $nom; return $this; }

    public function getRole(): string { return $this->role; }
    public function setRole(string $role): self { $this->role = $role; return $this; }

    public function getTenant(): ?Tenant { return $this->tenant; }
    public function setTenant(?Tenant $tenant): self { $this->tenant = $tenant; return $this; }

    public function getPermissions(): ?array { return $this->permissions; }
    public function setPermissions(?array $permissions): self { $this->permissions = $permissions; return $this; }

    public function isSuperAdmin(): bool { return $this->role === 'ROLE_SUPER_ADMIN'; }
    public function isAdmin(): bool { return $this->role === 'ROLE_ADMIN'; }
    public function isAgent(): bool { return $this->role === 'ROLE_AGENT'; }

    public function eraseCredentials(): void {}
}