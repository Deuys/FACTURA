import { Routes } from '@angular/router';

import { authGuard } from './core/guards/auth-guard';

export const routes: Routes = [
  {
    path: 'inscription',
    loadComponent: () =>
      import('./features/auth/pages/register/register').then((component) => component.Register),
  },
  {
    path: 'connexion',
    loadComponent: () =>
      import('./features/auth/pages/login/login').then((component) => component.Login),
  },
  {
    path: '',
    canActivate: [authGuard],
    loadComponent: () =>
      import('./layout/app-layout/app-layout').then((component) => component.AppLayout),
    children: [
      {
        path: 'dashboard',
        loadComponent: () =>
          import('./features/dashboard/pages/dashboard/dashboard').then(
            (component) => component.Dashboard,
          ),
      },
      {
        path: 'clients',
        loadComponent: () =>
          import('./features/clients/pages/clients/clients').then((component) => component.Clients),
      },
      {
        path: '',
        pathMatch: 'full',
        redirectTo: 'dashboard',
      },
    ],
  },
  {
    path: '**',
    redirectTo: 'connexion',
  },
];
