import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { Router } from '@angular/router';
import { CommonModule } from '@angular/common';
import { DataService } from '../../services/data.service';

@Component({
  selector: 'app-accueil',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './accueil.component.html',
  styleUrls: ['./accueil.component.scss']
})
export class AccueilComponent implements OnInit {
  stats: any = { total: 0, ca: 0, tauxConfirmation: 0, tauxRetour: 0 };
  loading = true;

  buttons = [
    { label: 'Commandes',        icon: '📦', path: '/commandes',       color: 'var(--accent)',  desc: 'Gérer toutes les commandes' },
    { label: 'Suivi Confirmées', icon: '✅', path: '/suivi-commandes', color: 'var(--success)', desc: 'Suivi des commandes confirmées' },
    { label: 'Produits',         icon: '🛍️', path: '/produits',        color: 'var(--info)',    desc: 'Catalogue produits & prix' },
    { label: 'Stock',            icon: '🗃️', path: '/stock',           color: 'var(--warning)', desc: 'Gestion du stock' },
    { label: 'Dashboard',        icon: '📊', path: '/dashboard',       color: 'var(--accent2)', desc: 'Tableaux de bord & KPIs' },
    { label: 'Dépenses',         icon: '💸', path: '/depenses',        color: '#c084fc',        desc: 'Suivi des dépenses' },
    { label: 'Publicités',       icon: '📣', path: '/ads',             color: '#fb923c',        desc: 'Campagnes publicitaires' },
    { label: 'Data',             icon: '⚙️', path: '/data',            color: '#94a3b8',        desc: 'Configuration système' },
  ];

  constructor(private router: Router, public ds: DataService, private cdr: ChangeDetectorRef) {}

  ngOnInit() {
    this.ds.getStatsObs().subscribe({
      next: (data) => {
        this.stats = data;
        this.loading = false;
        this.cdr.detectChanges();
      },
      error: () => {
        this.loading = false;
        this.stats = { total: 0, ca: 0, tauxConfirmation: 0, tauxRetour: 0 };
        this.cdr.detectChanges();
      }
    });
  }

  navigate(path: string) { this.router.navigate([path]); }
  logout() { this.ds.logout(); this.router.navigate(['/login']); }
}