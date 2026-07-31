import { Component, computed, inject, signal } from '@angular/core';
import { forkJoin } from 'rxjs';

import {
  DevisClient,
  DevisFilter,
  DevisItem,
  DevisPagination,
  DevisStatus,
} from '../../models/devis-data';
import { DevisService } from '../../services/devis.service';

const STATUTS_SUPPRIMABLES: readonly DevisStatus[] = ['Brouillon', 'Refusé', 'Expiré'];

@Component({
  selector: 'app-devis',
  imports: [],
  templateUrl: './devis.html',
  styleUrl: './devis.scss',
})
export class Devis {
  private readonly devisService = inject(DevisService);

  private searchTimer: ReturnType<typeof setTimeout> | null = null;

  protected readonly devis = signal<DevisItem[]>([]);
  protected readonly pagination = signal<DevisPagination | null>(null);

  protected readonly searchValue = signal('');
  protected readonly selectedFilter = signal<DevisFilter>('tous');

  protected readonly isLoading = signal(true);
  protected readonly isDeleting = signal(false);
  protected readonly errorMessage = signal<string | null>(null);

  protected readonly currentPage = signal(1);
  protected readonly pageSize = 10;

  protected readonly selectedDevisIds = signal<Set<number>>(new Set<number>());

  protected readonly deletableDevis = computed(() =>
    this.devis().filter((devisItem) => this.canDeleteDevis(devisItem)),
  );

  protected readonly selectedDevisCount = computed(() => this.selectedDevisIds().size);

  protected readonly allDevisSelected = computed(() => {
    const deletable = this.deletableDevis();
    const selectedIds = this.selectedDevisIds();

    return deletable.length > 0 && deletable.every((devisItem) => selectedIds.has(devisItem.id));
  });

  protected readonly someDevisSelected = computed(() => {
    const deletable = this.deletableDevis();
    const selectedIds = this.selectedDevisIds();

    const selectedCount = deletable.filter((devisItem) => selectedIds.has(devisItem.id)).length;

    return selectedCount > 0 && selectedCount < deletable.length;
  });

  protected readonly filterOptions: ReadonlyArray<{
    value: DevisFilter;
    label: string;
  }> = [
    { value: 'tous', label: 'Tous' },
    { value: 'Brouillon', label: 'Brouillons' },
    { value: 'Envoyé', label: 'Envoyés' },
    { value: 'Accepté', label: 'Acceptés' },
    { value: 'Refusé', label: 'Refusés' },
  ];

  constructor() {
    this.loadDevis();
  }

