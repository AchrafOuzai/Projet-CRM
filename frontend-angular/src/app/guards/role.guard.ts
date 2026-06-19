import { inject } from '@angular/core';
import { Router, ActivatedRouteSnapshot } from '@angular/router';

export const roleGuard = (route: ActivatedRouteSnapshot) => {
  const router     = inject(Router);
  const token      = localStorage.getItem('jwt_token');

  if (!token) {
    router.navigate(['/login']);
    return false;
  }

  try {
    const payload     = JSON.parse(atob(token.split('.')[1]));
    const role        = payload.role;
    const permissions = payload.permissions ?? [];

    // Super Admin et Admin ont accès à tout
    if (role === 'ROLE_SUPER_ADMIN' || role === 'ROLE_ADMIN') return true;

    // Pour les agents, vérifier les permissions
    const requiredPermission = route.data?.['permission'];
    if (!requiredPermission) return true;

    if (!permissions.includes(requiredPermission)) {
      router.navigate(['/dashboard']);
      return false;
    }

    return true;
  } catch {
    router.navigate(['/login']);
    return false;
  }
};