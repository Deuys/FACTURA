import { HttpErrorResponse } from '@angular/common/http';
import { Component, computed, inject, signal } from '@angular/core';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { finalize, take } from 'rxjs';

import { ClientDetails } from '../../models/client-data';
import { ClientsService } from '../../services/clients.service';

@Component({
  selector: 'app-client-detail',
  imports: [RouterLink],
  templateUrl: './client-detail.html',
  styleUrl: './client-detail.scss',
})
export class ClientDetail {
  private readonly clientsService = inject(ClientsService);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);

  protected readonly client = signal<ClientDetails | null>(null);
  protected readonly isLoading = signal(true);
  protected readonly errorMessage = signal<string | null>(null);

  protected readonly clientName = computed(() => {
    const currentClient = this.client();

    if (!currentClient) {
      return 'Client';
    }

    const fullName = [currentClient.prenom, currentClient.nom]
      .filter((value): value is string => Boolean(value?.trim()))
      .join(' ');

    return fullName || currentClient.entreprise || 'Client sans nom';
  });

  constructor() {
    this.route.paramMap.pipe(take(1)).subscribe((params) => {
      const rawId = params.get('id');
      const id = Number(rawId);

      if (!rawId || !Number.isInteger(id) || id <= 0) {
        this.errorMessage.set('Identifiant client invalide.');
        this.isLoading.set(false);
        return;
      }

      this.loadClient(id);
    });
  }

  protected goBack(): void {
    void this.router.navigate(['/clients']);
  }

  protected getInitials(client: ClientDetails): string {
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

  protected getStatusClass(status: string | null | undefined): string {
    const normalizedStatus = status?.trim().toLocaleLowerCase('fr-FR') ?? '';

    if (normalizedStatus.includes('retard')) {
      return 'client-status-late';
    }

    if (normalizedStatus.includes('attente')) {
      return 'client-status-pending';
    }

    if (normalizedStatus.includes('jour')) {
      return 'client-status-up-to-date';
    }

    return 'client-status-unknown';
  }

  private loadClient(id: number): void {
    this.isLoading.set(true);
    this.errorMessage.set(null);

    this.clientsService
      .getClient(id)
      .pipe(
        finalize(() => {
          this.isLoading.set(false);
        }),
      )
      .subscribe({
        next: (client) => {
          this.client.set(client);
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

    if (error.status === 404) {
      return 'Client introuvable.';
    }

    const apiMessage = error.error?.message;

    return typeof apiMessage === 'string' && apiMessage.trim() !== ''
      ? apiMessage
      : 'Une erreur est survenue. Veuillez réessayer.';
  }
}
