import { Component, effect, inject, signal } from '@angular/core';

import { DashboardData } from '../../models/dashboard-data';
import { DashboardService } from '../../services/dashboard.service';

@Component({
  selector: 'app-dashboard',
  imports: [],
  templateUrl: './dashboard.html',
  styleUrl: './dashboard.scss',
})
export class Dashboard {
  private readonly dashboardService = inject(DashboardService);

  protected readonly dashboard = signal<DashboardData | null>(null);

  protected readonly isLoading = signal(true);

  protected readonly errorMessage = signal<string | null>(null);

  constructor() {
    effect(() => {
      const period = this.dashboardService.selectedPeriod();

      this.loadDashboard(period.annee, period.mois);
    });
  }

  protected loadDashboard(annee: number, mois: number): void {
    this.isLoading.set(true);
    this.errorMessage.set(null);

    this.dashboardService.getDashboard(annee, mois).subscribe({
      next: (data) => {
        this.dashboard.set(data);
        this.isLoading.set(false);
      },
      error: () => {
        this.dashboard.set(null);
        this.errorMessage.set('Impossible de charger les données du tableau de bord.');
        this.isLoading.set(false);
      },
    });
  }

  protected formatCurrency(value: string): string {
    const amount = Number(value);

    if (!Number.isFinite(amount)) {
      return '0 €';
    }

    return new Intl.NumberFormat('fr-FR', {
      style: 'currency',
      currency: 'EUR',
      minimumFractionDigits: 0,
      maximumFractionDigits: 2,
    }).format(amount);
  }

  protected formatMonth(mois: number): string {
    const date = new Date(2000, mois - 1, 1);

    const monthName = new Intl.DateTimeFormat('fr-FR', {
      month: 'long',
    }).format(date);

    return monthName.charAt(0).toUpperCase() + monthName.slice(1);
  }
}
