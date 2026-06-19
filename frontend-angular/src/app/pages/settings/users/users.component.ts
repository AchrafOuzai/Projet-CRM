import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { DataService } from '../../../services/data.service';
import { NgIconComponent } from '@ng-icons/core';

@Component({
  selector: 'app-users',
  standalone: true,
  imports: [CommonModule, FormsModule, NgIconComponent],
  templateUrl: './users.component.html'
})
export class UsersComponent implements OnInit {
  users:     any[] = [];
  loading  = true;
  showModal  = false;
  editMode   = false;
  selectedUser: any = null;
  errors: any = {};

  // Permissions disponibles
  allPermissions = [
    { key: 'tiers',      label: 'Tiers & Contacts',  icon: 'heroBuildingOffice2' },
    { key: 'commandes',  label: 'Commandes',          icon: 'heroCube' },
    { key: 'livraisons', label: 'Livraisons',         icon: 'heroTruck' },
    { key: 'retours',    label: 'Retours',            icon: 'heroArrowUturnLeft' },
    { key: 'ecommerce',  label: 'E-Commerce',         icon: 'heroGlobeAlt' },
    { key: 'dashboard',  label: 'Dashboard',          icon: 'heroChartBar' },
  ];

  form: any = {
    nom: '', email: '', password: '',
    permissions: []
  };

  constructor(private ds: DataService, private cdr: ChangeDetectorRef) {}

  ngOnInit() { this.load(); }

  load() {
    this.loading = true;
    this.ds.getUsersObs().subscribe({
      next: (data) => {
        this.users   = (data || []).filter((u: any) => u.role === 'ROLE_AGENT');
        this.loading = false;
        this.cdr.detectChanges();
      },
      error: () => { this.loading = false; this.cdr.detectChanges(); }
    });
  }

  openAdd() {
    this.form      = { nom: '', email: '', password: '', permissions: [] };
    this.errors    = {};
    this.editMode  = false;
    this.showModal = true;
  }

  openEdit(u: any) {
    this.form         = { nom: u.nom, email: u.email, password: '', permissions: [...(u.permissions || [])] };
    this.errors       = {};
    this.editMode     = true;
    this.selectedUser = u;
    this.showModal    = true;
  }

  closeModal() { this.showModal = false; }

  togglePermission(key: string) {
    const idx = this.form.permissions.indexOf(key);
    if (idx === -1) {
      this.form.permissions.push(key);
    } else {
      this.form.permissions.splice(idx, 1);
    }
  }

  hasPermission(key: string): boolean {
    return this.form.permissions.includes(key);
  }

  selectAll() {
    this.form.permissions = this.allPermissions.map(p => p.key);
  }

  clearAll() {
    this.form.permissions = [];
  }

  validate(): boolean {
    this.errors = {};
    if (!this.form.nom?.trim())   this.errors['nom']   = 'Nom obligatoire';
    if (!this.form.email?.trim()) this.errors['email'] = 'Email obligatoire';
    if (!this.editMode && !this.form.password?.trim())
                                  this.errors['password'] = 'Mot de passe obligatoire';
    if (this.form.permissions.length === 0)
                                  this.errors['permissions'] = 'Sélectionnez au moins une permission';
    return Object.keys(this.errors).length === 0;
  }

  save() {
    if (!this.validate()) return;
    const obs = this.editMode
      ? this.ds.updateUserObs(this.selectedUser.id, this.form)
      : this.ds.createUserObs(this.form);

    obs.subscribe({
      next: () => { this.load(); this.closeModal(); },
      error: (err) => {
        this.errors['global'] = err?.error?.error || 'Erreur lors de la sauvegarde';
        this.cdr.detectChanges();
      }
    });
  }

  delete(id: number) {
    if (!confirm('Supprimer cet agent ?')) return;
    this.ds.deleteUserObs(id).subscribe({
      next: () => this.load(),
      error: () => alert('❌ Erreur lors de la suppression')
    });
  }
}