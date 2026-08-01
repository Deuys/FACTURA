import { HttpErrorResponse } from '@angular/common/http';
import { Component, computed, inject, signal } from '@angular/core';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { finalize, forkJoin, take } from 'rxjs';

import { DevisDetail as DevisDetailData, DevisLine, DevisStatus } from '../../models/devis-data';
import { DevisService } from '../../services/devis.service';

@Component({
  selector: 'app-devis-detail',
  imports: [RouterLink],
  templateUrl: './devis-detail.html',
  styleUrl: './devis-detail.scss',
})
export class DevisDetail {
  private readonly devisService = inject(DevisService);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);

  protected readonly devis = signal<DevisDetailData | null>(null);
  protected readonly lignes = signal<DevisLine[]>([]);
  protected readonly isLoading = signal(true);
  protected readonly isActionLoading = signal(false);
  protected readonly isPdfLoading = signal(false);
  protected readonly errorMessage = signal<string | null>(null);
  protected readonly actionMessage = signal<string | null>(null);

  protected readonly clientName = computed(() => {
    const currentDevis = this.devis();

    if (!currentDevis) {
      return 'Client';
    }

    return this.getClientName(currentDevis.client);
  });

  protected readonly canSend = computed(() => {
    const status = this.devis()?.statut;

    return status !== undefined && status !== 'Envoyé' && status !== 'Transformé';
  });

  protected readonly canTransform = computed(() => {
    return this.devis()?.statut === 'Accepté' && this.lignes().length > 0;
  });

  constructor() {
    this.route.paramMap.pipe(take(1)).subscribe((params) => {
      const rawId = params.get('id');
      const id = Number(rawId);

      if (!rawId || !Number.isInteger(id) || id <= 0) {
        this.errorMessage.set('Identifiant devis invalide.');
        this.isLoading.set(false);
        return;
      }

      this.loadDevis(id);
    });
  }

  protected goBack(): void {
    void this.router.navigate(['/devis']);
  }

  protected edit(): void {
    const id = this.devis()?.id;

    if (id !== undefined) {
      void this.router.navigate(['/devis', id, 'modifier']);
    }
  }

  protected downloadPdf(): void {
    const id = this.devis()?.id;

    if (id === undefined || this.isPdfLoading()) {
      return;
    }

    this.isPdfLoading.set(true);
    this.errorMessage.set(null);

    this.devisService
      .downloadPdf(id)
      .pipe(finalize(() => this.isPdfLoading.set(false)))
      .subscribe({
        next: (blob) => {
          const url = URL.createObjectURL(blob);
          const link = document.createElement('a');

          link.href = url;
          link.download = `${this.devis()?.numero ?? 'devis'}.pdf`;
          link.click();

          URL.revokeObjectURL(url);
        },
        error: (error: unknown) => {
          this.errorMessage.set(this.getErrorMessage(error));
        },
      });
  }

  protected printPdf(): void {
    const id = this.devis()?.id;

    if (id === undefined || this.isPdfLoading()) {
      return;
    }

    this.isPdfLoading.set(true);
    this.errorMessage.set(null);

    this.devisService
      .downloadPdf(id)
      .pipe(finalize(() => this.isPdfLoading.set(false)))
      .subscribe({
        next: (blob) => {
          const url = URL.createObjectURL(blob);
          const popup = window.open(url, '_blank', 'noopener,noreferrer');

          if (popup) {
            window.setTimeout(() => popup.print(), 700);
          }

          window.setTimeout(() => URL.revokeObjectURL(url), 60_000);
        },
        error: (error: unknown) => {
          this.errorMessage.set(this.getErrorMessage(error));
        },
      });
  }

  protected sendDevis(): void {
    const id = this.devis()?.id;

    if (id === undefined || this.isActionLoading() || !this.canSend()) {
      return;
    }

    if (!window.confirm('Envoyer ce devis au client ?')) {
      return;
    }

    this.isActionLoading.set(true);
    this.errorMessage.set(null);
    this.actionMessage.set(null);

    this.devisService
      .envoyerDevis(id)
      .pipe(finalize(() => this.isActionLoading.set(false)))
      .subscribe({
        next: (response) => {
          this.actionMessage.set(response.message);
          this.loadDevis(id);
        },
        error: (error: unknown) => {
          this.errorMessage.set(this.getErrorMessage(error));
        },
      });
  }

  protected transformDevis(): void {
    const id = this.devis()?.id;

    if (id === undefined || this.isActionLoading() || !this.canTransform()) {
      return;
    }

    if (!window.confirm('Transformer ce devis accepté en facture ?')) {
      return;
    }

    this.isActionLoading.set(true);
    this.errorMessage.set(null);
    this.actionMessage.set(null);

    this.devisService
      .transformerDevis(id)
      .pipe(finalize(() => this.isActionLoading.set(false)))
      .subscribe({
        next: (response) => {
          this.actionMessage.set(`${response.message} Facture ${response.facture.numero} créée.`);

          this.loadDevis(id);
        },
        error: (error: unknown) => {
          this.errorMessage.set(this.getErrorMessage(error));
        },
      });
  }

  protected getClientName(client: DevisDetailData['client']): string {
    const fullName = [client.prenom, client.nom]
      .filter((value): value is string => Boolean(value?.trim()))
      .join(' ');

    return fullName || client.entreprise || 'Client sans nom';
  }

  protected getClientInitials(client: DevisDetailData['client']): string {
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
      month: 'long',
      year: 'numeric',
    }).format(date);
  }

  protected formatDateTime(dateValue: string | null | undefined): string {
    if (!dateValue) {
      return '—';
    }

    const date = new Date(dateValue);

    if (Number.isNaN(date.getTime())) {
      return '—';
    }

    return new Intl.DateTimeFormat('fr-FR', {
      dateStyle: 'medium',
      timeStyle: 'short',
    }).format(date);
  }

  protected formatCurrency(value: string | number | null | undefined): string {
    if (value === null || value === undefined || value === '') {
      return '0,00 €';
    }

    const amount = Number(value);

    if (!Number.isFinite(amount)) {
      return '0,00 €';
    }

    return new Intl.NumberFormat('fr-FR', {
      style: 'currency',
      currency: 'EUR',
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(amount);
  }

  protected getStatusClass(status: DevisStatus): string {
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

  private loadDevis(id: number): void {
    this.isLoading.set(true);
    this.errorMessage.set(null);

    forkJoin({
      devis: this.devisService.getDevisById(id),
      lignes: this.devisService.getLignesDevis(id),
    })
      .pipe(finalize(() => this.isLoading.set(false)))
      .subscribe({
        next: ({ devis, lignes }) => {
          this.devis.set(devis);
          this.lignes.set(lignes);
        },
        error: (error: unknown) => {
          this.errorMessage.set(this.getErrorMessage(error));
        },
      });
  }

  private getErrorMessage(error: unknown): string {
    if (!(error instanceof HttpErrorResponse)) {
      return 'Une erreur est survenue. Veuillez réessayer.';
    }

    if (error.status === 0) {
      return 'Impossible de contacter le serveur.';
    }

    const apiMessage = error.error?.message;

    if (typeof apiMessage === 'string' && apiMessage.trim() !== '') {
      return apiMessage;
    }

    const apiErrors = error.error?.errors;

    if (Array.isArray(apiErrors) && apiErrors.length > 0) {
      const firstError = apiErrors[0];

      if (typeof firstError === 'string') {
        return firstError;
      }

      if (firstError && typeof firstError.message === 'string') {
        return firstError.message;
      }
    }

    return 'Une erreur est survenue. Veuillez réessayer.';
  }
}
