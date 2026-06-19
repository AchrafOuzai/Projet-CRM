import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { DataService } from '../../services/data.service';
import { Produit } from '../../models/commande.model';
import { NgIconComponent } from '@ng-icons/core';

@Component({
  selector: 'app-stock',
  standalone: true,
  imports: [CommonModule, FormsModule,NgIconComponent],
  templateUrl: './stock.component.html',
  styleUrls: ['./stock.component.scss']
})
export class StockComponent implements OnInit {
  produits: Produit[] = [];
  loading = true;

  constructor(private ds: DataService, private cdr: ChangeDetectorRef) {}

  ngOnInit() { this.load(); }

  load() {
    this.loading = true;
    this.ds.getProduitsObs().subscribe({
      next: (data) => {
        this.produits = data || [];
        this.loading = false;
        this.cdr.detectChanges();
      },
      error: () => { this.loading = false; this.cdr.detectChanges(); }
    });
  }

  updateStock(p: Produit) {
    this.ds.updateProduitObs(p).subscribe({
      next: () => this.load(),
      error: (err) => console.error(err)
    });
  }

  getStockStatus(stock: number) {
    if (stock === 0)  return { label: 'Rupture',  class: 'annulee'  };
    if (stock < 5)    return { label: 'Critique', class: 'retour'   };
    if (stock < 15)   return { label: 'Faible',   class: 'expediee' };
    return { label: 'OK', class: 'livree' };
  }

  get totalValeur(): number {
    return this.produits.reduce((s, p) => s + (p.stock || 0) * p.prixAchat, 0);
  }

  countRupture(): number  { return this.produits.filter(p => (p.stock || 0) === 0).length; }
  countCritique(): number { return this.produits.filter(p => (p.stock || 0) > 0 && (p.stock || 0) < 5).length; }
}