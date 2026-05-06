import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { DataService } from '../../services/data.service';

@Component({
  selector: 'app-retours',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './retours.component.html',
  styleUrls: ['./retours.component.scss']
})
export class RetoursComponent implements OnInit {
  retours:  any[] = [];
  filtered: any[] = [];
  loading         = true;
  syncing: any    = { prestashop: false, woocommerce: false, shopify: false };
  syncResults: any = {};

  search       = '';
  filterSource = '';
  filterStatut = '';

  showModal = false;
  editMode  = false;
  form: any = {};
  errors: any = {};

  statuts  = ['En attente', 'Remboursé', 'Refusé'];
  sources  = ['manuel', 'prestashop', 'woocommerce', 'shopify'];

  constructor(private ds: DataService, private cdr: ChangeDetectorRef) {}

  ngOnInit() { this.load(); }

  load() {
    this.loading = true;
    this.ds.getRetoursObs().subscribe({
      next: (data) => {
        this.retours = data || [];
        this.applyFilters();
        this.loading = false;
        this.cdr.detectChanges();
      },
      error: () => { this.loading = false; this.cdr.detectChanges(); }
    });
  }

  applyFilters() {
    this.filtered = this.retours.filter(r => {
      const s = this.search.toLowerCase();
      const matchSearch = !s
        || (r.client      || '').toLowerCase().includes(s)
        || (r.ref         || '').toLowerCase().includes(s)
        || (r.refCommande || '').toLowerCase().includes(s)
        || (r.emailClient || '').toLowerCase().includes(s)
        || (r.tiersNom    || '').toLowerCase().includes(s)
        || (r.motif       || '').toLowerCase().includes(s);
      const matchSource = !this.filterSource || r.source === this.filterSource;
      const matchStatut = !this.filterStatut || r.statut === this.filterStatut;
      return matchSearch && matchSource && matchStatut;
    });
  }

  syncRetours(type: string) {
    this.syncing[type]    = true;
    this.syncResults[type] = null;
    this.ds.syncEcommerceRetours(type).subscribe({
      next: (res) => {
        this.syncing[type]     = false;
        this.syncResults[type] = res;
        this.load();
        this.cdr.detectChanges();
      },
      error: () => { this.syncing[type] = false; this.cdr.detectChanges(); }
    });
  }

  openAdd() {
    this.form     = { source: 'manuel', statut: 'En attente',
                      date: new Date().toISOString().split('T')[0] };
    this.errors   = {};
    this.editMode = false;
    this.showModal = true;
  }

  openEdit(r: any) {
    this.form      = { ...r };
    this.errors    = {};
    this.editMode  = true;
    this.showModal = true;
  }

  closeModal() { this.showModal = false; }

  save() {
    this.errors = {};
    if (!this.form.client?.trim()) this.errors['client'] = 'Client obligatoire';
    if (Object.keys(this.errors).length > 0) return;

    const obs = this.editMode
      ? this.ds.updateRetourObs(this.form)
      : this.ds.addRetourObs(this.form);

    obs.subscribe({
      next: () => { this.load(); this.closeModal(); },
      error: (err) => console.error(err)
    });
  }

  delete(id: number) {
    if (confirm('Supprimer ce retour ?')) {
      this.ds.deleteRetourObs(id).subscribe({
        next: () => this.load(),
        error: (err) => console.error(err)
      });
    }
  }

  getTotalRembourse(): number {
    return this.retours.reduce((s, r) => s + (r.montantRembourse || 0), 0);
  }

  countBySource(source: string): number {
    return this.retours.filter(r => r.source === source).length;
  }

  getSourceBadge(source: string): string {
    const map: any = { prestashop: 'info', woocommerce: 'expediee', shopify: 'confirmee', manuel: 'pas-reponse' };
    return map[source] || 'pas-reponse';
  }

  getSourceIcon(source: string): string {
    const map: any = { prestashop: '🛒', woocommerce: '🟣', shopify: '🟢', manuel: '✏️' };
    return map[source] || '✏️';
  }

  getStatutBadge(statut: string): string {
    const map: any = { 'Remboursé': 'livree', 'En attente': 'retour', 'Refusé': 'annulee' };
    return map[statut] || 'pas-reponse';
  }
}