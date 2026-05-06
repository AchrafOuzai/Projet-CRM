export interface Commande {
  id?: number;
  date: string;
  designation: string;
  client: string;
  telephone: string;
  adresse: string;
  ville: string;
  quantite: number;
  prixVenteTotal: number;
  typeCde: string;
  agent: string;
  confirmation: string;
  livraison: string;
  ref: string;
  commentaire: string;
  fraisLivraison: number;
  whatsap: string;
  tiersId?: number;
  tiersNom?: string;
  // Nouveaux champs e-commerce
  source?: string;          // 'manuel' | 'prestashop' | 'woocommerce'
  statutEcommerce?: string; // statut original plateforme (Terminée, En cours...)
  modePaiement?: string;    // CB, virement, chèque, Payments by check...
  pays?: string;            // pays de livraison
  emailClient?: string;     // email du client
}

export interface Produit {
  id?: number;
  reference: string;
  designation: string;
  categorie: string;
  prixAchat: number;
  prixVente: number;
  prixVente2: number;
  prixVente3: number;
  prixVente4: number;
  prixVente5: number;
  stock: number;
}

export interface Ads {
  id?: number;
  numeroCampagne: string;
  date: string;
  produit: string;
  objetPublicitaire: string;
  admin: string;
  dureeJours: number;
  montantDollars: number;
  montantDH: number;
  resultat: string;
  evaluation: string;
  totalDollars: number;
  totalDH: number;
  prospect: number;
}

export interface Depense {
  id?: number;
  date: string;
  designation: string;
  montant: number;
  categorie: string;
  commentaire: string;
}

export interface DataConfig {
  statutsConfirmation: string[];
  statutsLivraison: string[];
  villes: any[];
  agents: string[];
  sites: string[];
  admins: string[];
  mois: string[];
  categoriesProduits: string[];
  fraisTelephonique: number;
  prixParCommandeLivree: number;
}