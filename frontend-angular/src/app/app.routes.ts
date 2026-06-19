import { Routes } from '@angular/router';
import { authGuard } from './guards/auth.guard';
import { superAdminGuard } from './guards/super-admin.guard';
import { roleGuard } from './guards/role.guard';

export const routes: Routes = [
  { path: '', redirectTo: 'dashboard', pathMatch: 'full' },

  // ── Page login ───────────────────────────────────────
  {
    path: 'login',
    loadComponent: () => import('./pages/login/login.component')
      .then(m => m.LoginComponent)
  },

  {
  path: 'register',
  loadComponent: () => import('./pages/register/register.component')
    .then(m => m.RegisterComponent)
},

  // ── Super Admin ──────────────────────────────────────
  {
    path: 'admin',
    canActivate: [superAdminGuard],
    children: [
      {
        path: 'tenants',
        loadComponent: () => import('./pages/admin/tenants/tenants.component')
          .then(m => m.TenantsComponent)
      },
      { path: '', redirectTo: 'tenants', pathMatch: 'full' }
    ]
  },

  // ── CRM (Admin + Agent selon permissions) ────────────
  {
    path: 'dashboard',
    canActivate: [authGuard],
    loadComponent: () => import('./pages/dashboard/dashboard.component')
      .then(m => m.DashboardComponent)
  },
  {
    path: 'tiers',
    canActivate: [authGuard, roleGuard],
    data: { permission: 'tiers' },
    loadComponent: () => import('./pages/tiers/tiers.component')
      .then(m => m.TiersComponent)
  },
  {
    path: 'contacts',
    canActivate: [authGuard, roleGuard],
    data: { permission: 'tiers' },
    loadComponent: () => import('./pages/contacts/contacts.component')
      .then(m => m.ContactsComponent)
  },
  {
    path: 'commandes',
    canActivate: [authGuard, roleGuard],
    data: { permission: 'commandes' },
    loadComponent: () => import('./pages/commandes/commandes.component')
      .then(m => m.CommandesComponent)
  },
  {
    path: 'livraison',
    canActivate: [authGuard, roleGuard],
    data: { permission: 'livraisons' },
    loadComponent: () => import('./pages/livraison/livraison.component')
      .then(m => m.LivraisonComponent)
  },
  {
    path: 'retours',
    canActivate: [authGuard, roleGuard],
    data: { permission: 'retours' },
    loadComponent: () => import('./pages/retours/retours.component')
      .then(m => m.RetoursComponent)
  },
  {
    path: 'ecommerce',
    canActivate: [authGuard, roleGuard],
    data: { permission: 'ecommerce' },
    loadComponent: () => import('./pages/ecommerce/ecommerce.component')
      .then(m => m.EcommerceComponent)
  },

  // ── Paramètres (Admin seulement) ─────────────────────
  {
    path: 'settings/users',
    canActivate: [authGuard],
    loadComponent: () => import('./pages/settings/users/users.component')
      .then(m => m.UsersComponent)
  },

  // ── Autres pages existantes ──────────────────────────
  {
    path: 'produits',
    canActivate: [authGuard],
    loadComponent: () => import('./pages/produits/produits.component')
      .then(m => m.ProduitsComponent)
  },
  {
    path: 'stock',
    canActivate: [authGuard],
    loadComponent: () => import('./pages/stock/stock.component')
      .then(m => m.StockComponent)
  },
  {
    path: 'depenses',
    canActivate: [authGuard],
    loadComponent: () => import('./pages/depenses/depenses.component')
      .then(m => m.DepensesComponent)
  },
  {
    path: 'ads',
    canActivate: [authGuard],
    loadComponent: () => import('./pages/ads/ads.component')
      .then(m => m.AdsComponent)
  },
  {
    path: 'data',
    canActivate: [authGuard],
    loadComponent: () => import('./pages/data/data.component')
      .then(m => m.DataComponent)
  },
  { path: '**', redirectTo: 'dashboard' }
];