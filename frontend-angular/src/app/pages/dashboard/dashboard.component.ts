import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { DataService } from '../../services/data.service';
import { NgIconComponent } from '@ng-icons/core';

@Component({
  selector: 'app-dashboard',
  standalone: true,
  imports: [CommonModule, FormsModule, NgIconComponent],
  templateUrl: './dashboard.component.html',
  styleUrls: ['./dashboard.component.scss']
})
export class DashboardComponent implements OnInit {
  stats: any             = {};
  ventesParMois: any     = {};
  ventesParProduit: any  = {};
  commandesParVille: any = {};
  parSource: any         = {};
  loading                = true;
  config: any            = { statutsLivraison: [] };
  moisLabels             = ['Jan','Fév','Mar','Avr','Mai','Juin','Juil','Août','Sep','Oct','Nov','Déc'];

  filterDateDebut = '';
  filterDateFin   = '';
  filterLivr      = '';

  constructor(private ds: DataService, private cdr: ChangeDetectorRef) {}

  ngOnInit() {
    this.ds.getConfigObs().subscribe({
      next: (cfg: any) => { this.config = cfg; this.cdr.detectChanges(); },
      error: () => {}
    });
    this.loadDashboard();
  }

  loadDashboard() {
    this.loading = true;
    const params: any = {};
    if (this.filterDateDebut) params['dateDebut'] = this.filterDateDebut;
    if (this.filterDateFin)   params['dateFin']   = this.filterDateFin;
    if (this.filterLivr)      params['livraison'] = this.filterLivr;

    this.ds.getDashboardStats(params).subscribe({
      next: (data: any) => {
        this.stats             = data.stats            || {};
        this.ventesParMois     = data.ventesParMois    || {};
        this.ventesParProduit  = data.ventesParProduit || {};
        this.commandesParVille = data.parVille         || {};
        this.parSource         = data.parSource        || {};
        this.loading           = false;
        this.cdr.detectChanges();
      },
      error: (err) => {
        console.error('Dashboard error:', err);
        this.loading = false;
        this.cdr.detectChanges();
      }
    });
  }

  applyFilters() { this.loadDashboard(); }

  resetFilters() {
    this.filterDateDebut = '';
    this.filterDateFin   = '';
    this.filterLivr      = '';
    this.loadDashboard();
  }

  objectKeys(obj: any): string[] { return Object.keys(obj || {}); }

  moisLabel(m: string): string {
    return this.moisLabels[parseInt(m) - 1] ?? m;
  }

  formatNumber(n: number): string {
    if (!n) return '0';
    if (n >= 1000000) return (n / 1000000).toFixed(1) + 'M';
    if (n >= 1000)    return (n / 1000).toFixed(1) + 'K';
    return Math.round(n).toString();
  }

  getMoisChartData(): any[] {
    const keys = Object.keys(this.ventesParMois || {});
    if (!keys.length) return [];
    const maxVal = Math.max(...keys.map(k => this.ventesParMois[k].commandes || 0), 1);
    return keys.map(k => {
      const d = this.ventesParMois[k];
      return {
        label:           this.moisLabels[parseInt(k) - 1] ?? k,
        commandes:       d.commandes || 0,
        livrees:         d.livrees   || 0,
        heightCommandes: ((d.commandes || 0) / maxVal) * 140,
        heightLivrees:   ((d.livrees   || 0) / maxVal) * 140,
      };
    });
  }

  getMaxDesignation(): number {
    const vals = Object.values(this.ventesParProduit || {}) as any[];
    return Math.max(...vals.map((p: any) => p.commandes || 0), 1);
  }

  getSourceColor(source: string): string {
    const colors: any = {
      'prestashop':  '#DF0067',
      'woocommerce': '#96588A',
      'shopify':     '#96BF48',
      'manuel':      '#2563eb',
    };
    return colors[source] ?? '#94a3b8';
  }

  getSourceLabel(source: string): string {
    const labels: any = {
      'prestashop':  'PrestaShop',
      'woocommerce': 'WooCommerce',
      'shopify':     'Shopify',
      'manuel':      'Manuel',
    };
    return labels[source] ?? source;
  }

  getTotalSource(): number {
    return Object.values(this.parSource || {}).reduce((a: any, b: any) => a + b, 0) as number;
  }
}