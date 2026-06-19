import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { DataService } from '../../services/data.service';
import { Produit } from '../../models/commande.model';
import { NgIconComponent } from '@ng-icons/core';

@Component({
  selector: 'app-produits',
  standalone: true,
  imports: [CommonModule, FormsModule,NgIconComponent],
  templateUrl: './produits.component.html',
  styleUrls: ['./produits.component.scss']
})
export class ProduitsComponent implements OnInit {
  produits: Produit[] = [];
  filtered: Produit[] = [];
  config: any = { categoriesProduits: [] };
  search = '';
  filterCat = '';
  showModal = false;
  editMode = false;
  form: Produit = this.emptyForm();
  loading = true;

  constructor(private ds: DataService, private cdr: ChangeDetectorRef) {}

  ngOnInit() {
    this.ds.getConfigObs().subscribe({
      next: (cfg: any) => { this.config = cfg; this.cdr.detectChanges(); },
      error: () => {}
    });
    this.load();
  }

  load() {
    this.loading = true;
    this.ds.getProduitsObs().subscribe({
      next: (data) => {
        this.produits = data || [];
        this.filtered = [...this.produits];
        this.applyFilters();
        this.loading = false;
        this.cdr.detectChanges();
      },
      error: () => { this.loading = false; this.cdr.detectChanges(); }
    });
  }

  applyFilters() {
    this.filtered = this.produits.filter(p => {
      const s = this.search.toLowerCase();
      return (!s || (p.designation || '').toLowerCase().includes(s) || (p.reference || '').toLowerCase().includes(s))
        && (!this.filterCat || p.categorie === this.filterCat);
    });
  }

  openAdd()            { this.form = this.emptyForm(); this.editMode = false; this.showModal = true; }
  openEdit(p: Produit) { this.form = { ...p };         this.editMode = true;  this.showModal = true; }
  closeModal()         { this.showModal = false; }

  save() {
    if (this.editMode) {
      this.ds.updateProduitObs(this.form).subscribe({
        next: () => { this.load(); this.closeModal(); },
        error: (err) => console.error(err)
      });
    } else {
      this.ds.addProduitObs(this.form).subscribe({
        next: () => { this.load(); this.closeModal(); },
        error: (err) => console.error(err)
      });
    }
  }

  delete(id: number) {
    if (confirm('Supprimer ce produit ?')) {
      this.ds.deleteProduitObs(id).subscribe({
        next: () => this.load(),
        error: (err) => console.error(err)
      });
    }
  }

  emptyForm(): Produit {
    return { reference: '', designation: '', categorie: '', prixAchat: 0, prixVente: 0, prixVente2: 0, prixVente3: 0, prixVente4: 0, prixVente5: 0, stock: 0 };
  }

  getMargin(p: Produit): number {
    return p.prixAchat ? Math.round((p.prixVente - p.prixAchat) / p.prixAchat * 100) : 0;
  }
}