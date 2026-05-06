import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { DataService } from '../../services/data.service';

@Component({
  selector: 'app-login',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './login.component.html',
  styleUrls: ['./login.component.scss']
})
export class LoginComponent {
  email = 'admin@crm.com';
  password = 'admin123';
  error = '';
  loading = false;

  constructor(private ds: DataService, private router: Router) {}

  login() {
    this.loading = true;
    this.error = '';
    this.ds.login(this.email, this.password).subscribe({
      next: () => { this.router.navigate(['/accueil']); },
      error: () => { this.error = 'Email ou mot de passe incorrect'; this.loading = false; }
    });
  }
}