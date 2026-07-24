import { Component, computed, inject, OnInit, signal } from '@angular/core';
import { Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';

import { AuthService } from '../../core/services/auth.service';
import { DashboardService } from '../../features/dashboard/services/dashboard.service';

interface DashboardPeriodOption {
  annee: number;
  mois: number;
  label: string;
}

@Component({
  selector: 'app-app-layout',
  imports: [RouterOutlet, RouterLink, RouterLinkActive],
  templateUrl: './app-layout.html',
  styleUrl: './app-layout.scss',
})
export class AppLayout implements OnInit {
  private readonly authService = inject(AuthService);
  private readonly router = inject(Router);
  private readonly dashboardService = inject(DashboardService);

  protected readonly userMenuOpen = signal(false);
  protected readonly periodMenuOpen = signal(false);

  protected readonly currentUser = this.authService.currentUser;
  protected readonly selectedPeriod = this.dashboardService.selectedPeriod;

  protected readonly periodOptions: DashboardPeriodOption[] = [
    { annee: 2026, mois: 7, label: 'Juillet 2026' },
    { annee: 2026, mois: 6, label: 'Juin 2026' },
    { annee: 2026, mois: 5, label: 'Mai 2026' },
    { annee: 2026, mois: 4, label: 'Avril 2026' },
    { annee: 2026, mois: 3, label: 'Mars 2026' },
    { annee: 2026, mois: 2, label: 'Février 2026' },
    { annee: 2026, mois: 1, label: 'Janvier 2026' },
  ];

  protected readonly selectedPeriodLabel = computed(() => {
    const selected = this.selectedPeriod();

    return (
      this.periodOptions.find((period) => {
        return period.annee === selected.annee && period.mois === selected.mois;
      })?.label ?? 'Sélectionner une période'
    );
  });

  protected readonly displayName = computed(() => {
    const email = this.currentUser()?.email;

    if (!email) {
      return '';
    }

    const localPart = email.split('@')[0];

    return localPart
      .split(/[._-]+/)
      .filter(Boolean)
      .map((part) => {
        return part.charAt(0).toUpperCase() + part.slice(1).toLowerCase();
      })
      .join(' ');
  });

  protected readonly userInitials = computed(() => {
    const name = this.displayName();

    if (!name) {
      return '';
    }

    return name
      .split(' ')
      .filter(Boolean)
      .slice(0, 2)
      .map((part) => part.charAt(0).toUpperCase())
      .join('.');
  });

  protected readonly roleLabel = computed(() => {
    const roles = this.currentUser()?.roles ?? [];

    if (roles.includes('ROLE_ADMIN')) {
      return 'Administrateur';
    }

    return 'Utilisateur';
  });

  ngOnInit(): void {
    this.authService.loadCurrentUser().subscribe({
      error: () => {
        this.authService.logout();
        void this.router.navigate(['/connexion']);
      },
    });
  }

  protected togglePeriodMenu(): void {
    this.periodMenuOpen.update((isOpen) => !isOpen);
  }

  protected selectPeriod(period: DashboardPeriodOption): void {
    this.dashboardService.setSelectedPeriod(period.annee, period.mois);
    this.periodMenuOpen.set(false);
  }

  protected isPeriodSelected(period: DashboardPeriodOption): boolean {
    const selected = this.selectedPeriod();

    return selected.annee === period.annee && selected.mois === period.mois;
  }

  protected toggleUserMenu(): void {
    this.userMenuOpen.update((isOpen) => !isOpen);
  }

  protected logout(): void {
    this.userMenuOpen.set(false);
    this.authService.logout();
    void this.router.navigate(['/connexion']);
  }
}
