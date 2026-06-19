import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { DataService } from '../../services/data.service';
import { Commande } from '../../models/commande.model';
import { NgIconComponent } from '@ng-icons/core';

interface DateRow {
  date: string; commandes: number; livrees: number;
  expediees: number; payees: number; retours: number; whatsap: number;
}

@Component({
  selector: 'app-suivi-commandes',
  standalone: true,
  imports: [CommonModule,NgIconComponent],
  templateUrl: './suivi-commandes.component.html',
  styleUrls: ['./suivi-commandes.component.scss']
})
export class SuiviCommandesComponent implements OnInit {
  all: Commande[] = [];
  confirmees: Commande[] = [];
  stats: any = {};
  loading = true;

  constructor(private ds: DataService, private cdr: ChangeDetectorRef) {}

  ngOnInit() {
    this.ds.getCommandesObs().subscribe({
      next: (data) => {
        this.all = data || [];
        this.confirmees = this.all.filter(c => c.confirmation === 'Confirmée');
        this.computeStats();
        this.loading = false;
        this.cdr.detectChanges();
      },
      error: () => { this.loading = false; this.cdr.detectChanges(); }
    });
  }

  computeStats() {
    const c = this.confirmees;
    const livrees   = c.filter(x => x.livraison === 'Livrée').length;
    const expediees = c.filter(x => x.livraison === 'Expédiée').length;
    const payees    = c.filter(x => x.livraison === 'Payée').length;
    const retours   = c.filter(x => x.livraison === 'Retour').length;
    const whatsap   = c.filter(x => x.whatsap   === 'Oui').length;
    this.stats = {
      total: c.length, livrees, expediees, payees, retours, whatsap,
      tauxLivraison: c.length ? Math.round(livrees / c.length * 100) : 0
    };
  }

  getByDate(): DateRow[] {
    const map: { [key: string]: DateRow } = {};
    this.confirmees.forEach(c => {
      if (!map[c.date]) map[c.date] = { date: c.date, commandes: 0, livrees: 0, expediees: 0, payees: 0, retours: 0, whatsap: 0 };
      map[c.date].commandes++;
      if (c.livraison === 'Livrée')   map[c.date].livrees++;
      if (c.livraison === 'Expédiée') map[c.date].expediees++;
      if (c.livraison === 'Payée')    map[c.date].payees++;
      if (c.livraison === 'Retour')   map[c.date].retours++;
      if (c.whatsap   === 'Oui')      map[c.date].whatsap++;
    });
    return Object.values(map).sort((a, b) => new Date(b.date).getTime() - new Date(a.date).getTime());
  }

  getBadgeClass(val: string): string {
    const map: any = { 'Livrée': 'livree', 'Expédiée': 'expediee', 'Payée': 'payee', 'Retour': 'retour', 'Annulée': 'annulee' };
    return map[val] || 'pas-reponse';
  }
}