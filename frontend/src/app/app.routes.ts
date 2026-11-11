import { inject } from '@angular/core';
import { CanActivateFn, Router, Routes } from '@angular/router';
import { SessionService } from './session.service';

const authenticated: CanActivateFn = async () => {
  const session = inject(SessionService);
  const router = inject(Router);
  await session.initialize();
  return session.user() ? true : router.parseUrl('/login');
};
const administrator: CanActivateFn = async () => {
  const session = inject(SessionService);
  const router = inject(Router);
  await session.initialize();
  return session.user()?.role === 'ROLE_ADMIN' ? true : router.parseUrl('/desks');
};

export const routes: Routes = [
  { path: 'login', loadComponent: () => import('./login').then((m) => m.LoginPage) },
  {
    path: 'desks',
    canActivate: [authenticated],
    loadComponent: () => import('./desks').then((m) => m.DesksPage),
  },
  {
    path: 'bookings',
    canActivate: [authenticated],
    loadComponent: () => import('./bookings').then((m) => m.BookingsPage),
  },
  {
    path: 'activity',
    canActivate: [authenticated],
    loadComponent: () => import('./events').then((m) => m.EventsPage),
  },
  {
    path: 'admin',
    canActivate: [authenticated, administrator],
    loadComponent: () => import('./admin').then((m) => m.AdminPage),
  },
  {
    path: 'events',
    canActivate: [authenticated, administrator],
    loadComponent: () => import('./events').then((m) => m.EventsPage),
  },
  { path: '', pathMatch: 'full', redirectTo: 'desks' },
  { path: '**', redirectTo: 'desks' },
];
