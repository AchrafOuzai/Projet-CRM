import { Routes } from '@angular/router';
import { authGuard } from './guards/auth.guard';

export const routes: Routes = [
  { path: '', redirectTo: 'tiers', pathMatch: 'full' },
  { path: 'login',         loadComponent: () => import('./pages/login/login.component').then(m => m.LoginComponent) },
  { path: 'tiers',         canActivate: [authGuard], loadComponent: () => import('./pages/tiers/tiers.component').then(m => m.TiersComponent) },
  { path: 'contacts',      canActivate: [authGuard], loadComponent: () => import('./pages/contacts/contacts.component').then(m => m.ContactsComponent) },
  { path: 'commandes',     canActivate: [authGuard], loadComponent: () => import('./pages/commandes/commandes.component').then(m => m.CommandesComponent) },
  { path: 'livraison',     canActivate: [authGuard], loadComponent: () => import('./pages/livraison/livraison.component').then(m => m.LivraisonComponent) },
  { path: 'retours',       canActivate: [authGuard], loadComponent: () => import('./pages/retours/retours.component').then(m => m.RetoursComponent) },
  { path: 'ecommerce',     canActivate: [authGuard], loadComponent: () => import('./pages/ecommerce/ecommerce.component').then(m => m.EcommerceComponent) },
  { path: 'dashboard',     canActivate: [authGuard], loadComponent: () => import('./pages/dashboard/dashboard.component').then(m => m.DashboardComponent) },
  { path: 'suivi-commandes', canActivate: [authGuard], loadComponent: () => import('./pages/suivi-commandes/suivi-commandes.component').then(m => m.SuiviCommandesComponent) },
  { path: 'produits',      canActivate: [authGuard], loadComponent: () => import('./pages/produits/produits.component').then(m => m.ProduitsComponent) },
  { path: 'stock',         canActivate: [authGuard], loadComponent: () => import('./pages/stock/stock.component').then(m => m.StockComponent) },
  { path: 'depenses',      canActivate: [authGuard], loadComponent: () => import('./pages/depenses/depenses.component').then(m => m.DepensesComponent) },
  { path: 'ads',           canActivate: [authGuard], loadComponent: () => import('./pages/ads/ads.component').then(m => m.AdsComponent) },
  { path: 'data',          canActivate: [authGuard], loadComponent: () => import('./pages/data/data.component').then(m => m.DataComponent) },
  { path: 'data-reference', canActivate: [authGuard], loadComponent: () => import('./pages/data-reference/data-reference.component').then(m => m.DataReferenceComponent) },
  { path: '**', redirectTo: 'tiers' }
];