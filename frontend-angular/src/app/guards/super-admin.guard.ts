import { inject } from '@angular/core';
import { Router } from '@angular/router';

export const superAdminGuard = () => {
  const router  = inject(Router);
  const token   = localStorage.getItem('jwt_token');

  if (!token) {
    router.navigate(['/login']);
    return false;
  }

  try {
    const payload     = JSON.parse(atob(token.split('.')[1]));
    const isSuperAdmin = payload.role === 'ROLE_SUPER_ADMIN';
    if (!isSuperAdmin) {
      router.navigate(['/dashboard']);
      return false;
    }
    return true;
  } catch {
    router.navigate(['/login']);
    return false;
  }
};