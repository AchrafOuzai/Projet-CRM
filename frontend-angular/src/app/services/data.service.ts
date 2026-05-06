import { Injectable } from '@angular/core';
import { HttpClient, HttpHeaders, HttpParams } from '@angular/common/http';
import { Observable, tap, catchError, throwError } from 'rxjs';
import { Router } from '@angular/router';
import { Commande, Produit, Ads, Depense, DataConfig } from '../models/commande.model';

@Injectable({ providedIn: 'root' })
export class DataService {

  private apiUrl = 'http://localhost:8000/api';
  private token: string = '';

  constructor(private http: HttpClient, private router: Router) {
    this.token = localStorage.getItem('jwt_token') || '';
  }

  private getHeaders(): HttpHeaders {
    const token = localStorage.getItem('jwt_token') || this.token;
    return new HttpHeaders({
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`
    });
  }

  private handleError(err: any): Observable<never> {
    console.error('❌ Erreur HTTP:', err.status, err.message);
    if (err.status === 401) {
      this.token = '';
      localStorage.removeItem('jwt_token');
      this.router.navigate(['/login']);
    }
    return throwError(() => err);
  }

  login(email: string, password: string): Observable<any> {
    return this.http.post(`${this.apiUrl}/login`, { email, password }).pipe(
      tap((res: any) => {
        this.token = res.token;
        localStorage.setItem('jwt_token', res.token);
      }),
      catchError(err => throwError(() => err))
    );
  }

  logout() {
    this.token = '';
    localStorage.removeItem('jwt_token');
  }

  isLoggedIn(): boolean {
    this.token = localStorage.getItem('jwt_token') || '';
    return !!this.token;
  }

  getMe(): Observable<any> {
    return this.http.get(`${this.apiUrl}/me`, {
      headers: this.getHeaders()
    }).pipe(catchError(err => this.handleError(err)));
  }

  // ---- COMMANDES ----
  getCommandesObs(filters?: any, tiersId?: number): Observable<Commande[]> {
    let params = new HttpParams();
    if (filters) {
      Object.keys(filters).forEach(k => {
        if (filters[k]) params = params.set(k, filters[k]);
      });
    }
    if (tiersId) params = params.set('tiersId', tiersId.toString());
    return this.http.get<Commande[]>(`${this.apiUrl}/commandes`, {
      headers: this.getHeaders(), params
    }).pipe(catchError(err => this.handleError(err)));
  }

  addCommandeObs(c: Commande): Observable<Commande> {
    return this.http.post<Commande>(`${this.apiUrl}/commandes`, c, {
      headers: this.getHeaders()
    }).pipe(catchError(err => this.handleError(err)));
  }

  updateCommandeObs(c: Commande): Observable<Commande> {
    return this.http.put<Commande>(`${this.apiUrl}/commandes/${c.id}`, c, {
      headers: this.getHeaders()
    }).pipe(catchError(err => this.handleError(err)));
  }

  deleteCommandeObs(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/commandes/${id}`, {
      headers: this.getHeaders()
    }).pipe(catchError(err => this.handleError(err)));
  }

  getStatsObs(): Observable<any> {
    return this.http.get(`${this.apiUrl}/commandes/stats`, {
      headers: this.getHeaders()
    }).pipe(catchError(err => this.handleError(err)));
  }
sendCommandeEmailObs(id: number): Observable<any> {
  return this.http.post(
    `${this.apiUrl}/commandes/${id}/send-email`,
    {},
    { headers: this.getHeaders() }
  );
}
  // ---- PRODUITS ----
  getProduitsObs(): Observable<Produit[]> {
    return this.http.get<Produit[]>(`${this.apiUrl}/produits`, {
      headers: this.getHeaders()
    }).pipe(catchError(err => this.handleError(err)));
  }

  addProduitObs(p: Produit): Observable<Produit> {
    return this.http.post<Produit>(`${this.apiUrl}/produits`, p, {
      headers: this.getHeaders()
    }).pipe(catchError(err => this.handleError(err)));
  }

  updateProduitObs(p: Produit): Observable<Produit> {
    return this.http.put<Produit>(`${this.apiUrl}/produits/${p.id}`, p, {
      headers: this.getHeaders()
    }).pipe(catchError(err => this.handleError(err)));
  }

  deleteProduitObs(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/produits/${id}`, {
      headers: this.getHeaders()
    }).pipe(catchError(err => this.handleError(err)));
  }

  // ---- ADS ----
  getAdsObs(): Observable<Ads[]> {
    return this.http.get<Ads[]>(`${this.apiUrl}/ads`, {
      headers: this.getHeaders()
    }).pipe(catchError(err => this.handleError(err)));
  }

  addAdsObs(a: Ads): Observable<Ads> {
    return this.http.post<Ads>(`${this.apiUrl}/ads`, a, {
      headers: this.getHeaders()
    }).pipe(catchError(err => this.handleError(err)));
  }

  updateAdsObs(a: Ads): Observable<Ads> {
    return this.http.put<Ads>(`${this.apiUrl}/ads/${a.id}`, a, {
      headers: this.getHeaders()
    }).pipe(catchError(err => this.handleError(err)));
  }

  deleteAdsObs(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/ads/${id}`, {
      headers: this.getHeaders()
    }).pipe(catchError(err => this.handleError(err)));
  }

