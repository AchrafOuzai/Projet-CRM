import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { DataService } from '../../services/data.service';
import { NgIconComponent } from '@ng-icons/core';

interface Livraison {
  id?: number;
  numeroSuivi: string;
  transporteur: string;
  dateExpedition: string;
  statut: string;
  commentaire: string;
  commandeId?: number;
  commandeRef?: string;
  commandeClient?: string;
}

@Component({
  selector: 'app-livraison',
  standalone: true,
  imports: [CommonModule, FormsModule, NgIconComponent],
  templateUrl: './livraison.component.html',
  styleUrls: ['./livraison.component.scss']
})
export class LivraisonComponent implements OnInit {
  livraisons:  Livraison[] = [];
  commandes:   any[]       = [];
  commandesFiltered: any[] = [];
  commandesPage    = 1;
  commandesTotal   = 0;
  commandesPages   = 1;
  commandesSearch  = '';

  loading    = true;
  showModal  = false;
  editMode   = false;
  form: Livraison = this.empty();
  errors: any     = {};

  page    = 1;
  pages   = 1;
  total   = 0;
  search  = '';
  filterStatut = '';

  statuts       = ['En transit', 'Livré', 'Échec livraison', 'Retourné'];
  transporteurs = ['Amana', 'Aramex', 'DHL', 'CTM', 'Autre'];
  commandesPageSize = 10;

  constructor(private ds: DataService, private cdr: ChangeDetectorRef) {}

  ngOnInit() { this.load(); this.loadCommandes(); }

  load() {
    this.loading = true;
    this.ds.getLivraisonsObs(this.page, this.search, this.filterStatut).subscribe({
      next: (res) => {
        this.livraisons = res.data  || [];
        this.total      = res.total || 0;
        this.pages      = res.pages || 1;
        this.loading    = false;
        this.cdr.detectChanges();
      },
      error: () => { this.loading = false; this.cdr.detectChanges(); }
    });
  }

  loadCommandes() {
    this.ds.getCommandesObs().subscribe({
      next: (data) => {
        this.commandes      = data || [];
        this.commandesTotal = this.commandes.length;
        this.commandesPages = Math.ceil(this.commandesTotal / this.commandesPageSize);
        this.applyCommandesPagination();
        this.cdr.detectChanges();
      },
      error: () => {}
    });
  }

  applyCommandesPagination() {
    let filtered = this.commandes;
    if (this.commandesSearch) {
      const s = this.commandesSearch.toLowerCase();
      filtered = this.commandes.filter(c =>
        (c.ref    || '').toLowerCase().includes(s) ||
        (c.client || '').toLowerCase().includes(s)
      );
    }
    const start = (this.commandesPage - 1) * this.commandesPageSize;
    this.commandesFiltered = filtered.slice(start, start + this.commandesPageSize);
    this.commandesPages    = Math.ceil(filtered.length / this.commandesPageSize);
  }

  prevCommandesPage() {
    if (this.commandesPage > 1) { this.commandesPage--; this.applyCommandesPagination(); }
  }
  nextCommandesPage() {
    if (this.commandesPage < this.commandesPages) { this.commandesPage++; this.applyCommandesPagination(); }
  }

  onSearch() { this.page = 1; this.load(); }
  prevPage() { if (this.page > 1)          { this.page--; this.load(); } }
  nextPage() { if (this.page < this.pages) { this.page++; this.load(); } }

  validate(): boolean {
    this.errors = {};
    if (!this.form.numeroSuivi?.trim())   this.errors['numeroSuivi']    = 'Le numéro de suivi est obligatoire';
    if (!this.form.transporteur?.trim())  this.errors['transporteur']   = 'Le transporteur est obligatoire';
    if (!this.form.dateExpedition)        this.errors['dateExpedition'] = "La date d'expédition est obligatoire";
    if (!this.form.statut)                this.errors['statut']         = 'Le statut est obligatoire';
    return Object.keys(this.errors).length === 0;
  }

  openAdd() {
    this.form            = this.empty();
    this.errors          = {};
    this.editMode        = false;
    this.showModal       = true;
    this.commandesPage   = 1;
    this.commandesSearch = '';
    this.applyCommandesPagination();
  }

  openEdit(l: Livraison) {
    this.form            = { ...l };
    this.errors          = {};
    this.editMode        = true;
    this.showModal       = true;
    this.commandesPage   = 1;
    this.commandesSearch = '';
    this.applyCommandesPagination();
  }

  closeModal() { this.showModal = false; }

  save() {
    if (!this.validate()) return;
    const obs = this.editMode
      ? this.ds.updateLivraisonObs(this.form)
      : this.ds.addLivraisonObs(this.form);
    obs.subscribe({
      next: () => { this.load(); this.closeModal(); },
      error: (err) => console.error(err)
    });
  }

  delete(id: number) {
    if (confirm('Supprimer cette livraison ?')) {
      this.ds.deleteLivraisonObs(id).subscribe({
        next: () => this.load(),
        error: (err) => console.error(err)
      });
    }
  }

  // ── EXPORT CSV ──────────────────────────────────────
  exportCSV() {
    const headers = [
      'N° Suivi', 'Transporteur', 'Date Expédition',
      'Statut', 'Ref Commande', 'Client', 'Commentaire'
    ];
    const rows = this.livraisons.map(l => [
      l.numeroSuivi    || '',
      l.transporteur   || '',
      l.dateExpedition || '',
      l.statut         || '',
      l.commandeRef    || '',
      l.commandeClient || '',
      l.commentaire    || ''
    ]);
    const csv = [headers, ...rows]
      .map(r => r.map(v => `"${String(v).replace(/"/g, '""')}"`).join(';'))
      .join('\n');
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = `livraisons_${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
    URL.revokeObjectURL(url);
  }
  // ────────────────────────────────────────────────────

  getStatutClass(statut: string): string {
    const map: any = {
      'En transit': 'expediee', 'Livré': 'livree',
      'Échec livraison': 'annulee', 'Retourné': 'retour'
    };
    return map[statut] || 'pas-reponse';
  }

  getPages(): number[] { return Array.from({ length: this.pages }, (_, i) => i + 1); }
  goToPage(p: number)  { this.page = p; this.load(); }

  empty(): Livraison {
    return {
      numeroSuivi: '', transporteur: '',
      dateExpedition: new Date().toISOString().split('T')[0],
      statut: 'En transit', commentaire: '', commandeId: undefined
    };
  }
}