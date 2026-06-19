import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { DataService } from '../../services/data.service';
import { NgIconComponent } from '@ng-icons/core';

@Component({
  selector: 'app-register',
  standalone: true,
  imports: [CommonModule, FormsModule, NgIconComponent],
  templateUrl: './register.component.html'
})
export class RegisterComponent {
  form = {
    nom:      '',
    email:    '',
    password: '',
    confirm:  '',
    plan:     'free'
  };
  error   = '';
  success = '';
  loading = false;

  constructor(private ds: DataService, private router: Router) {}

  register() {
    this.error   = '';
    this.success = '';

    // Validation
    if (!this.form.nom.trim())      { this.error = 'Le nom est obligatoire';           return; }
    if (!this.form.email.trim())    { this.error = 'L\'email est obligatoire';          return; }
    if (!this.form.password.trim()) { this.error = 'Le mot de passe est obligatoire';   return; }
    if (this.form.password !== this.form.confirm) {
      this.error = 'Les mots de passe ne correspondent pas';
      return;
    }
    if (this.form.password.length < 6) {
      this.error = 'Le mot de passe doit contenir au moins 6 caractères';
      return;
    }

    this.loading = true;

    // Appel à l'endpoint d'auto-inscription tenant
    this.ds.registerTenantObs({
      nom:      this.form.nom,
      email:    this.form.email,
      password: this.form.password,
      plan:     this.form.plan
    }).subscribe({
      next: () => {
        this.loading = false;
        this.success = 'Compte créé avec succès ! Vous pouvez vous connecter.';
        setTimeout(() => this.router.navigate(['/login']), 2000);
      },
      error: (err) => {
        this.loading = false;
        this.error   = err?.error?.error || 'Erreur lors de la création du compte';
      }
    });
  }

  goToLogin() {
    this.router.navigate(['/login']);
  }
}