import { HttpErrorResponse } from '@angular/common/http';
import { Component, computed, inject, signal } from '@angular/core';
import {
  FormArray,
  FormControl,
  FormGroup,
  FormsModule,
  ReactiveFormsModule,
  Validators,
} from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import {
  Observable,
  catchError,
  concatMap,
  finalize,
  forkJoin,
  from,
  map,
  of,
  switchMap,
  take,
  throwError,
  toArray,
} from 'rxjs';

import { Client, ClientsResponse } from '../../../clients/models/client-data';
import { ClientsService } from '../../../clients/services/clients.service';
import {
  DevisCreateResponse,
  DevisDetail,
  DevisLine,
  DevisLineCreateResponse,
  DevisLineUpdatePayload,
  DevisStatus,
  DevisUpdatePayload,
} from '../../models/devis-data';
import { DevisService } from '../../services/devis.service';
import {
  ProduitItem,
  ProduitsResponse,
} from '../../../produits/models/produit-data';
import { ProduitsService } from '../../../produits/services/produits.service';

type DevisLineFormGroup = FormGroup<{
  id: FormControl<number | null>;
  originalProductId: FormControl<number | null>;
  produitId: FormControl<number | null>;
  designation: FormControl<string>;
  description: FormControl<string>;
  unite: FormControl<string>;
  quantite: FormControl<string>;
  prixUnitaireHT: FormControl<string>;
  tva: FormControl<string>;
  remise: FormControl<string>;
}>;

