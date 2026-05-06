import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { DataService } from '../../services/data.service';
import { Commande, DataConfig } from '../../models/commande.model';

@Component({
  selector: 'app-commandes',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './commandes.component.html',
  styleUrls: ['./commandes.component.scss']
})
export class CommandesComponent implements OnInit {
  commandes: Commande[] = [];
  filtered:  Commande[] = [];
  tiers:     any[]      = [];
  tiersFiltered: any[]  = [];
  loading    = true;
  config:    DataConfig = this.defaultConfig();
  search      = '';
  filterConf  = '';
  filterLivr  = '';
  filterAgent = '';
  filterTiers = '';
  filterSource = '';
  showModal   = false;
  editMode    = false;
  form: Commande = this.emptyForm();
  errors: any    = {};

  tiersSearch          = '';
  showTiersSuggestions = false;
  tiersSelectedNom     = '';

  showLivraisonModal   = false;
  commandeToLivraison: Commande | null = null;
  livraisonForm: any   = {};
  livraisonErrors: any = {};
  statuts      = ['En transit', 'Livré', 'Échec livraison', 'Retourné'];
  transporteurs = ['Amana', 'Aramex', 'DHL', 'CTM', 'Autre'];

  // ── Email ──────────────────────────────────────────
  sendingEmailId: number | null = null; // id de la commande en cours d'envoi
  // ───────────────────────────────────────────────────

  constructor(private ds: DataService, private cdr: ChangeDetectorRef) {}

  ngOnInit() {
    this.ds.getConfigObs().subscribe({
      next: (cfg: any) => { this.config = { ...this.defaultConfig(), ...cfg }; this.cdr.detectChanges(); },
      error: () => {}
    });
    this.ds.getTiersObs().subscribe({
      next: (data) => { this.tiers = data || []; this.tiersFiltered = [...this.tiers]; this.cdr.detectChanges(); },
      error: () => {}
    });
    this.load();
  }

  // ── NOUVELLE MÉTHODE : envoyer email ───────────────
  sendEmail(c: Commande) {
    if (!c.emailClient) {
      alert('❌ Cette commande n\'a pas d\'email client.');
      return;
    }
    if (!confirm(`Envoyer un email de confirmation à ${c.emailClient} ?`)) return;

    this.sendingEmailId = c.id!;
    this.cdr.detectChanges();

    this.ds.sendCommandeEmailObs(c.id!).subscribe({
      next: (res) => {
        alert(`✅ Email envoyé à ${c.emailClient}`);
        this.sendingEmailId = null;
        this.cdr.detectChanges();
      },
      error: (err) => {
        const msg = err?.error?.error || 'Erreur lors de l\'envoi';
        alert(`❌ ${msg}`);
        this.sendingEmailId = null;
        this.cdr.detectChanges();
      }
    });
  }
  // ───────────────────────────────────────────────────

  onTiersSearchChange() {
    const s = this.tiersSearch.toLowerCase();
    this.tiersFiltered = !s ? [...this.tiers]
      : this.tiers.filter(t =>
          (t.nom || '').toLowerCase().includes(s) ||
          (t.referent || '').toLowerCase().includes(s) ||
          (t.telephone || '').includes(s)
        );
    this.showTiersSuggestions = true;
    this.form.tiersId = undefined;
    this.cdr.detectChanges();
  }

  selectTiers(t: any) {
    this.form.tiersId        = t.id;
    this.tiersSearch         = t.nom;
    this.tiersSelectedNom    = t.nom;
    this.showTiersSuggestions = false;
    this.form.client    = t.nom       || '';
    this.form.telephone = t.telephone || '';
    this.form.adresse   = t.adresse   || '';
    this.form.ville     = t.ville     || '';
    this.form.pays      = t.pays      || '';
    this.form.emailClient = t.email   || '';
    this.cdr.detectChanges();
  }

  clearTiers() {
    this.form.tiersId         = undefined;
    this.tiersSearch          = '';
    this.tiersSelectedNom     = '';
    this.showTiersSuggestions = false;
    this.tiersFiltered        = [...this.tiers];
    this.cdr.detectChanges();
  }

