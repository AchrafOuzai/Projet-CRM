import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { DataService } from '../../services/data.service';

@Component({
  selector: 'app-dashboard',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './dashboard.component.html',
  styleUrls: ['./dashboard.component.scss']
})
export class DashboardComponent implements OnInit {
  stats: any = {};
  ventesParMois: any = {};
  ventesParProduit: any = {};
  commandesParVille: any = {};
  performanceAgents: any = {};
  loading = true;
  config: any = { agents: [], statutsConfirmation: [], statutsLivraison: [] };
  moisLabels = ['Jan','Fév','Mar','Avr','Mai','Juin','Juil','Août','Sep','Oct','Nov','Déc'];

  // ← these were missing
  filterDateDebut = '';
  filterDateFin = '';
  filterAgent = '';
  filterConf = '';
  filterLivr = '';

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
    this.ds.getDashboardStats().subscribe({
      next: (data: any) => {
        this.stats             = data.stats            || {};
        this.ventesParMois     = data.ventesParMois    || {};
        this.ventesParProduit  = data.ventesParProduit || {};
        this.commandesParVille = data.parVille         || {};
        this.performanceAgents = data.agents           || {};
        this.loading = false;
        this.cdr.detectChanges();
      },
      error: () => { this.loading = false; this.cdr.detectChanges(); }
    });
  }

  applyFilters() {
    this.loadDashboard();
  }

  objectKeys(obj: any): string[] { return Object.keys(obj || {}); }
  moisLabel(m: string): string   { return this.moisLabels[parseInt(m)]; }
}