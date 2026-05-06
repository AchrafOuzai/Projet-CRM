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
    private $id = null;

    #[ORM\Column(type: 'string', length: 100, unique: true)]
    private $email = null;

    #[ORM\Column(type: 'json')]
    private $roles = [];

    #[ORM\Column(type: 'string', length: 255)]
    private $password = null;

    #[ORM\Column(type: 'string', length: 100)]
    private $nom = null;

    public function getId() { return $this->id; }
    public function getEmail() { return $this->email; }
    public function setEmail($email) { $this->email = $email; return $this; }
    public function getUserIdentifier(): string { return (string) $this->email; }
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }
    public function setRoles(array $roles) { $this->roles = $roles; return $this; }
    public function getPassword(): string { return $this->password; }
    public function setPassword($password) { $this->password = $password; return $this; }
    public function getNom() { return $this->nom; }
    public function setNom($nom) { $this->nom = $nom; return $this; }
    public function eraseCredentials(): void {}
}