  protected loadDevis(page = 1): void {
    this.selectedDevisIds.set(new Set<number>());
    this.isLoading.set(true);
    this.errorMessage.set(null);

    this.devisService
      .getDevis(
        page,
        this.pageSize,
        this.searchValue(),
        this.selectedFilter(),
        'dateEmission',
        'DESC',
      )
      .subscribe({
        next: (response) => {
          const lastPage = Math.max(1, response.pagination.totalPages);

          if (page > lastPage) {
            this.loadDevis(lastPage);
            return;
          }

          this.devis.set(response.devis);
          this.pagination.set(response.pagination);
          this.currentPage.set(response.pagination.page);
          this.isLoading.set(false);
        },
        error: () => {
          this.devis.set([]);
          this.pagination.set(null);
          this.errorMessage.set('Impossible de charger les devis.');
          this.isLoading.set(false);
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
      this.loadDevis(1);
    }, 350);
  }

  protected selectFilter(filter: DevisFilter): void {
    if (this.selectedFilter() === filter) {
      return;
    }

    this.selectedFilter.set(filter);
    this.loadDevis(1);
  }

  protected canDeleteDevis(devisItem: DevisItem): boolean {
    return STATUTS_SUPPRIMABLES.includes(devisItem.statut);
  }

  protected toggleAllDevis(event: Event): void {
    const checkbox = event.target as HTMLInputElement;
    const nextSelectedIds = new Set(this.selectedDevisIds());

    for (const devisItem of this.deletableDevis()) {
      if (checkbox.checked) {
        nextSelectedIds.add(devisItem.id);
      } else {
        nextSelectedIds.delete(devisItem.id);
      }
    }

    this.selectedDevisIds.set(nextSelectedIds);
  }

  protected toggleDevisSelection(devisItem: DevisItem, event: Event): void {
    if (!this.canDeleteDevis(devisItem)) {
      return;
    }

    const checkbox = event.target as HTMLInputElement;
    const nextSelectedIds = new Set(this.selectedDevisIds());

    if (checkbox.checked) {
      nextSelectedIds.add(devisItem.id);
    } else {
      nextSelectedIds.delete(devisItem.id);
    }

    this.selectedDevisIds.set(nextSelectedIds);
  }

  protected isDevisSelected(id: number): boolean {
    return this.selectedDevisIds().has(id);
  }

  protected deleteDevis(devisItem: DevisItem): void {
    if (!this.canDeleteDevis(devisItem) || this.isDeleting()) {
      return;
    }

    if (!window.confirm(`Supprimer définitivement le devis ${devisItem.numero} ?`)) {
      return;
    }

    this.isDeleting.set(true);

    this.devisService.deleteDevis(devisItem.id).subscribe({
      next: () => {
        this.isDeleting.set(false);
        this.selectedDevisIds.set(new Set<number>());
        this.loadDevis(this.currentPage());
      },
      error: () => {
        this.isDeleting.set(false);
        this.errorMessage.set('Impossible de supprimer ce devis.');
      },
    });
  }

  protected deleteSelectedDevis(): void {
    const selectedIds = [...this.selectedDevisIds()];

    if (selectedIds.length === 0 || this.isDeleting()) {
      return;
    }

    if (!window.confirm(`Supprimer définitivement ${selectedIds.length} devis sélectionné(s) ?`)) {
      return;
    }

    this.isDeleting.set(true);

    forkJoin(selectedIds.map((id) => this.devisService.deleteDevis(id))).subscribe({
      next: () => {
        this.isDeleting.set(false);
        this.selectedDevisIds.set(new Set<number>());
        this.loadDevis(this.currentPage());
      },
      error: () => {
        this.isDeleting.set(false);
        this.selectedDevisIds.set(new Set<number>());
        this.loadDevis(this.currentPage());
        this.errorMessage.set('Impossible de supprimer les devis sélectionnés.');
      },
    });
  }

  protected goToPage(page: number): void {
    const totalPages = this.pagination()?.totalPages ?? 0;

    if (page < 1 || page > totalPages || page === this.currentPage()) {
      return;
    }

    this.loadDevis(page);
  }

  protected goToPreviousPage(): void {
    const page = this.pagination();

    if (page?.hasPreviousPage) {
      this.goToPage(page.page - 1);
    }
  }

  protected goToNextPage(): void {
    const page = this.pagination();

    if (page?.hasNextPage) {
      this.goToPage(page.page + 1);
    }
  }

  protected getPageNumbers(): number[] {
    const totalPages = this.pagination()?.totalPages ?? 0;

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

  protected getClientName(client: DevisClient): string {
    const fullName = [client.prenom, client.nom]
      .filter((value): value is string => Boolean(value?.trim()))
      .join(' ');

    return fullName || client.entreprise || 'Client sans nom';
  }

  protected getClientInitials(client: DevisClient): string {
    const firstInitial = client.prenom?.trim().charAt(0) ?? '';
    const lastInitial = client.nom?.trim().charAt(0) ?? '';

    return `${firstInitial}${lastInitial}`.toUpperCase();
  }

  protected formatDate(dateValue: string | null | undefined): string {
    if (!dateValue) {
      return '—';
    }

    const date = new Date(`${dateValue}T00:00:00`);

    if (Number.isNaN(date.getTime())) {
      return '—';
    }

    return new Intl.DateTimeFormat('fr-FR', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    }).format(date);
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
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(amount);
  }

  protected getDevisStatusClass(status: DevisStatus): string {
    const normalizedStatus = status.toLocaleLowerCase('fr-FR');

    if (normalizedStatus === 'brouillon') {
      return 'devis-status-draft';
    }

    if (normalizedStatus.includes('envoy')) {
      return 'devis-status-sent';
    }

    if (normalizedStatus.includes('accept')) {
      return 'devis-status-accepted';
    }

    if (normalizedStatus.includes('refus')) {
      return 'devis-status-refused';
    }

    if (normalizedStatus.includes('attente')) {
      return 'devis-status-pending';
    }

    if (normalizedStatus.includes('expir')) {
      return 'devis-status-expired';
    }

    if (normalizedStatus.includes('transform')) {
      return 'devis-status-transformed';
    }

    return 'devis-status-unknown';
  }
}
