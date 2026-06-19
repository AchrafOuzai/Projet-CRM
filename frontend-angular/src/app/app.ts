import { Component, OnInit } from '@angular/core';
import { Router, NavigationEnd, RouterOutlet } from '@angular/router';
import { CommonModule } from '@angular/common';
import { SidebarComponent } from './components/sidebar/sidebar.component';
import { DataService } from './services/data.service';

@Component({
  selector: 'app-root',
  standalone: true,
  imports: [RouterOutlet, SidebarComponent, CommonModule],
  template: `
    <div class="app-layout">

      <!-- Sidebar uniquement pour ROLE_ADMIN et ROLE_AGENT -->
      <app-sidebar *ngIf="showSidebar"></app-sidebar>

      <!-- Contenu principal -->
      <div
        class="main-content"
        [style.marginLeft]="showSidebar ? '248px' : '0'">
        <router-outlet></router-outlet>
      </div>

    </div>
  `,
  styles: [`
    .app-layout {
      display: flex;
      min-height: 100vh;
    }
    .main-content {
      flex: 1;
      min-height: 100vh;
      background: #f8fafc;
      transition: margin-left 0.28s ease;
    }
    @media (max-width: 768px) {
      .main-content {
        margin-left: 0 !important;
      }
    }
  `]
})
export class AppComponent implements OnInit {
  showSidebar = false;

  constructor(public ds: DataService, private router: Router) {}

  ngOnInit() {
    this.updateSidebar();
    this.router.events.subscribe(event => {
      if (event instanceof NavigationEnd) {
        this.updateSidebar();
      }
    });
  }

  updateSidebar() {
    if (!this.ds.isLoggedIn()) {
      this.showSidebar = false;
      return;
    }
    // Pas de sidebar pour Super Admin et pages login/register
    const role = this.ds.getUserRole();
    const url  = this.router.url;
    const noSidebarRoutes = ['/login', '/register', '/admin'];
    const isNoSidebarRoute = noSidebarRoutes.some(r => url.startsWith(r));
    this.showSidebar = role !== 'ROLE_SUPER_ADMIN' && !isNoSidebarRoute;
  }
}