  // ---- DEPENSES ----
  getDepensesObs(): Observable<Depense[]> {
    return this.http.get<Depense[]>(`${this.apiUrl}/depenses`, {
      headers: this.getHeaders()
    }).pipe(catchError(err => this.handleError(err)));
  }

  addDepenseObs(d: Depense): Observable<Depense> {
    return this.http.post<Depense>(`${this.apiUrl}/depenses`, d, {
      headers: this.getHeaders()
    }).pipe(catchError(err => this.handleError(err)));
  }

  updateDepenseObs(d: Depense): Observable<Depense> {
    return this.http.put<Depense>(`${this.apiUrl}/depenses/${d.id}`, d, {
      headers: this.getHeaders()
    }).pipe(catchError(err => this.handleError(err)));
  }

  deleteDepenseObs(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/depenses/${id}`, {
      headers: this.getHeaders()
    }).pipe(catchError(err => this.handleError(err)));
  }

  // ---- CONFIG ----
  getConfigObs(): Observable<DataConfig> {
    return this.http.get<DataConfig>(`${this.apiUrl}/config`, {
      headers: this.getHeaders()
    }).pipe(catchError(err => this.handleError(err)));
  }

  saveConfigObs(config: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/config`, config, {
      headers: this.getHeaders()
    }).pipe(catchError(err => this.handleError(err)));
  }

  // ---- DASHBOARD ----
  getDashboardStats(): Observable<any> {
    return this.http.get(`${this.apiUrl}/dashboard/stats`, {
      headers: this.getHeaders()
    }).pipe(catchError(err => this.handleError(err)));
  }

  // ---- TIERS ----
  getTiersObs(): Observable<any[]> {
    return this.http.get<any[]>(`${this.apiUrl}/tiers`, {
      headers: this.getHeaders()
    }).pipe(catchError(err => this.handleError(err)));
  }

  addTiersObs(t: any): Observable<any> {
    return this.http.post<any>(`${this.apiUrl}/tiers`, t, {
      headers: this.getHeaders()
    }).pipe(catchError(err => this.handleError(err)));
  }

  updateTiersObs(t: any): Observable<any> {
    return this.http.put<any>(`${this.apiUrl}/tiers/${t.id}`, t, {
      headers: this.getHeaders()
    }).pipe(catchError(err => this.handleError(err)));
  }

  deleteTiersObs(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/tiers/${id}`, {
      headers: this.getHeaders()
    }).pipe(catchError(err => this.handleError(err)));
  }

  // ---- CONTACTS ----
  getContactsObs(tiersId?: number): Observable<any[]> {
    let params = new HttpParams();
    if (tiersId) params = params.set('tiersId', tiersId.toString());
    return this.http.get<any[]>(`${this.apiUrl}/contacts`, {
      headers: this.getHeaders(), params
    }).pipe(catchError(err => this.handleError(err)));
  }

  addContactObs(c: any): Observable<any> {
    return this.http.post<any>(`${this.apiUrl}/contacts`, c, {
      headers: this.getHeaders()
    }).pipe(catchError(err => this.handleError(err)));
  }

  updateContactObs(c: any): Observable<any> {
    return this.http.put<any>(`${this.apiUrl}/contacts/${c.id}`, c, {
      headers: this.getHeaders()
    }).pipe(catchError(err => this.handleError(err)));
  }

  deleteContactObs(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/contacts/${id}`, {
      headers: this.getHeaders()
    }).pipe(catchError(err => this.handleError(err)));
  }

  // ---- LIVRAISONS ----
  getLivraisonsObs(page: number = 1, search: string = '', statut: string = ''): Observable<any> {
    let params = new HttpParams()
      .set('page', page.toString())
      .set('search', search)
      .set('statut', statut);
    return this.http.get<any>(`${this.apiUrl}/livraisons`, {
      headers: this.getHeaders(), params
    }).pipe(catchError(err => this.handleError(err)));
  }

  addLivraisonObs(l: any): Observable<any> {
    return this.http.post<any>(`${this.apiUrl}/livraisons`, l, {
      headers: this.getHeaders()
    }).pipe(catchError(err => this.handleError(err)));
  }

  updateLivraisonObs(l: any): Observable<any> {
    return this.http.put<any>(`${this.apiUrl}/livraisons/${l.id}`, l, {
      headers: this.getHeaders()
    }).pipe(catchError(err => this.handleError(err)));
  }

  deleteLivraisonObs(id: number): Observable<any> {
    return this.http.delete(`${this.apiUrl}/livraisons/${id}`, {
      headers: this.getHeaders()
    }).pipe(catchError(err => this.handleError(err)));
  }

  

  // ---- ECOMMERCE  ----
  getEcommerceStatus(): Observable<any> {
    return this.http.get<any>(`${this.apiUrl}/ecommerce/status`, {
      headers: this.getHeaders()
    }).pipe(catchError(err => this.handleError(err)));
  }

  connectEcommerce(type: string, url: string, apiKey: string): Observable<any> {
    return this.http.post<any>(`${this.apiUrl}/ecommerce/connect`, { type, url, apiKey }, {
      headers: this.getHeaders()
    }).pipe(catchError(err => this.handleError(err)));
  }

  disconnectEcommerce(type: string): Observable<any> {
    return this.http.post<any>(`${this.apiUrl}/ecommerce/disconnect`, { type }, {
      headers: this.getHeaders()
    }).pipe(catchError(err => this.handleError(err)));
  }

  syncEcommerceOrders(type: string): Observable<any> {
    return this.http.post<any>(`${this.apiUrl}/ecommerce/sync/orders`, { type }, {
      headers: this.getHeaders()
    }).pipe(catchError(err => this.handleError(err)));
  }

  syncEcommerceCustomers(type: string): Observable<any> {
    return this.http.post<any>(`${this.apiUrl}/ecommerce/sync/customers`, { type }, {
      headers: this.getHeaders()
    }).pipe(catchError(err => this.handleError(err)));
  }


  // ---- RETOURS ----
