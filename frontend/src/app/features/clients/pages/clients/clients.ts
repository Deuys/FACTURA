import { Component, inject, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';

import {
  Client,
  ClientFilter,
  ClientSortField,
  ClientsDashboard,
  ClientsPagination,
  SortOrder,
} from '../../models/client-data';
import { ClientsService } from '../../services/clients.service';

@Component({
  selector: 'app-clients',
  imports: [RouterLink],
  templateUrl: './clients.html',
  styleUrl: './clients.scss',
})
export class Clients {
  private readonly clientsService = inject(ClientsService);
  private readonly router = inject(Router);

  private searchTimer: ReturnType<typeof setTimeout> | null = null;

  protected readonly clients = signal<Client[]>([]);
  protected readonly dashboard = signal<ClientsDashboard | null>(null);
  protected readonly pagination = signal<ClientsPagination | null>(null);

  protected readonly searchValue = signal('');
  protected readonly selectedFilter = signal<ClientFilter>('tous');

  protected readonly isLoading = signal(true);
  protected readonly isDashboardLoading = signal(true);
  protected readonly errorMessage = signal<string | null>(null);
  protected readonly dashboardErrorMessage = signal<string | null>(null);

  protected readonly currentPage = signal(1);
  protected readonly pageSize = 20;

  protected readonly filterOptions: ReadonlyArray<{
    value: ClientFilter;
    label: string;
  }> = [
    { value: 'tous', label: 'Tous' },
    { value: 'nouveaux', label: 'Nouveaux' },
    { value: 'a_jour', label: 'À jour' },
    { value: 'en_attente', label: 'En attente' },
    { value: 'en_retard', label: 'En retard' },
    { value: 'archives', label: 'Archivés' },
  ];

  constructor() {
    this.loadClients();
    this.loadDashboard();
  }

  protected openCreateForm(): void {
    void this.router.navigate(['/clients/nouveau']);
  }

  protected loadClients(page = 1): void {
    this.isLoading.set(true);
    this.errorMessage.set(null);

    this.clientsService
      .getClients(
        page,
        this.pageSize,
        this.searchValue(),
        this.selectedFilter(),
        'nom' as ClientSortField,
        'ASC' as SortOrder,
      )
      .subscribe({
        next: (response) => {
          this.clients.set(response.clients);
          this.pagination.set(response.pagination);
          this.currentPage.set(response.pagination.page);
          this.isLoading.set(false);
        },
        error: () => {
          this.clients.set([]);
          this.pagination.set(null);
          this.errorMessage.set('Impossible de charger les clients.');
          this.isLoading.set(false);
        },
      });
  }

  private loadDashboard(): void {
    this.isDashboardLoading.set(true);
    this.dashboardErrorMessage.set(null);

    this.clientsService.getClientsDashboard().subscribe({
      next: (response) => {
        this.dashboard.set(response);
        this.isDashboardLoading.set(false);
      },
      error: () => {
        this.dashboard.set(null);
        this.dashboardErrorMessage.set('Impossible de charger les statistiques.');
        this.isDashboardLoading.set(false);
      },
    });
  }

  protected onSearchInput(event: Event): void {
    const input = event.target as HTMLInputElement;

    this.searchValue.set(input.value);

    if (this.searchTimer !== null) {
      clearTimeout(this.searchTimer);
    }

    this.searchTimer = setTimeout(() => {
      this.loadClients(1);
    }, 350);
  }

  protected selectFilter(filter: ClientFilter): void {
    if (this.selectedFilter() === filter) {
      return;
    }

    this.selectedFilter.set(filter);
    this.loadClients(1);
  }

  protected toggleArchive(client: Client): void {
    const isArchived = client.archivee;
    const clientName = this.getClientName(client);

    const confirmationMessage = isArchived
      ? `Restaurer le client « ${clientName} » ?`
      : `Archiver le client « ${clientName} » ?`;

    if (!window.confirm(confirmationMessage)) {
      return;
    }

    const request$ = isArchived
      ? this.clientsService.restoreClient(client.id)
      : this.clientsService.archiveClient(client.id);

    request$.subscribe({
      next: () => {
        this.loadClients(1);
        this.loadDashboard();
      },
      error: () => {
        this.errorMessage.set(
          isArchived ? 'Impossible de restaurer le client.' : 'Impossible d’archiver le client.',
        );
      },
    });
  }

  protected goToPage(page: number): void {
    const totalPages = this.pagination()?.nombrePages ?? 0;

    if (page < 1 || page > totalPages || page === this.currentPage()) {
      return;
    }

    this.loadClients(page);
  }

  protected goToPreviousPage(): void {
    const previousPage = this.pagination()?.pagePrecedente;

    if (previousPage !== null && previousPage !== undefined) {
      this.goToPage(previousPage);
    }
  }

  protected goToNextPage(): void {
    const nextPage = this.pagination()?.pageSuivante;

    if (nextPage !== null && nextPage !== undefined) {
      this.goToPage(nextPage);
    }
  }

  protected getPageNumbers(): number[] {
    const totalPages = this.pagination()?.nombrePages ?? 0;

    if (totalPages === 0) {
      return [];
    }

    const maximumPages = 5;
    const currentPage = this.currentPage();

    let startPage = Math.max(1, currentPage - 2);
    let endPage = Math.min(totalPages, startPage + maximumPages - 1);

    startPage = Math.max(1, endPage - maximumPages + 1);

    return Array.from({ length: endPage - startPage + 1 }, (_, index) => startPage + index);
  }

  protected getClientName(client: Client): string {
    const fullName = [client.prenom, client.nom]
      .filter((value): value is string => Boolean(value?.trim()))
      .join(' ');

    return fullName || client.entreprise || 'Client sans nom';
  }

  protected getClientInitials(client: Client): string {
    const firstInitial = client.prenom?.trim().charAt(0) ?? '';
    const lastInitial = client.nom.trim().charAt(0);

    return `${firstInitial}${lastInitial}`.toUpperCase();
  }

  protected formatPhone(phone: string | null): string {
    if (!phone) {
      return '—';
    }

    const digits = phone.replace(/\D/g, '');

    if (digits.length === 10) {
      return digits.replace(/(\d{2})(?=\d)/g, '$1 ').trim();
    }

    return phone;
  }

  protected formatCurrency(value: string | number | null | undefined): string {
    if (value === null || value === undefined || value === '') {
      return '—';
    }

    const amount = Number(value);

    if (!Number.isFinite(amount)) {
      return '—';
    }

    return new Intl.NumberFormat('fr-FR', {
      style: 'currency',
      currency: 'EUR',
      minimumFractionDigits: 0,
      maximumFractionDigits: 2,
    }).format(amount);
  }

  protected getClientStatusLabel(client: Client): string {
    return client.statut ?? '—';
  }

  protected getClientStatusClass(client: Client): string {
    const status = client.statut?.trim().toLocaleLowerCase('fr-FR') ?? '';

    if (status.includes('retard')) {
      return 'client-status-late';
    }

    if (status.includes('attente')) {
      return 'client-status-pending';
    }

    if (status.includes('jour')) {
      return 'client-status-up-to-date';
    }

    return 'client-status-unknown';
  }
}