  hideSuggestions() {
    setTimeout(() => { this.showTiersSuggestions = false; this.cdr.detectChanges(); }, 200);
  }

  load() {
    this.loading = true;
    this.ds.getCommandesObs().subscribe({
      next: (data) => {
        this.commandes = data || [];
        this.filtered  = [...this.commandes];
        this.applyFilters();
        this.loading   = false;
        this.cdr.detectChanges();
      },
      error: (err) => {
        console.error('❌ Erreur commandes:', err);
        this.commandes = [];
        this.filtered  = [];
        this.loading   = false;
        this.cdr.detectChanges();
      }
    });
  }

  applyFilters() {
    this.filtered = this.commandes.filter(c => {
      const s = this.search.toLowerCase();
      const matchSearch = !s
        || (c.client        || '').toLowerCase().includes(s)
        || (c.designation   || '').toLowerCase().includes(s)
        || (c.telephone     || '').includes(s)
        || (c.ref           || '').toLowerCase().includes(s)
        || (c.tiersNom      || '').toLowerCase().includes(s)
        || (c.emailClient   || '').toLowerCase().includes(s)
        || (c.ville         || '').toLowerCase().includes(s)
        || (c.pays          || '').toLowerCase().includes(s)
        || (c.modePaiement  || '').toLowerCase().includes(s);
      const matchConf   = !this.filterConf   || c.confirmation === this.filterConf;
      const matchLivr   = !this.filterLivr   || c.livraison    === this.filterLivr;
      const matchAgent  = !this.filterAgent  || c.agent        === this.filterAgent;
      const matchTiers  = !this.filterTiers  || String(c.tiersId) === this.filterTiers;
      const matchSource = !this.filterSource || c.source === this.filterSource;
      return matchSearch && matchConf && matchLivr && matchAgent && matchTiers && matchSource;
    });
  }

  validate(): boolean {
    this.errors = {};
    if (!this.form.date?.trim())        this.errors['date']          = 'La date est obligatoire';
    if (!this.form.client?.trim())      this.errors['client']        = 'Le client est obligatoire';
    if (!this.form.designation?.trim()) this.errors['designation']   = 'La désignation est obligatoire';
    if (!this.form.telephone?.trim())   this.errors['telephone']     = 'Le téléphone est obligatoire';
    else if (!/^[0-9+\s]{6,20}$/.test(this.form.telephone))
                                        this.errors['telephone']     = 'Téléphone invalide';
    if (!this.form.ville?.trim())       this.errors['ville']         = 'La ville est obligatoire';
    if (!this.form.quantite || this.form.quantite < 1)
                                        this.errors['quantite']      = 'La quantité doit être au moins 1';
    if (!this.form.prixVenteTotal || this.form.prixVenteTotal <= 0)
                                        this.errors['prixVenteTotal'] = 'Le prix doit être supérieur à 0';
    return Object.keys(this.errors).length === 0;
  }

  openAdd() {
    this.form                 = this.emptyForm();
    this.errors               = {};
    this.editMode             = false;
    this.showModal            = true;
    this.tiersSearch          = '';
    this.tiersSelectedNom     = '';
    this.showTiersSuggestions = false;
    this.tiersFiltered        = [...this.tiers];
  }

  openEdit(c: Commande) {
    this.form                 = { ...c };
    this.errors               = {};
    this.editMode             = true;
    this.showModal            = true;
    this.tiersSearch          = c.tiersNom || '';
    this.tiersSelectedNom     = c.tiersNom || '';
    this.showTiersSuggestions = false;
    this.tiersFiltered        = [...this.tiers];
  }

  closeModal() { this.showModal = false; }

  save() {
    if (!this.validate()) return;
    const obs = this.editMode
      ? this.ds.updateCommandeObs(this.form)
      : this.ds.addCommandeObs(this.form);
    obs.subscribe({
      next: () => { this.load(); this.closeModal(); },
      error: (err) => console.error('❌ save:', err)
    });
  }

  delete(id: number) {
    if (confirm('Supprimer cette commande ?')) {
      this.ds.deleteCommandeObs(id).subscribe({
        next: () => this.load(),
        error: (err) => console.error('❌ delete:', err)
      });
    }
  }

