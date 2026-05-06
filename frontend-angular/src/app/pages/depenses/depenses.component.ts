import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { DataService } from '../../services/data.service';
import { Depense } from '../../models/commande.model';

interface CategorieItem { categorie: string; montant: number; }

@Component({
  selector: 'app-depenses',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './depenses.component.html',
  styleUrls: ['./depenses.component.scss']
})
export class DepensesComponent implements OnInit {
  depenses: Depense[] = [];
  categories = ['Logistique', 'Marketing', 'Salaires', 'Telephonie', 'Autre'];
  showModal = false;
  editMode = false;
  form: Depense = this.empty();
  loading = true;

  constructor(private ds: DataService, private cdr: ChangeDetectorRef) {}

  ngOnInit() { this.load(); }

  load() {
    this.loading = true;
    this.ds.getDepensesObs().subscribe({
      next: (data) => {
        this.depenses = data || [];
        this.loading = false;
        this.cdr.detectChanges();
      },
      error: () => { this.loading = false; this.cdr.detectChanges(); }
    });
  }

  get totalDepenses(): number { return this.depenses.reduce((s, d) => s + d.montant, 0); }

  openAdd()            { this.form = this.empty(); this.editMode = false; this.showModal = true; }
  openEdit(d: Depense) { this.form = { ...d };     this.editMode = true;  this.showModal = true; }
  closeModal()         { this.showModal = false; }

  save() {
    if (this.editMode) {
      this.ds.updateDepenseObs(this.form).subscribe({
        next: () => { this.load(); this.closeModal(); },
        error: (err) => console.error(err)
      });
    } else {
      this.ds.addDepenseObs(this.form).subscribe({
        next: () => { this.load(); this.closeModal(); },
        error: (err) => console.error(err)
      });
    }
  }

  delete(id: number) {
    if (confirm('Supprimer cette dépense ?')) {
      this.ds.deleteDepenseObs(id).subscribe({
        next: () => this.load(),
        error: (err) => console.error(err)
      });
    }
  }

  empty(): Depense {
    return { date: new Date().toISOString().split('T')[0], designation: '', montant: 0, categorie: '', commentaire: '' };
  }

  byCategorie(): CategorieItem[] {
    const map: { [key: string]: number } = {};
    this.depenses.forEach(d => {
      if (!map[d.categorie]) map[d.categorie] = 0;
      map[d.categorie] += d.montant;
    });
    return Object.entries(map).map(([categorie, montant]) => ({ categorie, montant }));
  }
}