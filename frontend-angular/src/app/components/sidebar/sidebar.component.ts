import { Component, OnInit } from '@angular/core';
import { Router, NavigationEnd } from '@angular/router';
import { CommonModule } from '@angular/common';
import { DataService } from '../../services/data.service';
import { NgIconComponent } from '@ng-icons/core';

@Component({
  selector: 'app-sidebar',
  standalone: true,
  imports: [CommonModule, NgIconComponent],
  templateUrl: './sidebar.component.html',
  styleUrls: ['./sidebar.component.scss']
})
export class SidebarComponent implements OnInit {
  currentRoute  = '';
  menuOpen      = false;
  tiersOpen     = false;
  commandesOpen = false;

  isAdmin = false;

  // ✅ Infos utilisateur pour le footer
  userNom   = '';
  userRole  = '';
  userLabel = '';
  userInitiale = '';

  constructor(private router: Router, private ds: DataService) {}

  ngOnInit() {
    this.currentRoute  = this.router.url;
    this.tiersOpen     = this.currentRoute.startsWith('/tiers') || this.currentRoute.startsWith('/contacts');
    this.commandesOpen = this.currentRoute.startsWith('/commandes')
                      || this.currentRoute.startsWith('/livraison')
                      || this.currentRoute.startsWith('/retours');

    this.router.events.subscribe(e => {
      if (e instanceof NavigationEnd) {
        this.currentRoute  = e.url;
        this.tiersOpen     = this.currentRoute.startsWith('/tiers') || this.currentRoute.startsWith('/contacts');
        this.commandesOpen = this.currentRoute.startsWith('/commandes')
                          || this.currentRoute.startsWith('/livraison')
                          || this.currentRoute.startsWith('/retours');
      }
    });

    // ✅ Lecture du JWT pour récupérer nom, rôle, label
    const token = localStorage.getItem('jwt_token');
    if (token) {
      try {
        const payload = JSON.parse(atob(token.split('.')[1]));
        this.isAdmin     = payload.role === 'ROLE_ADMIN';
        this.userNom     = payload.nom  || '';
        this.userRole    = payload.role || '';
        this.userInitiale = this.userNom ? this.userNom.charAt(0).toUpperCase() : 'U';

        // ✅ Label affiché selon le rôle
        if (payload.role === 'ROLE_ADMIN') {
          this.userLabel = 'Administrateur';
        } else if (payload.role === 'ROLE_AGENT') {
          this.userLabel = 'Agent';
        } else {
          this.userLabel = payload.role || '';
        }
      } catch {}
    }
  }

  navigate(path: string) { this.router.navigate([path]); this.menuOpen = false; }
  toggleTiers()          { this.tiersOpen     = !this.tiersOpen; }
  toggleCommandes()      { this.commandesOpen = !this.commandesOpen; }
  isActive(path: string) { return this.currentRoute.startsWith(path); }
  logout()               { this.ds.logout(); this.router.navigate(['/login']); }
}