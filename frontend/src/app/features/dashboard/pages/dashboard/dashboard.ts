import { Component, effect, inject, signal } from '@angular/core';
import { catchError, forkJoin, of } from 'rxjs';

import {
  ActiviteRecente,
  DashboardData,
  EvolutionChiffreAffaires,
  EvolutionChiffreAffairesDonnee,
  RepartitionFacture,
  RepartitionFactures,
  DerniereFacture,
  FactureEcheance,
} from '../../models/dashboard-data';
import { DashboardService } from '../../services/dashboard.service';

@Component({
  selector: 'app-dashboard',
  imports: [],
  templateUrl: './dashboard.html',
  styleUrl: './dashboard.scss',
})
export class Dashboard {
  private readonly dashboardService = inject(DashboardService);

  private readonly currentYear = new Date().getFullYear();

  private readonly statusColors: Record<string, string> = {
    Brouillon: '#94a3b8',
    Planifiée: '#844fdd',
    'En attente': '#f59e0b',
    Envoyée: '#3b82f6',
    'Partiellement payée': '#06b6d4',
    Payée: '#22c55e',
    Payées: '#22c55e',
    'En retard': '#ef4444',
    'Devis en attente': '#3b82f6',
  };

  private evolutionRequestId = 0;

  protected readonly dashboard = signal<DashboardData | null>(null);

  protected readonly evolution = signal<EvolutionChiffreAffaires | null>(null);

  protected readonly selectedRevenueItem = signal<EvolutionChiffreAffairesDonnee | null>(null);

  protected readonly repartition = signal<RepartitionFactures | null>(null);

  protected readonly activites = signal<ActiviteRecente[]>([]);

  protected readonly dernieresFactures = signal<DerniereFacture[]>([]);

  protected readonly facturesEcheance = signal<FactureEcheance[]>([]);

  protected readonly analyticsYear = signal(this.currentYear);

  protected readonly isAnalyticsYearMenuOpen = signal(false);

  protected readonly availableYears = [
    this.currentYear,
    this.currentYear - 1,
    this.currentYear - 2,
  ];

  protected readonly isLoading = signal(true);

  protected readonly analyticsLoading = signal(false);

  protected readonly errorMessage = signal<string | null>(null);

  constructor() {
    const initialPeriod = this.dashboardService.selectedPeriod();

    this.analyticsYear.set(initialPeriod.annee);
    this.loadEvolution(initialPeriod.annee);

    effect(() => {
      const period = this.dashboardService.selectedPeriod();

      this.loadDashboard(period.annee, period.mois);
    });
  }

  protected loadDashboard(annee: number, mois: number): void {
    this.isLoading.set(true);
    this.errorMessage.set(null);

    forkJoin({
      dashboard: this.dashboardService.getDashboard(annee, mois),
      repartition: this.dashboardService.getRepartitionFactures(),
      activites: this.dashboardService.getActiviteRecente(),

      dernieresFactures: this.dashboardService
        .getDernieresFactures()
        .pipe(catchError(() => of([] as DerniereFacture[]))),

      facturesEcheance: this.dashboardService
        .getFacturesAEcheance()
        .pipe(catchError(() => of([] as FactureEcheance[]))),
    }).subscribe({
      next: ({ dashboard, repartition, activites, dernieresFactures, facturesEcheance }) => {
        this.dashboard.set(dashboard);
        this.repartition.set(repartition);
        this.activites.set(activites);
        this.dernieresFactures.set(dernieresFactures);
        this.facturesEcheance.set(facturesEcheance);
        this.isLoading.set(false);
      },

      error: () => {
        this.dashboard.set(null);
        this.evolution.set(null);
        this.selectedRevenueItem.set(null);
        this.repartition.set(null);
        this.activites.set([]);
        this.dernieresFactures.set([]);
        this.facturesEcheance.set([]);
        this.errorMessage.set('Impossible de charger les données du tableau de bord.');
        this.isLoading.set(false);
      },
    });
  }