  openLivraisonModal(c: Commande) {
    this.commandeToLivraison = c;
    this.livraisonForm = {
      commandeId:     c.id,
      numeroSuivi:    '',
      transporteur:   '',
      dateExpedition: new Date().toISOString().split('T')[0],
      statut:         'En transit',
      commentaire:    ''
    };
    this.livraisonErrors    = {};
    this.showLivraisonModal = true;
  }

  closeLivraisonModal() {
    this.showLivraisonModal  = false;
    this.commandeToLivraison = null;
  }

  validateLivraison(): boolean {
    this.livraisonErrors = {};
    if (!this.livraisonForm.numeroSuivi?.trim())
      this.livraisonErrors['numeroSuivi']    = 'Le numéro de suivi est obligatoire';
    if (!this.livraisonForm.transporteur)
      this.livraisonErrors['transporteur']   = 'Le transporteur est obligatoire';
    if (!this.livraisonForm.dateExpedition)
      this.livraisonErrors['dateExpedition'] = 'La date est obligatoire';
    return Object.keys(this.livraisonErrors).length === 0;
  }

  sendToLivraison() {
    if (!this.validateLivraison()) return;
    this.ds.addLivraisonObs(this.livraisonForm).subscribe({
      next: () => { this.closeLivraisonModal(); alert('✅ Commande envoyée vers les livraisons !'); },
      error: (err) => console.error('❌ livraison:', err)
    });
  }

  getSourceBadge(source: string): string {
    const map: any = { 'prestashop': 'info', 'woocommerce': 'expediee', 'manuel': 'confirmee' };
    return map[source] || 'confirmee';
  }

  getSourceIcon(source: string): string {
    const map: any = { 'prestashop': '🛒', 'woocommerce': '🛍️', 'manuel': '✏️' };
    return map[source] || '✏️';
  }

  getStatutEcommerceBadge(statut: string): string {
    const map: any = {
      'Terminée':         'livree',
      'En cours':         'expediee',
      'Attente paiement': 'warning',
      'Annulée':          'annulee',
      'Remboursée':       'retour',
      'Confirmée':        'confirmee',
    };
    return map[statut] || 'pas-reponse';
  }

  getBadgeClass(val: string): string {
    const map: any = {
      'Livrée':    'livree',    'Confirmée': 'confirmee',
      'Annulée':   'annulee',   'Retour':    'retour',
      'Expédiée':  'expediee',  'Payée':     'payee'
    };
    return map[val] || 'pas-reponse';
  }

  getVilles(): string[] {
    return (this.config.villes || []).map((v: any) => v.nom || v);
  }

  emptyForm(): Commande {
    return {
      date: new Date().toISOString().split('T')[0],
      designation: '', client: '', telephone: '', adresse: '',
      ville: '', quantite: 1, prixVenteTotal: 0, typeCde: 'Site web',
      agent: '', confirmation: '', livraison: '', ref: '',
      commentaire: '', fraisLivraison: 30, whatsap: '',
      tiersId: undefined, source: 'manuel',
      emailClient: '', pays: '', modePaiement: '', statutEcommerce: ''
    };
  }

  defaultConfig(): DataConfig {
    return {
      statutsConfirmation: ['Confirmée','Pas intéressé','Pas de réponse','Injoignable','Faux numéro','2ème appel'],
      statutsLivraison:    ['Livrée','Expédiée','Retour','Annulée','Payée','En attente'],
      villes: [
        { nom: 'Casablanca', fraisLivraison: 25 }, { nom: 'Rabat',     fraisLivraison: 30 },
        { nom: 'Marrakech',  fraisLivraison: 40 }, { nom: 'Fès',       fraisLivraison: 40 },
        { nom: 'Tanger',     fraisLivraison: 45 }, { nom: 'Agadir',    fraisLivraison: 50 },
      ],
      agents: ['Agent1','Agent2','Agent3'],
      sites: [], admins: [], mois: [], categoriesProduits: [],
      fraisTelephonique: 0, prixParCommandeLivree: 0
    };
  }
}