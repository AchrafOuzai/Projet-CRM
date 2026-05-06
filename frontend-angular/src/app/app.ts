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
      <app-sidebar *ngIf="showSidebar"></app-sidebar>
      <div class="main-content" [style.marginLeft]="showSidebar ? '260px' : '0'">
        <router-outlet></router-outlet>
      </div>
    </div>
  `
})
export class AppComponent implements OnInit {
  showSidebar = false;

  constructor(public ds: DataService, private router: Router) {}

  ngOnInit() {
    this.showSidebar = this.ds.isLoggedIn();
    this.router.events.subscribe(event => {
      if (event instanceof NavigationEnd) {
        this.showSidebar = this.ds.isLoggedIn();
      }
    });
  }
}