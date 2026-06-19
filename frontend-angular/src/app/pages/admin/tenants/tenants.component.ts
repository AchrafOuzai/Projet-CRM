import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { DataService } from '../../../services/data.service';
import { NgIconComponent } from '@ng-icons/core';
import { Router } from '@angular/router';

@Component({
  selector: 'app-tenants',
  standalone: true,
  imports: [CommonModule, FormsModule, NgIconComponent],
  templateUrl: './tenants.component.html'
})
export class TenantsComponent implements OnInit {
  tenants:   any[] = [];
  loading  = true;
  showModal      = false;
  editMode       = false;
  showResetModal = false;

  // ✅ Nouveaux modals
  showToggleModal = false;
  showDeleteModal = false;
  selectedTenant: any = null;

  form: any = { nom: '', email: '', password: '', plan: 'free' };
  resetPassword = '';
  errors: any   = {};
  plans = ['free', 'pro', 'enterprise'];

  constructor(
    private ds: DataService,
    private cdr: ChangeDetectorRef,
    private router: Router
  ) {}

  ngOnInit() { this.load(); }

  load() {
    this.loading = true;
    this.ds.getTenantsObs().subscribe({
      next: (data) => {
        this.tenants = data || [];
        this.loading = false;
        this.cdr.detectChanges();
      },
      error: () => { this.loading = false; this.cdr.detectChanges(); }
    });
  }

  openAdd() {
    this.form      = { nom: '', email: '', password: '', plan: 'free' };
    this.errors    = {};
    this.editMode  = false;
    this.showModal = true;
  }

  openEdit(t: any) {
    this.form           = { nom: t.nom, email: t.email, plan: t.plan };
    this.errors         = {};
    this.editMode       = true;
    this.selectedTenant = t;
    this.showModal      = true;
  }

  openReset(t: any) {
    this.selectedTenant  = t;
    this.resetPassword   = '';
    this.showResetModal  = true;
  }

  // ✅ Ouvre le modal de confirmation suspendre/activer
  openToggle(t: any) {
    this.selectedTenant  = t;
    this.showToggleModal = true;
  }

  // ✅ Ouvre le modal de confirmation suppression
  openDelete(t: any) {
    this.selectedTenant  = t;
    this.showDeleteModal = true;
  }

  closeModal()       { this.showModal       = false; }
  closeResetModal()  { this.showResetModal  = false; }
  closeToggleModal() { this.showToggleModal = false; }
  closeDeleteModal() { this.showDeleteModal = false; }

  validate(): boolean {
    this.errors = {};
    if (!this.form.nom?.trim())   this.errors['nom']      = 'Nom obligatoire';
    if (!this.form.email?.trim()) this.errors['email']    = 'Email obligatoire';
    if (!this.editMode && !this.form.password?.trim())
                                  this.errors['password'] = 'Mot de passe obligatoire';
    return Object.keys(this.errors).length === 0;
  }

  save() {
    if (!this.validate()) return;
    const obs = this.editMode
      ? this.ds.updateTenantObs(this.selectedTenant.id, this.form)
      : this.ds.createTenantObs(this.form);
    obs.subscribe({
      next: () => { this.load(); this.closeModal(); },
      error: (err) => {
        this.errors['global'] = err?.error?.error || 'Erreur lors de la sauvegarde';
        this.cdr.detectChanges();
      }
    });
  }

  saveReset() {
    if (!this.resetPassword.trim()) return;
    this.ds.resetTenantPasswordObs(this.selectedTenant.id, this.resetPassword).subscribe({
      next: () => { this.closeResetModal(); },
      error: () => {}
    });
  }

  // ✅ Confirme le toggle depuis le modal
  confirmToggle() {
    this.ds.toggleTenantStatutObs(this.selectedTenant.id).subscribe({
      next: () => { this.load(); this.closeToggleModal(); },
      error: () => { this.closeToggleModal(); }
    });
  }

  // ✅ Confirme la suppression depuis le modal
  confirmDelete() {
    this.ds.deleteTenantObs(this.selectedTenant.id).subscribe({
      next: () => { this.load(); this.closeDeleteModal(); },
      error: () => { this.closeDeleteModal(); }
    });
  }

  logout() { this.ds.logout(); this.router.navigate(['/login']); }

  getStatutClass(statut: string): string {
    return statut === 'actif'
      ? 'bg-emerald-100 text-emerald-700 border border-emerald-200'
      : 'bg-red-100 text-red-700 border border-red-200';
  }

  getPlanClass(plan: string): string {
    const map: any = {
      'free':       'bg-slate-100 text-slate-600 border border-slate-200',
      'pro':        'bg-blue-100 text-blue-700 border border-blue-200',
      'enterprise': 'bg-purple-100 text-purple-700 border border-purple-200'
    };
    return map[plan] || 'bg-slate-100 text-slate-600';
  }
}