  protected toggleAnalyticsYearMenu(): void {
    this.isAnalyticsYearMenuOpen.update((isOpen) => !isOpen);
  }

  protected selectAnalyticsYear(year: number): void {
    if (year === this.analyticsYear()) {
      this.isAnalyticsYearMenuOpen.set(false);
      return;
    }

    this.analyticsYear.set(year);
    this.evolution.set(null);
    this.selectedRevenueItem.set(null);
    this.isAnalyticsYearMenuOpen.set(false);
    this.loadEvolution(year);
  }

  protected selectRevenueItem(item: EvolutionChiffreAffairesDonnee): void {
    this.selectedRevenueItem.set(item);
  }

  private loadEvolution(annee: number): void {
    const requestId = ++this.evolutionRequestId;

    this.analyticsLoading.set(true);

    this.dashboardService.getEvolutionChiffreAffaires(annee).subscribe({
      next: (evolution) => {
        if (requestId !== this.evolutionRequestId) {
          return;
        }

        this.analyticsYear.set(evolution.annee ?? annee);
        this.evolution.set(evolution);
        this.selectedRevenueItem.set(this.getDefaultRevenueItem(evolution.donnees));
        this.analyticsLoading.set(false);
      },
      error: () => {
        if (requestId !== this.evolutionRequestId) {
          return;
        }

        this.evolution.set(null);
        this.selectedRevenueItem.set(null);
        this.analyticsLoading.set(false);
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

  protected formatInvoiceDate(value: string | null): string {
    if (!value) {
      return '—';
    }

    const date = new Date(`${value}T00:00:00`);

    if (Number.isNaN(date.getTime())) {
      return '—';
    }

    return new Intl.DateTimeFormat('fr-FR', {
      day: 'numeric',
      month: 'short',
      year: 'numeric',
    }).format(date);
  }

  protected getInvoiceStatusClass(statut: string): string {
    const classes: Record<string, string> = {
      Brouillon: 'badge-brouillon',
      Planifiée: 'badge-envoyee',
      Envoyée: 'badge-envoyee',
      'En attente': 'badge-en-attente',
      'Partiellement payée': 'badge-en-attente',
      Payée: 'badge-payee',
      'En retard': 'badge-en-retard',
    };

    return classes[statut.trim()] ?? 'badge-brouillon';
  }

  protected getInvoiceStatusLabel(statut: string): string {
    return statut.trim();
  }

  protected formatMonth(mois: number): string {
    const date = new Date(2000, mois - 1, 1);
    const monthName = new Intl.DateTimeFormat('fr-FR', { month: 'long' }).format(date);

    return monthName.charAt(0).toUpperCase() + monthName.slice(1);
  }

  protected formatChartMonth(mois: string): string {
    const abbreviations: Record<string, string> = {
      janvier: 'Jan',
      février: 'Fév',
      mars: 'Mar',
      avril: 'Avr',
      mai: 'Mai',
      juin: 'Juin',
      juillet: 'Juil',
      août: 'Août',
      septembre: 'Sep',
      octobre: 'Oct',
      novembre: 'Nov',
      décembre: 'Déc',
    };

    const normalizedMonth = mois.trim().toLocaleLowerCase('fr-FR');

    return abbreviations[normalizedMonth] ?? mois;
  }

  protected getRevenueChartGridValues(_data: EvolutionChiffreAffairesDonnee[]): number[] {
    return [50_000, 40_000, 30_000, 20_000, 10_000, 0];
  }

  protected getRevenueChartX(index: number, count: number): number {
    const left = 48;
    const right = 700;

    if (count <= 1) {
      return (left + right) / 2;
    }

    return left + (index / (count - 1)) * (right - left);
  }

  protected getRevenueChartY(
    value: string | number,
    data: EvolutionChiffreAffairesDonnee[],
  ): number {
    const top = 24;
    const bottom = 310;
    const amount = Number(value);
    const safeAmount = Number.isFinite(amount) ? Math.max(amount, 0) : 0;
    const ratio = Math.min(safeAmount / this.getRevenueChartMaximum(data), 1);

    return bottom - ratio * (bottom - top);
  }

  protected getRevenueLinePoints(data: EvolutionChiffreAffairesDonnee[]): string {
    return data
      .map((item, index) => {
        const x = this.getRevenueChartX(index, data.length);
        const y = this.getRevenueChartY(item.montant, data);

        return `${x},${y}`;
      })
      .join(' ');
  }

  protected formatRevenueAxisValue(value: number): string {
    if (value <= 0) {
      return '0 €';
    }

    return `${new Intl.NumberFormat('fr-FR', {
      maximumFractionDigits: 0,
    }).format(value / 1000)} k€`;
  }

  protected getPieChartBackground(items: RepartitionFacture[]): string {
    const visibleItems = items.filter((item) => item.pourcentage > 0);

    if (visibleItems.length === 0) {
      return 'conic-gradient(#e5e7eb 0 100%)';
    }

    let start = 0;

    const sections = visibleItems.map((item, index) => {
      const end = start + item.pourcentage;
      const color = this.getStatusColor(item.statut, index);
      const section = `${color} ${start}% ${end}%`;

      start = end;

      return section;
    });

    if (start < 100) {
      sections.push(`#e5e7eb ${start}% 100%`);
    }

    return `conic-gradient(${sections.join(', ')})`;
  }

  protected getStatusColor(statut: string, index = 0): string {
    const fallbackColors = [
      '#22c55e',
      '#f59e0b',
      '#ef4444',
      '#3b82f6',
      '#844fdd',
      '#06b6d4',
      '#94a3b8',
    ];

    return this.statusColors[statut] ?? fallbackColors[index % fallbackColors.length];
  }

  protected formatActivityDate(value: string): string {
    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
      return '';
    }

    const difference = Date.now() - date.getTime();

    if (difference < 0) {
      return new Intl.DateTimeFormat('fr-FR', {
        dateStyle: 'short',
        timeStyle: 'short',
      }).format(date);
    }

    const minutes = Math.floor(difference / 60_000);

    if (minutes < 1) {
      return 'À l’instant';
    }

    if (minutes < 60) {
      return `Il y a ${minutes} min`;
    }

    const hours = Math.floor(minutes / 60);

    if (hours < 24) {
      return `Il y a ${hours} h`;
    }

    const days = Math.floor(hours / 24);

    if (days < 7) {
      return `Il y a ${days} j`;
    }

    return new Intl.DateTimeFormat('fr-FR', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
    }).format(date);
  }

  protected getActivityIcon(type: string): string {
    const icons: Record<string, string> = {
      paiement_recu: '/icons/circle-check-big.svg',
      facture_envoyee: '/icons/file-text.svg',
      facture_en_retard: '/icons/clock-alert.svg',
      client_cree: '/icons/user-plus.svg',
      devis_envoye: '/icons/file-text.svg',
    };

    return icons[type] ?? '/icons/file-text.svg';
  }

  protected getActivityClass(type: string): string {
    const classes: Record<string, string> = {
      paiement_recu: 'activity-icon-success',
      facture_envoyee: 'activity-icon-info',
      facture_en_retard: 'activity-icon-danger',
      client_cree: 'activity-icon-violet',
      devis_envoye: 'activity-icon-warning',
    };

    return classes[type] ?? 'activity-icon-info';
  }

  protected getDistributionShortLabel(statut: string): string {
    return statut === 'Devis en attente' ? 'Devis' : statut;
  }

  private getRevenueChartMaximum(_data: EvolutionChiffreAffairesDonnee[]): number {
    return 50_000;
  }

  private getDefaultRevenueItem(
    data: EvolutionChiffreAffairesDonnee[],
  ): EvolutionChiffreAffairesDonnee | null {
    const latestNonZeroItem = [...data].reverse().find((item) => Number(item.montant) > 0);

    return latestNonZeroItem ?? data.at(-1) ?? null;
  }
}