getRetoursObs(source?: string): Observable<any[]> {
  let params = new HttpParams();
  if (source) params = params.set('source', source);
  return this.http.get<any[]>(`${this.apiUrl}/retours`, {
    headers: this.getHeaders(), params
  }).pipe(catchError(err => this.handleError(err)));
}

addRetourObs(r: any): Observable<any> {
  return this.http.post<any>(`${this.apiUrl}/retours`, r, {
    headers: this.getHeaders()
  }).pipe(catchError(err => this.handleError(err)));
}

updateRetourObs(r: any): Observable<any> {
  return this.http.put<any>(`${this.apiUrl}/retours/${r.id}`, r, {
    headers: this.getHeaders()
  }).pipe(catchError(err => this.handleError(err)));
}

deleteRetourObs(id: number): Observable<any> {
  return this.http.delete(`${this.apiUrl}/retours/${id}`, {
    headers: this.getHeaders()
  }).pipe(catchError(err => this.handleError(err)));
}

syncEcommerceRetours(type: string): Observable<any> {
  return this.http.post<any>(`${this.apiUrl}/ecommerce/sync/retours`, { type }, {
    headers: this.getHeaders()
  }).pipe(catchError(err => this.handleError(err)));
}

}









/*import { Injectable } from '@angular/core';
import { Commande, Produit, Ads, Depense, DataConfig } from '../models/commande.model';

@Injectable({ providedIn: 'root' })
export class DataService {

  private storageKey = 'crm_data';

  private defaultConfig: DataConfig = {
    categoriesProduits: ['Electronique', 'Mode', 'Maison', 'Beauté', 'Sport', 'Autre'],
    statutsConfirmation: ['Confirmée', 'Pas intéressé', 'Pas de réponse', 'Injoignable', 'Faux numéro', '2ème appel'],
    statutsLivraison: ['Livrée', 'Expédiée', 'Retour', 'Annulée', 'Payée', 'En attente'],
    villes: [
      { nom: 'Casablanca', fraisLivraison: 25 },
      { nom: 'Rabat', fraisLivraison: 30 },
      { nom: 'Marrakech', fraisLivraison: 40 },
      { nom: 'Fès', fraisLivraison: 40 },
      { nom: 'Tanger', fraisLivraison: 45 },
      { nom: 'Agadir', fraisLivraison: 50 },
      { nom: 'Oujda', fraisLivraison: 55 },
      { nom: 'Autre', fraisLivraison: 35 },
    ],
    agents: ['Agent1', 'Agent2', 'Agent3'],
    sites: ['Site1', 'Site2'],
    admins: ['Admin1', 'Admin2'],
    mois: ['Jan','Fév','Mar','Avr','Mai','Juin','Juil','Août','Sep','Oct','Nov','Déc'],
    fraisTelephonique: 200,
    prixParCommandeLivree: 5,
  };

  private data: any;

  constructor() { this.load(); }

  private load() {
    const raw = localStorage.getItem(this.storageKey);
    if (raw) {
      this.data = JSON.parse(raw);
    } else {
      this.data = {
        commandes: this.getSampleCommandes(),
        produits: this.getSampleProduits(),
        ads: this.getSampleAds(),
        depenses: [],
        refs: ['REF001','REF002','REF003'],
        config: this.defaultConfig,
      };
      this.save();
    }
  }

  private save() {
    localStorage.setItem(this.storageKey, JSON.stringify(this.data));
  }

  // ---- COMMANDES ----
  getCommandes(): Commande[] { return this.data.commandes || []; }
  addCommande(c: Commande) {
    c.id = Date.now();
    this.data.commandes.push(c);
    this.save();
  }
  updateCommande(c: Commande) {
    const i = this.data.commandes.findIndex((x: Commande) => x.id === c.id);
    if (i >= 0) { this.data.commandes[i] = c; this.save(); }
  }
  deleteCommande(id: number) {
    this.data.commandes = this.data.commandes.filter((x: Commande) => x.id !== id);
    this.save();
  }

  // ---- PRODUITS ----
  getProduits(): Produit[] { return this.data.produits || []; }
  addProduit(p: Produit) { p.id = Date.now(); this.data.produits.push(p); this.save(); }
  updateProduit(p: Produit) {
    const i = this.data.produits.findIndex((x: Produit) => x.id === p.id);
    if (i >= 0) { this.data.produits[i] = p; this.save(); }
  }
  deleteProduit(id: number) {
    this.data.produits = this.data.produits.filter((x: Produit) => x.id !== id);
    this.save();
  }

  // ---- ADS ----
  getAds(): Ads[] { return this.data.ads || []; }
  addAds(a: Ads) { a.id = Date.now(); this.data.ads.push(a); this.save(); }
  updateAds(a: Ads) {
    const i = this.data.ads.findIndex((x: Ads) => x.id === a.id);
    if (i >= 0) { this.data.ads[i] = a; this.save(); }
  }
  deleteAds(id: number) {
    this.data.ads = this.data.ads.filter((x: Ads) => x.id !== id);
    this.save();
  }

  // ---- DEPENSES ----
  getDepenses(): Depense[] { return this.data.depenses || []; }
  addDepense(d: Depense) { d.id = Date.now(); this.data.depenses.push(d); this.save(); }
  updateDepense(d: Depense) {
    const i = this.data.depenses.findIndex((x: Depense) => x.id === d.id);
    if (i >= 0) { this.data.depenses[i] = d; this.save(); }
  }
  deleteDepense(id: number) {
    this.data.depenses = this.data.depenses.filter((x: Depense) => x.id !== id);
    this.save();
  }

  // ---- CONFIG ----
  getConfig(): DataConfig { return this.data.config || this.defaultConfig; }
  updateConfig(cfg: DataConfig) { this.data.config = cfg; this.save(); }

  // ---- REFS ----
  getRefs(): string[] { return this.data.refs || []; }
  addRef(r: string) { this.data.refs.push(r); this.save(); }
  deleteRef(r: string) { this.data.refs = this.data.refs.filter((x: string) => x !== r); this.save(); }

  // ---- STATS ----
  getStats() {
    const commandes: Commande[] = this.data.commandes;
    const total = commandes.length;
    const confirmees = commandes.filter(c => c.confirmation === 'Confirmée').length;
    const livrees = commandes.filter(c => c.livraison === 'Livrée').length;
    const retours = commandes.filter(c => c.livraison === 'Retour').length;
    const ca = commandes.filter(c => c.livraison === 'Livrée').reduce((s, c) => s + c.prixVenteTotal, 0);
    return { total, confirmees, livrees, retours, ca,
      tauxConfirmation: total ? Math.round(confirmees/total*100) : 0,
      tauxLivraison: confirmees ? Math.round(livrees/confirmees*100) : 0,
      tauxRetour: livrees ? Math.round(retours/livrees*100) : 0,
    };
  }

  getVentesParMois() {
    const map: any = {};
    this.data.commandes.forEach((c: Commande) => {
      const m = new Date(c.date).getMonth();
      if (!map[m]) map[m] = { commandes: 0, livrees: 0, ventes: 0 };
      map[m].commandes++;
      if (c.livraison === 'Livrée') { map[m].livrees++; map[m].ventes += c.prixVenteTotal; }
    });
    return map;
  }

  getVentesParProduit() {
    const map: any = {};
    this.data.commandes.forEach((c: Commande) => {
      if (!map[c.designation]) map[c.designation] = { commandes: 0, ventes: 0, qte: 0 };
      map[c.designation].commandes++;
      map[c.designation].qte += c.quantite;
      if (c.livraison === 'Livrée') map[c.designation].ventes += c.prixVenteTotal;
    });
    return map;
  }

  getCommandesParVille() {
    const map: any = {};
    this.data.commandes.forEach((c: Commande) => {
      if (!map[c.ville]) map[c.ville] = 0;
      map[c.ville]++;
    });
    return map;
  }

  getPerformanceAgents() {
    const map: any = {};
    this.data.commandes.forEach((c: Commande) => {
      if (!c.agent) return;
      if (!map[c.agent]) map[c.agent] = { total: 0, confirmees: 0, livrees: 0 };
      map[c.agent].total++;
      if (c.confirmation === 'Confirmée') map[c.agent].confirmees++;
      if (c.livraison === 'Livrée') map[c.agent].livrees++;
    });
    return map;
  }

  // ---- SAMPLE DATA ----
  private getSampleCommandes(): Commande[] {
    const confirmations = ['Confirmée', 'Pas intéressé', 'Pas de réponse', 'Injoignable', 'Confirmée', 'Confirmée'];
    const livraisons = ['Livrée', 'Expédiée', 'Retour', 'Annulée', 'Payée', 'En attente'];
    const villes = ['Casablanca', 'Rabat', 'Marrakech', 'Fès', 'Tanger', 'Agadir'];
    const produits = ['Montre Luxe', 'Sac Cuir', 'Chaussures Sport', 'Parfum Elite', 'Hijab Premium'];
    const agents = ['Agent1', 'Agent2', 'Agent3'];
    const result: Commande[] = [];
    for (let i = 0; i < 40; i++) {
      const conf = confirmations[Math.floor(Math.random() * confirmations.length)];
      const livr = conf === 'Confirmée' ? livraisons[Math.floor(Math.random() * livraisons.length)] : '';
      result.push({
        id: i + 1,
        date: new Date(2024, Math.floor(Math.random() * 12), Math.floor(Math.random() * 28) + 1).toISOString().split('T')[0],
        designation: produits[Math.floor(Math.random() * produits.length)],
        client: `Client ${i + 1}`,
        telephone: `06${Math.floor(Math.random() * 90000000) + 10000000}`,
        adresse: `Adresse ${i + 1}`,
        ville: villes[Math.floor(Math.random() * villes.length)],
        quantite: Math.floor(Math.random() * 3) + 1,
        prixVenteTotal: (Math.floor(Math.random() * 10) + 1) * 100,
        typeCde: Math.random() > 0.5 ? 'WhatsApp' : 'Site web',
        agent: agents[Math.floor(Math.random() * agents.length)],
        confirmation: conf,
        livraison: livr,
        ref: `REF${String(i + 1).padStart(3, '0')}`,
        commentaire: '',
        fraisLivraison: 30,
        whatsap: Math.random() > 0.5 ? 'Oui' : 'Non',
      });
    }
    return result;
  }

  private getSampleProduits(): Produit[] {
    return [
      { id: 1, reference: 'P001', designation: 'Montre Luxe', categorie: 'Accessoires', prixAchat: 200, prixVente: 450, prixVente2: 850, prixVente3: 1200, prixVente4: 1550, prixVente5: 1900, stock: 25 },
      { id: 2, reference: 'P002', designation: 'Sac Cuir', categorie: 'Mode', prixAchat: 150, prixVente: 350, prixVente2: 680, prixVente3: 990, prixVente4: 1280, prixVente5: 1550, stock: 18 },
      { id: 3, reference: 'P003', designation: 'Chaussures Sport', categorie: 'Sport', prixAchat: 180, prixVente: 399, prixVente2: 760, prixVente3: 1100, prixVente4: 1420, prixVente5: 1700, stock: 30 },
      { id: 4, reference: 'P004', designation: 'Parfum Elite', categorie: 'Beauté', prixAchat: 90, prixVente: 220, prixVente2: 420, prixVente3: 600, prixVente4: 760, prixVente5: 900, stock: 50 },
      { id: 5, reference: 'P005', designation: 'Hijab Premium', categorie: 'Mode', prixAchat: 40, prixVente: 99, prixVente2: 190, prixVente3: 270, prixVente4: 340, prixVente5: 400, stock: 100 },
    ];
  }

  private getSampleAds(): Ads[] {
    return [
      { id: 1, numeroCampagne: 'CPG001', date: '2024-01-15', produit: 'Montre Luxe', objetPublicitaire: 'Ventes', admin: 'Admin1', dureeJours: 7, montantDollars: 50, montantDH: 500, resultat: 'Bon', evaluation: '⭐⭐⭐⭐', totalDollars: 50, totalDH: 500, prospect: 120 },
      { id: 2, numeroCampagne: 'CPG002', date: '2024-02-01', produit: 'Sac Cuir', objetPublicitaire: 'Trafic', admin: 'Admin2', dureeJours: 14, montantDollars: 80, montantDH: 800, resultat: 'Moyen', evaluation: '⭐⭐⭐', totalDollars: 80, totalDH: 800, prospect: 80 },
    ];
  }
}
  */