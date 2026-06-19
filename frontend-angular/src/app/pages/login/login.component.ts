import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { DataService } from '../../services/data.service';
import { NgIconComponent } from '@ng-icons/core';

@Component({
  selector: 'app-login',
  standalone: true,
  imports: [CommonModule, FormsModule, NgIconComponent],
  templateUrl: './login.component.html',
  styleUrls: ['./login.component.scss']
})
export class LoginComponent {
  email    = '';
  password = '';
  error    = '';
  loading  = false;

  // ✅ Modal d'erreur
  showErrorModal = false;
  errorTitle     = '';
  errorMessage   = '';

  constructor(private ds: DataService, private router: Router) {}

  login() {
    if (!this.email.trim() || !this.password.trim()) {
      this.openErrorModal(
        'Champs manquants',
        'Veuillez remplir votre email et votre mot de passe.'
      );
      return;
    }

    this.loading = true;
    this.error   = '';

    this.ds.login(this.email, this.password).subscribe({
      next: (res) => {
        this.loading = false;
        if (res.role === 'ROLE_SUPER_ADMIN') {
          this.router.navigate(['/admin/tenants']);
        } else {
          this.router.navigate(['/dashboard']);
        }
      },
      error: (err) => {
        this.loading = false;
        // ✅ Distinguer compte suspendu vs mauvais identifiants
        if (err?.status === 403) {
          this.openErrorModal(
            'Compte suspendu',
            err?.error?.error || 'Votre compte a été suspendu. Veuillez contacter l\'administrateur.'
          );
        } else {
          this.openErrorModal(
            'Identifiants incorrects',
            'L\'email ou le mot de passe saisi est incorrect. Vérifiez vos informations et réessayez.'
          );
        }
      }
    });
  }

  openErrorModal(title: string, message: string) {
    this.errorTitle    = title;
    this.errorMessage  = message;
    this.showErrorModal = true;
  }

  closeErrorModal() {
    this.showErrorModal = false;
  }

  goToRegister() {
    this.router.navigate(['/register']);
  }
}