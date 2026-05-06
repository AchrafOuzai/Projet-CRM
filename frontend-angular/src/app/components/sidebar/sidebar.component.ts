import { Component, OnInit } from '@angular/core';
import { Router, NavigationEnd } from '@angular/router';
import { CommonModule } from '@angular/common';
import { DataService } from '../../services/data.service';

@Component({
  selector: 'app-sidebar',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './sidebar.component.html',
  styleUrls: ['./sidebar.component.scss']
})
export class SidebarComponent implements OnInit {
  currentRoute  = '';
  menuOpen      = false;
  tiersOpen     = false;
  commandesOpen = false;

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
  }

  navigate(path: string) { this.router.navigate([path]); this.menuOpen = false; }
  toggleTiers()          { this.tiersOpen     = !this.tiersOpen; }
  toggleCommandes()      { this.commandesOpen = !this.commandesOpen; }
  isActive(path: string) { return this.currentRoute.startsWith(path); }
  logout()               { this.ds.logout(); this.router.navigate(['/login']); }
}