import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { DataService } from '../../services/data.service';
import { Commande, DataConfig } from '../../models/commande.model';
import { NgIconComponent } from '@ng-icons/core';

@Component({
  selector: 'app-commandes',
  standalone: true,
  imports: [CommonModule, FormsModule, NgIconComponent],
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
  search       = '';
  filterConf   = '';
  filterLivr   = '';
  filterTiers  = '';
  filterSource = '';
  showModal    = false;
  editMode     = false;
  form: Commande = this.emptyForm();
  errors: any    = {};

  tiersSearch          = '';
  showTiersSuggestions = false;
  tiersSelectedNom     = '';

  showLivraisonModal   = false;
  commandeToLivraison: Commande | null = null;
  livraisonForm: any   = {};
  livraisonErrors: any = {};
  statuts       = ['En transit', 'Livré', 'Échec livraison', 'Retourné'];
  transporteurs = ['Amana', 'Aramex', 'DHL', 'CTM', 'Autre'];

  sendingEmailId: number | null = null;

  // ✅ Modal email
  showEmailModal    = false;
  commandeForEmail: any = null;
  emailResult: 'success' | 'error' | null = null;

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

  // ✅ Ouvre le modal email
  openEmailModal(c: Commande) {
    this.commandeForEmail = c;
    this.emailResult      = null;
    this.showEmailModal   = true;
  }

  closeEmailModal() {
    this.showEmailModal   = false;
    this.commandeForEmail = null;
    this.emailResult      = null;
  }

  // ✅ Confirme l'envoi depuis le modal
  confirmSendEmail() {
    if (!this.commandeForEmail?.id) return;
    this.sendingEmailId = this.commandeForEmail.id;
    this.cdr.detectChanges();
    this.ds.sendCommandeEmailObs(this.commandeForEmail.id).subscribe({
      next: () => {
        this.emailResult    = 'success';
        this.sendingEmailId = null;
        this.cdr.detectChanges();
      },
      error: () => {
        this.emailResult    = 'error';
        this.sendingEmailId = null;
        this.cdr.detectChanges();
      }
    });
  }

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
    this.form.tiersId         = t.id;
    this.tiersSearch          = t.nom;
    this.tiersSelectedNom     = t.nom;
    this.showTiersSuggestions = false;
    this.form.client          = t.nom       || '';
    this.form.telephone       = t.telephone || '';
    this.form.adresse         = t.adresse   || '';
    this.form.ville           = t.ville     || '';
    this.form.pays            = t.pays      || '';
    this.form.emailClient     = t.email     || '';
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
        || (c.client       || '').toLowerCase().includes(s)
        || (c.designation  || '').toLowerCase().includes(s)
        || (c.telephone    || '').includes(s)
        || (c.ref          || '').toLowerCase().includes(s)
        || (c.tiersNom     || '').toLowerCase().includes(s)
        || (c.emailClient  || '').toLowerCase().includes(s)
        || (c.ville        || '').toLowerCase().includes(s)
        || (c.pays         || '').toLowerCase().includes(s)
        || (c.modePaiement || '').toLowerCase().includes(s);
      const matchConf   = !this.filterConf   || c.confirmation === this.filterConf;
      const matchLivr   = !this.filterLivr   || c.livraison    === this.filterLivr;
      const matchTiers  = !this.filterTiers  || String(c.tiersId) === this.filterTiers;
      const matchSource = !this.filterSource || c.source === this.filterSource;
      return matchSearch && matchConf && matchLivr && matchTiers && matchSource;
    });
  }

  validate(): boolean {
    this.errors = {};
    if (!this.form.date?.trim())        this.errors['date']           = 'La date est obligatoire';
    if (!this.form.client?.trim())      this.errors['client']         = 'Le client est obligatoire';
    if (!this.form.designation?.trim()) this.errors['designation']    = 'La désignation est obligatoire';
    if (!this.form.telephone?.trim())   this.errors['telephone']      = 'Le téléphone est obligatoire';
    else if (!/^[0-9+\s]{6,20}$/.test(this.form.telephone))
                                        this.errors['telephone']      = 'Téléphone invalide';
    if (!this.form.ville?.trim())       this.errors['ville']          = 'La ville est obligatoire';
    if (!this.form.quantite || this.form.quantite < 1)
                                        this.errors['quantite']       = 'La quantité doit être au moins 1';
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
      next: () => { this.closeLivraisonModal(); },
      error: (err) => console.error('❌ livraison:', err)
    });
  }

  // ✅ Heroicons au lieu d'emojis
  getSourceIcon(source: string): string {
    const map: any = {
      'prestashop':  'heroShoppingCart',
      'woocommerce': 'heroShoppingBag',
      'shopify':     'heroGlobeAlt',
      'manuel':      'heroPencilSquare'
    };
    return map[source] || 'heroPencilSquare';
  }

  getSourceBadge(source: string): string {
    const map: any = {
      'prestashop':  'info',
      'woocommerce': 'expediee',
      'shopify':     'payee',
      'manuel':      'confirmee'
    };
    return map[source] || 'confirmee';
  }

  getStatutEcommerceBadge(statut: string): string {
    const map: any = {
      'Terminée': 'livree', 'En cours': 'expediee',
      'Attente paiement': 'warning', 'Annulée': 'annulee',
      'Remboursée': 'retour', 'Confirmée': 'confirmee',
    };
    return map[statut] || 'pas-reponse';
  }

  getBadgeClass(val: string): string {
    const map: any = {
      'Livrée': 'livree', 'Confirmée': 'confirmee',
      'Annulée': 'annulee', 'Retour': 'retour',
      'Expédiée': 'expediee', 'Payée': 'payee'
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
      confirmation: '', livraison: '', ref: '',
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
        { nom: 'Casablanca', fraisLivraison: 25 }, { nom: 'Rabat',   fraisLivraison: 30 },
        { nom: 'Marrakech',  fraisLivraison: 40 }, { nom: 'Fès',     fraisLivraison: 40 },
        { nom: 'Tanger',     fraisLivraison: 45 }, { nom: 'Agadir',  fraisLivraison: 50 },
      ],
      agents: [], sites: [], admins: [], mois: [], categoriesProduits: [],
      fraisTelephonique: 0, prixParCommandeLivree: 0
    };
  }

  exportCSV() {
    const headers = [
      'Date','Ref','Source','Tiers','Désignation','Client','Email',
      'Téléphone','Ville','Pays','Quantité','Prix Total','Frais Livraison',
      'Mode Paiement','Statut E-commerce','Confirmation','Livraison'
    ];
    const rows = this.filtered.map(c => [
      c.date || '', c.ref || '', c.source || '', c.tiersNom || '',
      c.designation || '', c.client || '', c.emailClient || '',
      c.telephone || '', c.ville || '', c.pays || '',
      c.quantite || 0, c.prixVenteTotal || 0, c.fraisLivraison || 0,
      c.modePaiement || '', c.statutEcommerce || '',
      c.confirmation || '', c.livraison || ''
    ]);
    const csvContent = [headers, ...rows]
      .map(row => row.map(v => `"${String(v).replace(/"/g, '""')}"`).join(';'))
      .join('\n');
    const blob = new Blob(['\uFEFF' + csvContent], { type: 'text/csv;charset=utf-8;' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = `commandes_${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
    URL.revokeObjectURL(url);
  }
}