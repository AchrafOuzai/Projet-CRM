import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { DataService } from '../services/data.service';

export const authGuard = () => {
  const ds = inject(DataService);
  const router = inject(Router);
  if (ds.isLoggedIn()) return true;
  router.navigate(['/login']);
  return false;
};