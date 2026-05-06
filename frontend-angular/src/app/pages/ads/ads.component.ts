import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { DataService } from '../../services/data.service';
import { Ads } from '../../models/commande.model';

@Component({
  selector: 'app-ads',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './ads.component.html',
  styleUrls: ['./ads.component.scss']
})
export class AdsComponent implements OnInit {
  ads: Ads[] = [];
  config: any = { admins: [] };
  showModal = false;
  editMode = false;
  form: Ads = this.empty();
  loading = true;
  objectifs   = ['Ventes', 'Trafic', 'Notoriété', 'Leads', 'Engagement'];
  evaluations = ['⭐', '⭐⭐', '⭐⭐⭐', '⭐⭐⭐⭐', '⭐⭐⭐⭐⭐'];

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
    this.ds.getAdsObs().subscribe({
      next: (data) => {
        this.ads = data || [];
        this.loading = false;
        this.cdr.detectChanges();
      },
      error: () => { this.loading = false; this.cdr.detectChanges(); }
    });
  }

  get totalDH(): number        { return this.ads.reduce((s, a) => s + (a.totalDH || 0), 0); }
  get totalProspects(): number  { return this.ads.reduce((s, a) => s + (a.prospect || 0), 0); }
  get coutParProspect(): number { return this.totalProspects ? Math.round(this.totalDH / this.totalProspects) : 0; }

  openAdd()        { this.form = this.empty(); this.editMode = false; this.showModal = true; }
  openEdit(a: Ads) { this.form = { ...a };     this.editMode = true;  this.showModal = true; }
  closeModal()     { this.showModal = false; }

  save() {
    if (this.editMode) {
      this.ds.updateAdsObs(this.form).subscribe({
        next: () => { this.load(); this.closeModal(); },
        error: (err) => console.error(err)
      });
    } else {
      this.ds.addAdsObs(this.form).subscribe({
        next: () => { this.load(); this.closeModal(); },
        error: (err) => console.error(err)
      });
    }
  }

  delete(id: number) {
    if (confirm('Supprimer ?')) {
      this.ds.deleteAdsObs(id).subscribe({
        next: () => this.load(),
        error: (err) => console.error(err)
      });
    }
  }

  empty(): Ads {
    return {
      numeroCampagne: '', date: new Date().toISOString().split('T')[0],
      produit: '', objetPublicitaire: '', admin: '', dureeJours: 7,
      montantDollars: 0, montantDH: 0, resultat: '', evaluation: '',
      totalDollars: 0, totalDH: 0, prospect: 0
    };
  }
}