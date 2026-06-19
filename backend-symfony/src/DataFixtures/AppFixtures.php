<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // AppFixtures désactivé — utiliser SuperAdminFixture uniquement
        // php bin/console doctrine:fixtures:load --append --group=superadmin
        echo "ℹ️  AppFixtures vide — aucune donnée insérée.\n";
    }
}








/*namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\Produit;
use App\Entity\Commande;
use App\Entity\ConfigData;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private $hasher;

    public function __construct(UserPasswordHasherInterface $hasher)
    {
        $this->hasher = $hasher;
    }

    public function load(ObjectManager $manager): void
    {
        // ---- USER ADMIN ----
        $user = new User();
        $user->setEmail('admin@crm.com');
        $user->setNom('Administrateur');
        $user->setRoles(['ROLE_ADMIN']);
        $user->setPassword($this->hasher->hashPassword($user, 'admin123'));
        $manager->persist($user);
        $manager->flush();

        echo "✅ Utilisateur créé : admin@crm.com / admin123\n";

        // ---- PRODUITS ----
        $produitsData = [
            ['P001', 'Montre Luxe',       'Accessoires', 200, 450, 850,  1200, 1550, 1900, 25],
            ['P002', 'Sac Cuir',          'Mode',        150, 350, 680,  990,  1280, 1550, 18],
            ['P003', 'Chaussures Sport',  'Sport',       180, 399, 760,  1100, 1420, 1700, 30],
            ['P004', 'Parfum Elite',      'Beauté',      90,  220, 420,  600,  760,  900,  50],
            ['P005', 'Hijab Premium',     'Mode',        40,  99,  190,  270,  340,  400,  100],
        ];

        foreach ($produitsData as $p) {
            $produit = new Produit();
            $produit->setReference($p[0]);
            $produit->setDesignation($p[1]);
            $produit->setCategorie($p[2]);
            $produit->setPrixAchat((string)$p[3]);
            $produit->setPrixVente((string)$p[4]);
            $produit->setPrixVente2((string)$p[5]);
            $produit->setPrixVente3((string)$p[6]);
            $produit->setPrixVente4((string)$p[7]);
            $produit->setPrixVente5((string)$p[8]);
            $produit->setStock($p[9]);
            $manager->persist($produit);
        }

        $manager->flush();
        echo "✅ Produits créés\n";

        // ---- COMMANDES ----
        $confirmations = ['Confirmée', 'Pas intéressé', 'Pas de réponse', 'Injoignable', 'Confirmée', 'Confirmée'];
        $livraisons    = ['Livrée', 'Expédiée', 'Retour', 'Annulée', 'Payée', 'En attente'];
        $villes        = ['Casablanca', 'Rabat', 'Marrakech', 'Fès', 'Tanger'];
        $produitNames  = ['Montre Luxe', 'Sac Cuir', 'Chaussures Sport', 'Parfum Elite', 'Hijab Premium'];
        $agents        = ['Agent1', 'Agent2', 'Agent3'];

        for ($i = 1; $i <= 30; $i++) {
            $conf = $confirmations[array_rand($confirmations)];
            $livr = ($conf === 'Confirmée') ? $livraisons[array_rand($livraisons)] : '';

            $commande = new Commande();
            $commande->setDate(new \DateTime(sprintf('2024-%02d-%02d', rand(1, 12), rand(1, 28))));
            $commande->setDesignation($produitNames[array_rand($produitNames)]);
            $commande->setClient('Client ' . $i);
            $commande->setTelephone('06' . rand(10000000, 99999999));
            $commande->setAdresse('Adresse ' . $i);
            $commande->setVille($villes[array_rand($villes)]);
            $commande->setQuantite(rand(1, 3));
            $commande->setPrixVenteTotal((string)(rand(1, 10) * 100));
            $commande->setTypeCde(rand(0, 1) ? 'WhatsApp' : 'Site web');
            $commande->setAgent($agents[array_rand($agents)]);
            $commande->setConfirmation($conf);
            $commande->setLivraison($livr);
            $commande->setRef('REF' . str_pad($i, 3, '0', STR_PAD_LEFT));
            $commande->setFraisLivraison('30');
            $commande->setWhatsap(rand(0, 1) ? 'Oui' : 'Non');
            $manager->persist($commande);
        }

        $manager->flush();
        echo "✅ Commandes créées\n";

        // ---- CONFIG ----
        $configs = [
            'categoriesProduits'  => ['Electronique', 'Mode', 'Maison', 'Beauté', 'Sport', 'Autre'],
            'statutsConfirmation' => ['Confirmée', 'Pas intéressé', 'Pas de réponse', 'Injoignable', 'Faux numéro', '2ème appel'],
            'statutsLivraison'    => ['Livrée', 'Expédiée', 'Retour', 'Annulée', 'Payée', 'En attente'],
            'agents'              => ['Agent1', 'Agent2', 'Agent3'],
            'sites'               => ['Site1', 'Site2'],
            'admins'              => ['Admin1', 'Admin2'],
            'refs'                => ['REF001', 'REF002', 'REF003'],
            'villes'              => [
                ['nom' => 'Casablanca', 'fraisLivraison' => 25],
                ['nom' => 'Rabat',      'fraisLivraison' => 30],
                ['nom' => 'Marrakech',  'fraisLivraison' => 40],
                ['nom' => 'Fès',        'fraisLivraison' => 40],
                ['nom' => 'Tanger',     'fraisLivraison' => 45],
                ['nom' => 'Agadir',     'fraisLivraison' => 50],
            ],
        ];

        foreach ($configs as $cle => $valeur) {
            $config = new ConfigData();
            $config->setCle($cle);
            $config->setValeur($valeur);
            $manager->persist($config);
        }

        $manager->flush();
        echo "✅ Config créée\n";
    }
}*/