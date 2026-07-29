import { HttpErrorResponse } from '@angular/common/http';
import { Component, computed, inject, signal } from '@angular/core';
import { FormControl, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { Observable, finalize, take } from 'rxjs';

import { ClientPayload } from '../../models/client-data';
import { ClientsService } from '../../services/clients.service';

@Component({
  selector: 'app-client-form',
  imports: [ReactiveFormsModule, RouterLink],
  templateUrl: './client-form.html',
  styleUrl: './client-form.scss',
})
export class ClientForm {
  private readonly clientsService = inject(ClientsService);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);

  protected readonly isEditMode = signal(false);
  protected readonly clientId = signal<number | null>(null);

  protected readonly isLoading = signal(false);
  protected readonly isSaving = signal(false);
  protected readonly errorMessage = signal<string | null>(null);

  protected readonly pageTitle = computed(() =>
    this.isEditMode() ? 'Modifier le client' : 'Ajouter un client',
  );

  protected readonly submitLabel = computed(() =>
    this.isEditMode() ? 'Enregistrer les modifications' : 'Enregistrer le client',
  );

  protected readonly clientForm = new FormGroup({
    prenom: new FormControl('', {
      nonNullable: true,
      validators: [Validators.maxLength(100)],
    }),

    nom: new FormControl('', {
      nonNullable: true,
      validators: [Validators.required, Validators.maxLength(100)],
    }),

    entreprise: new FormControl('', {
      nonNullable: true,
      validators: [Validators.maxLength(150)],
    }),

    email: new FormControl('', {
      nonNullable: true,
      validators: [Validators.required, Validators.email, Validators.maxLength(180)],
    }),

    telephone: new FormControl('', {
      nonNullable: true,
      validators: [Validators.maxLength(20), Validators.pattern(/^[0-9+().\s-]+$/)],
    }),

    adresse: new FormControl('', {
      nonNullable: true,
      validators: [Validators.maxLength(255)],
    }),

    codePostal: new FormControl('', {
      nonNullable: true,
      validators: [Validators.maxLength(10), Validators.pattern(/^[A-Za-z0-9\s-]+$/)],
    }),

    ville: new FormControl('', {
      nonNullable: true,
      validators: [Validators.maxLength(100)],
    }),

    pays: new FormControl('France', {
      nonNullable: true,
      validators: [Validators.required, Validators.maxLength(100)],
    }),

    siret: new FormControl('', {
      nonNullable: true,
      validators: [Validators.pattern(/^\d{14}$/)],
    }),

    tvaIntracom: new FormControl('', {
      nonNullable: true,
      validators: [Validators.maxLength(20), Validators.pattern(/^[A-Za-z0-9]+$/)],
    }),
  });

  constructor() {
    this.route.paramMap.pipe(take(1)).subscribe((params) => {
      const rawId = params.get('id');

      if (rawId === null) {
        return;
      }

      const id = Number(rawId);

      if (!Number.isInteger(id) || id <= 0) {
        this.errorMessage.set('Identifiant client invalide.');
        return;
      }

      this.isEditMode.set(true);
      this.clientId.set(id);
      this.loadClient(id);
    });
  }

  protected onSubmit(): void {
    this.errorMessage.set(null);

    if (this.isLoading() || this.isSaving()) {
      return;
    }

    if (this.clientForm.invalid) {
      this.clientForm.markAllAsTouched();
      return;
    }

    const payload = this.buildPayload();

    let request$: Observable<unknown>;

    if (this.isEditMode()) {
      const editClientId = this.clientId();

      if (editClientId === null) {
        this.errorMessage.set('Identifiant client manquant.');
        return;
      }

      request$ = this.clientsService.updateClient(editClientId, payload);
    } else {
      request$ = this.clientsService.createClient(payload);
    }

    this.isSaving.set(true);

    request$
      .pipe(
        finalize(() => {
          this.isSaving.set(false);
        }),
      )
      .subscribe({
        next: () => {
          void this.router.navigate(['/clients']);
        },
        error: (error: unknown) => {
          this.errorMessage.set(this.getRequestErrorMessage(error));
        },
      });
  }

  protected cancel(): void {
    void this.router.navigate(['/clients']);
  }

  protected isFieldInvalid(fieldName: string): boolean {
    const control = this.clientForm.get(fieldName);

    return Boolean(control?.invalid && control.touched);
  }

  protected getFieldError(fieldName: string): string | null {
    const control = this.clientForm.get(fieldName);

    if (!control || !control.touched || !control.errors) {
      return null;
    }

    if (control.hasError('required')) {
      if (fieldName === 'nom') {
        return 'Le nom du client est obligatoire.';
      }

      if (fieldName === 'email') {
        return 'L’adresse e-mail est obligatoire.';
      }

      if (fieldName === 'pays') {
        return 'Le pays est obligatoire.';
      }

      return 'Ce champ est obligatoire.';
    }

    if (control.hasError('email')) {
      return 'L’adresse e-mail n’est pas valide.';
    }

    if (control.hasError('maxlength')) {
      const maxLength = control.getError('maxlength')?.requiredLength;

      return typeof maxLength === 'number'
        ? `Ce champ ne peut pas dépasser ${maxLength} caractères.`
        : 'Ce champ est trop long.';
    }

    if (control.hasError('pattern')) {
      if (fieldName === 'telephone') {
        return 'Le numéro de téléphone contient des caractères invalides.';
      }

      if (fieldName === 'codePostal') {
        return 'Le code postal contient des caractères invalides.';
      }

      if (fieldName === 'siret') {
        return 'Le SIRET doit contenir exactement 14 chiffres.';
      }

      if (fieldName === 'tvaIntracom') {
        return 'Le numéro de TVA contient des caractères invalides.';
      }

      return 'La valeur saisie est invalide.';
    }

    return 'La valeur saisie est invalide.';
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
          this.clientForm.patchValue({
            prenom: client.prenom ?? '',
            nom: client.nom ?? '',
            entreprise: client.entreprise ?? '',
            email: client.email ?? '',
            telephone: client.telephone ?? '',
            adresse: client.adresse ?? '',
            codePostal: client.codePostal ?? '',
            ville: client.ville ?? '',
            pays: client.pays ?? 'France',
            siret: client.siret ?? '',
            tvaIntracom: client.tvaIntracom ?? '',
          });
        },
        error: (error: unknown) => {
          this.errorMessage.set(this.getRequestErrorMessage(error));
        },
      });
  }

  private buildPayload(): ClientPayload {
    const value = this.clientForm.getRawValue();

    return {
      prenom: this.toNullable(value.prenom),
      nom: value.nom.trim(),
      entreprise: this.toNullable(value.entreprise),
      email: value.email.trim(),
      telephone: this.toNullable(value.telephone),
      adresse: this.toNullable(value.adresse),
      codePostal: this.toNullable(value.codePostal),
      ville: this.toNullable(value.ville),
      pays: value.pays.trim(),
      siret: this.toNullable(value.siret),
      tvaIntracom: this.toNullable(value.tvaIntracom),
    };
  }

  private toNullable(value: string): string | null {
    const normalizedValue = value.trim();

    return normalizedValue === '' ? null : normalizedValue;
  }

  private getRequestErrorMessage(error: unknown): string {
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
