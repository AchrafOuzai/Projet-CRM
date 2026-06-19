import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { DataService } from '../../services/data.service';
import { DataConfig } from '../../models/commande.model';
import { NgIconComponent } from '@ng-icons/core';

@Component({
  selector: 'app-data',
  standalone: true,
  imports: [CommonModule, FormsModule,NgIconComponent],
  templateUrl: './data.component.html',
  styleUrls: ['./data.component.scss']
})
export class DataComponent implements OnInit {
  config!: DataConfig;
  activeTab = 0;
  newVille = ''; newVilleFrais = 0;
  newAgent = ''; newSite = ''; newAdmin = ''; newCategorie = '';
  saved = false; loading = true;

  constructor(private ds: DataService) {}

  ngOnInit() {
    this.ds.getConfigObs().subscribe({
      next: (cfg: any) => {
        this.config = {
          categoriesProduits: cfg.categoriesProduits || [],
          statutsConfirmation: cfg.statutsConfirmation || [],
          statutsLivraison: cfg.statutsLivraison || [],
          villes: cfg.villes || [],
          agents: cfg.agents || [],
          sites: cfg.sites || [],
          admins: cfg.admins || [],
          mois: cfg.mois || [],
          fraisTelephonique: cfg.fraisTelephonique || 0,
          prixParCommandeLivree: cfg.prixParCommandeLivree || 0,
        };
        this.loading = false;
      },
      error: () => { this.loading = false; }
    });
  }

  save() {
    this.ds.saveConfigObs(this.config).subscribe({
      next: () => { this.saved = true; setTimeout(() => this.saved = false, 2000); },
      error: () => {}
    });
  }

  addVille()     { if (this.newVille.trim())     { this.config.villes.push({ nom: this.newVille.trim(), fraisLivraison: this.newVilleFrais }); this.newVille = ''; this.newVilleFrais = 0; } }
  removeVille(i: number)    { this.config.villes.splice(i, 1); }
  addAgent()     { if (this.newAgent.trim())     { this.config.agents.push(this.newAgent.trim()); this.newAgent = ''; } }
  removeAgent(i: number)    { this.config.agents.splice(i, 1); }
  addSite()      { if (this.newSite.trim())      { this.config.sites.push(this.newSite.trim()); this.newSite = ''; } }
  removeSite(i: number)     { this.config.sites.splice(i, 1); }
  addAdmin()     { if (this.newAdmin.trim())     { this.config.admins.push(this.newAdmin.trim()); this.newAdmin = ''; } }
  removeAdmin(i: number)    { this.config.admins.splice(i, 1); }
  addCategorie() { if (this.newCategorie.trim()) { this.config.categoriesProduits.push(this.newCategorie.trim()); this.newCategorie = ''; } }
  removeCategorie(i: number){ this.config.categoriesProduits.splice(i, 1); }
}