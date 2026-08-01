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
        path: 'clients/nouveau',
        loadComponent: () =>
          import('./features/clients/pages/client-form/client-form').then(
            (component) => component.ClientForm,
          ),
      },
      {
        path: 'clients/:id/modifier',
        loadComponent: () =>
          import('./features/clients/pages/client-form/client-form').then(
            (component) => component.ClientForm,
          ),
      },
      {
        path: 'clients/:id',
        loadComponent: () =>
          import('./features/clients/pages/client-detail/client-detail').then(
            (component) => component.ClientDetail,
          ),
      },
      {
        path: 'clients',
        loadComponent: () =>
          import('./features/clients/pages/clients/clients').then((component) => component.Clients),
      },

      {
        path: 'devis/nouveau',
        loadComponent: () =>
          import('./features/devis/pages/devis-form/devis-form').then(
            (component) => component.DevisForm,
          ),
      },
      {
        path: 'devis/:id/modifier',
        loadComponent: () =>
          import('./features/devis/pages/devis-form/devis-form').then(
            (component) => component.DevisForm,
          ),
      },
      {
        path: 'devis/:id',
        loadComponent: () =>
          import('./features/devis/pages/devis-detail/devis-detail').then(
            (component) => component.DevisDetail,
          ),
      },
      {
        path: 'devis',
        loadComponent: () =>
          import('./features/devis/pages/devis/devis').then((component) => component.Devis),
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