@Component({
  selector: 'app-devis-form',
  imports: [FormsModule, ReactiveFormsModule, RouterLink],
  templateUrl: './devis-form.html',
  styleUrl: './devis-form.scss',
})
export class DevisForm {
  private readonly devisService = inject(DevisService);
  private readonly clientsService = inject(ClientsService);
  private readonly produitsService = inject(ProduitsService);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);

  protected readonly isEditMode = signal(false);
  protected readonly devisId = signal<number | null>(null);
  protected readonly numero = signal<string | null>(null);

  protected readonly clients = signal<Client[]>([]);
  protected readonly produits = signal<ProduitItem[]>([]);

  protected readonly isLoading = signal(false);
  protected readonly isOptionsLoading = signal(true);
  protected readonly isSaving = signal(false);
  protected readonly errorMessage = signal<string | null>(null);

  protected readonly pageTitle = computed(() =>
    this.isEditMode() ? 'Modifier un devis' : 'Ajouter un devis',
  );

  protected readonly submitLabel = computed(() =>
    this.isEditMode()
      ? 'Enregistrer les modifications'
      : 'Enregistrer le devis',
  );

  protected readonly statusOptions: ReadonlyArray<{
    value: DevisStatus;
    label: string;
  }> = [
    { value: 'Brouillon', label: 'Brouillon' },
    { value: 'En attente', label: 'En attente' },
    { value: 'Envoyé', label: 'Envoyé' },
    { value: 'Accepté', label: 'Accepté' },
    { value: 'Refusé', label: 'Refusé' },
    { value: 'Expiré', label: 'Expiré' },
    { value: 'Transformé', label: 'Transformé' },
  ];

  protected readonly lignes = new FormArray<DevisLineFormGroup>([]);

  protected readonly devisForm = new FormGroup({
    clientId: new FormControl<number | null>(null, {
      validators: [Validators.required],
    }),
    dateEmission: new FormControl('', {
      nonNullable: true,
      validators: [Validators.required],
    }),
    dateValidite: new FormControl('', {
      nonNullable: true,
      validators: [Validators.required],
    }),
    statut: new FormControl<DevisStatus>('Brouillon', {
      nonNullable: true,
      validators: [Validators.required],
    }),
    commentaire: new FormControl('', {
      nonNullable: true,
      validators: [Validators.maxLength(5000)],
    }),
    lignes: this.lignes,
  });

  private readonly initialLineIds = new Set<number>();

  constructor() {
    this.initializeDefaultDates();
    this.addLigne();
    this.loadOptions();

    this.route.paramMap.pipe(take(1)).subscribe((params) => {
      const rawId = params.get('id');

      if (rawId === null) {
        return;
      }

      const id = Number(rawId);

      if (!Number.isInteger(id) || id <= 0) {
        this.errorMessage.set('Identifiant devis invalide.');
        return;
      }

      this.isEditMode.set(true);
      this.devisId.set(id);
      this.loadDevis(id);
    });
  }

  protected onSubmit(): void {
    this.errorMessage.set(null);

    if (this.isLoading() || this.isSaving() || this.isOptionsLoading()) {
      return;
    }

    if (this.devisForm.invalid) {
      this.devisForm.markAllAsTouched();
      return;
    }

    const rawValue = this.devisForm.getRawValue();

    if (rawValue.clientId === null) {
      this.errorMessage.set('Le client est obligatoire.');
      return;
    }

    const headerPayload: DevisUpdatePayload = {
      clientId: rawValue.clientId,
      dateEmission: rawValue.dateEmission,
      dateValidite: rawValue.dateValidite,
      statut: rawValue.statut,
      commentaire: this.toNullable(rawValue.commentaire),
    };

    const currentDevisId = this.devisId();

    const request$ =
      currentDevisId === null
        ? this.createDevisAndLines(headerPayload)
        : this.updateDevisAndLines(currentDevisId, headerPayload);

    this.isSaving.set(true);

    request$.pipe(finalize(() => this.isSaving.set(false))).subscribe({
      next: (savedDevisId) => {
        void this.router.navigate(['/devis', savedDevisId]);
      },
      error: (error: unknown) => {
        this.errorMessage.set(this.getRequestErrorMessage(error));
      },
    });
  }

  protected cancel(): void {
    void this.router.navigate(['/devis']);
  }

  protected addLigne(line?: DevisLine): void {
    this.lignes.push(this.createLineForm(line));
  }

  protected removeLigne(index: number): void {
    if (index < 0 || index >= this.lignes.length) {
      return;
    }

    this.lignes.removeAt(index);

    if (this.lignes.length === 0) {
      this.addLigne();
    }
  }

  protected onProductChange(index: number): void {
    const line = this.lignes.at(index);
    const productId = line.controls.produitId.value;

    if (productId === null) {
      return;
    }

    const product = this.getProduct(productId);

    if (!product) {
      return;
    }

    line.patchValue({
      designation: product.nom,
      description: product.description ?? '',
      unite: product.unite ?? '',
      prixUnitaireHT: product.prixHT,
      tva: product.tva,
    });
  }

  protected getProduct(productId: number | null): ProduitItem | undefined {
    if (productId === null) {
      return undefined;
    }

    return this.produits().find((product) => product.id === productId);
  }

  protected getProductLabel(product: ProduitItem): string {
    return `${product.nom} — ${product.reference}`;
  }

  protected getClientName(client: Client): string {
    const fullName = [client.prenom, client.nom]
      .filter((value): value is string => Boolean(value?.trim()))
      .join(' ');

    return fullName || client.entreprise || 'Client sans nom';
  }

  protected getLineTotal(
    index: number,
    type: 'ht' | 'tva' | 'ttc',
  ): number {
    const value = this.lignes.at(index).getRawValue();

    const quantity = this.toNumber(value.quantite);
    const unitPrice = this.toNumber(value.prixUnitaireHT);
    const taxRate = this.toNumber(value.tva);
    const discountRate = this.toNumber(value.remise);

    const grossHt = quantity * unitPrice;
    const totalHt = grossHt - grossHt * (discountRate / 100);
    const totalTva = totalHt * (taxRate / 100);

    if (type === 'ht') {
      return totalHt;
    }

    if (type === 'tva') {
      return totalTva;
    }

    return totalHt + totalTva;
  }

  protected getTotal(type: 'ht' | 'tva' | 'ttc'): number {
    return this.lignes.controls.reduce(
      (total, _line, index) => total + this.getLineTotal(index, type),
      0,
    );
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

  protected isFieldInvalid(fieldName: string): boolean {
    const control = this.devisForm.get(fieldName);

    return Boolean(control?.invalid && control.touched);
  }

  protected getFieldError(fieldName: string): string | null {
    const control = this.devisForm.get(fieldName);

    if (!control || !control.touched || !control.errors) {
      return null;
    }

    if (control.hasError('required')) {
      if (fieldName === 'clientId') {
        return 'Le client est obligatoire.';
      }

      if (fieldName === 'dateEmission') {
        return "La date d'émission est obligatoire.";
      }

      if (fieldName === 'dateValidite') {
        return 'La date de validité est obligatoire.';
      }

      return 'Ce champ est obligatoire.';
    }

    if (control.hasError('maxlength')) {
      const maxLength = control.getError('maxlength')?.requiredLength;

      return typeof maxLength === 'number'
        ? `Ce champ ne peut pas dépasser ${maxLength} caractères.`
        : 'Ce champ est trop long.';
    }

    return 'La valeur saisie est invalide.';
  }

  protected getLineError(index: number, fieldName: string): string | null {
    const control = this.lignes.at(index).get(fieldName);

    if (!control || !control.touched || !control.errors) {
      return null;
    }

    if (control.hasError('required')) {
      return fieldName === 'produitId'
        ? 'Sélectionnez un produit.'
        : 'Champ obligatoire.';
    }

    if (control.hasError('min')) {
      return fieldName === 'quantite'
        ? 'La quantité doit être supérieure à zéro.'
        : 'La valeur doit être positive.';
    }

    if (control.hasError('max')) {
      return 'La valeur ne peut pas dépasser 100.';
    }

    return 'Valeur invalide.';
  }

  private loadOptions(): void {
    this.isOptionsLoading.set(true);

    forkJoin({
      clients: this.clientsService.getClients(1, 100, '', 'tous', 'nom', 'ASC'),
      produits: this.produitsService.getProduits(
        1,
        100,
        '',
        'tous',
        'nom',
        'ASC',
      ),
    })
      .pipe(finalize(() => this.isOptionsLoading.set(false)))
      .subscribe({
        next: ({
          clients,
          produits,
        }: {
          clients: ClientsResponse;
          produits: ProduitsResponse;
        }) => {
          this.clients.set(clients.clients);
          this.produits.set(produits.produits);
        },
        error: (error: unknown) => {
          this.errorMessage.set(this.getRequestErrorMessage(error));
        },
      });
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
        next: ({
          devis,
          lignes,
        }: {
          devis: DevisDetail;
          lignes: DevisLine[];
        }) => {
          this.patchDevisForm(devis, lignes);
        },
        error: (error: unknown) => {
          this.errorMessage.set(this.getRequestErrorMessage(error));
        },
      });
  }

  private patchDevisForm(devis: DevisDetail, lignes: DevisLine[]): void {
    this.numero.set(devis.numero);

    this.devisForm.patchValue({
      clientId: devis.client.id,
      dateEmission: devis.dateEmission,
      dateValidite: devis.dateValidite,
      statut: devis.statut,
      commentaire: devis.commentaire ?? '',
    });

    this.lignes.clear();
    this.initialLineIds.clear();

    for (const ligne of lignes) {
      this.initialLineIds.add(ligne.id);
      this.addLigne(ligne);
    }

    if (this.lignes.length === 0) {
      this.addLigne();
    }
  }

  private createLineForm(line?: DevisLine): DevisLineFormGroup {
    return new FormGroup({
      id: new FormControl<number | null>(line?.id ?? null),
      originalProductId: new FormControl<number | null>(
        line?.produitId ?? null,
      ),
      produitId: new FormControl<number | null>(line?.produitId ?? null, {
        validators: [Validators.required],
      }),
      designation: new FormControl(line?.designation ?? '', {
        nonNullable: true,
        validators: [Validators.required, Validators.maxLength(150)],
      }),
      description: new FormControl(line?.description ?? '', {
        nonNullable: true,
        validators: [Validators.maxLength(5000)],
      }),
      unite: new FormControl(line?.unite ?? '', {
        nonNullable: true,
        validators: [Validators.maxLength(30)],
      }),
      quantite: new FormControl(line?.quantite ?? '1.00', {
        nonNullable: true,
        validators: [Validators.required, Validators.min(0.01)],
      }),
      prixUnitaireHT: new FormControl(line?.prixUnitaireHT ?? '0.00', {
        nonNullable: true,
        validators: [Validators.required, Validators.min(0)],
      }),
      tva: new FormControl(line?.tva ?? '20.00', {
        nonNullable: true,
        validators: [
          Validators.required,
          Validators.min(0),
          Validators.max(100),
        ],
      }),
      remise: new FormControl(line?.remise ?? '0.00', {
        nonNullable: true,
        validators: [Validators.min(0), Validators.max(100)],
      }),
    });
  }

  private createDevisAndLines(payload: DevisUpdatePayload): Observable<number> {
    if (payload.clientId === undefined) {
      return throwError(() => new Error('Le client est obligatoire.'));
    }

    return this.devisService
      .createDevis({
        clientId: payload.clientId,
        commentaire: payload.commentaire ?? null,
      })
      .pipe(
        switchMap((createdDevis: DevisCreateResponse) =>
          this.createLines(createdDevis.id).pipe(
            switchMap(() =>
              this.devisService.updateDevis(createdDevis.id, payload),
            ),
            map(() => createdDevis.id),
            catchError((error: unknown) =>
              this.devisService.deleteDevis(createdDevis.id).pipe(
                catchError(() => of(null)),
                switchMap(() => throwError(() => error)),
              ),
            ),
          ),
        ),
      );
  }

  private updateDevisAndLines(
    id: number,
    payload: DevisUpdatePayload,
  ): Observable<number> {
    return this.devisService.updateDevis(id, payload).pipe(
      switchMap(() => this.syncExistingLines(id)),
      map(() => id),
    );
  }

  private createLines(devisId: number): Observable<void> {
    return from(this.lignes.controls).pipe(
      concatMap((line) => this.createLine(devisId, line)),
      toArray(),
      map(() => void 0),
    );
  }

  private syncExistingLines(devisId: number): Observable<void> {
    const operations: Observable<unknown>[] = [];
    const currentLineIds = new Set<number>();

    for (const line of this.lignes.controls) {
      const value = line.getRawValue();

      if (value.id === null) {
        operations.push(this.createLine(devisId, line));
        continue;
      }

      currentLineIds.add(value.id);

      if (value.originalProductId !== value.produitId) {
        operations.push(
          this.devisService
            .deleteLigneDevis(value.id)
            .pipe(switchMap(() => this.createLine(devisId, line))),
        );
        continue;
      }

      operations.push(
        this.devisService.updateLigneDevis(
          value.id,
          this.buildLinePayload(line),
        ),
      );
    }

    for (const initialLineId of this.initialLineIds) {
      if (!currentLineIds.has(initialLineId)) {
        operations.push(this.devisService.deleteLigneDevis(initialLineId));
      }
    }

    return from(operations).pipe(
      concatMap((operation) => operation),
      toArray(),
      map(() => void 0),
    );
  }

  private createLine(
    devisId: number,
    line: DevisLineFormGroup,
  ): Observable<unknown> {
    const value = line.getRawValue();

    if (value.produitId === null) {
      return throwError(
        () => new Error('Chaque ligne doit avoir un produit ou un service.'),
      );
    }

    return this.devisService
      .createLigneDevis(devisId, {
        produitId: value.produitId,
        quantite: this.toNumber(value.quantite),
        remise: this.toNumber(value.remise),
      })
      .pipe(
        switchMap((response: DevisLineCreateResponse) =>
          this.devisService.updateLigneDevis(
            response.ligne.id,
            this.buildLinePayload(line),
          ),
        ),
      );
  }

  private buildLinePayload(line: DevisLineFormGroup): DevisLineUpdatePayload {
    const value = line.getRawValue();

    return {
      quantite: this.toNumber(value.quantite),
      remise: this.toNumber(value.remise),
      prixUnitaireHT: this.toNumber(value.prixUnitaireHT),
      tva: this.toNumber(value.tva),
      designation: value.designation.trim(),
      description: this.toNullable(value.description),
      unite: this.toNullable(value.unite),
    };
  }

  private initializeDefaultDates(): void {
    const today = new Date();
    const validity = new Date(today);

    validity.setDate(validity.getDate() + 30);

    this.devisForm.patchValue({
      dateEmission: this.formatInputDate(today),
      dateValidite: this.formatInputDate(validity),
    });
  }

  private formatInputDate(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
  }

  private toNullable(value: string): string | null {
    const normalizedValue = value.trim();

    return normalizedValue === '' ? null : normalizedValue;
  }

  private toNumber(value: string | number | null | undefined): number {
    const normalizedValue = Number(value ?? 0);

    return Number.isFinite(normalizedValue) ? normalizedValue : 0;
  }

  private getRequestErrorMessage(error: unknown): string {
    if (error instanceof Error && !(error instanceof HttpErrorResponse)) {
      return error.message;
    }

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