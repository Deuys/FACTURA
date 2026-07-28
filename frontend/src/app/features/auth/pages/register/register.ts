import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import {
  AbstractControl,
  FormControl,
  FormGroup,
  ReactiveFormsModule,
  ValidationErrors,
  Validators,
} from '@angular/forms';
import { RouterLink } from '@angular/router';
import { finalize } from 'rxjs';

import { RegisterRequest } from '../../../../core/models/register-request';
import { AuthService } from '../../../../core/services/auth.service';

@Component({
  selector: 'app-register',
  imports: [ReactiveFormsModule, RouterLink],
  templateUrl: './register.html',
  styleUrl: './register.scss',
})
export class Register {
  private readonly authService = inject(AuthService);

  protected readonly isLoading = signal(false);
  protected readonly showPassword = signal(false);
  protected readonly showPasswordConfirmation = signal(false);
  protected readonly errorMessage = signal<string | null>(null);
  protected readonly successMessage = signal<string | null>(null);

  protected readonly registerForm = new FormGroup(
    {
      prenom: new FormControl('', {
        nonNullable: true,
        validators: [Validators.required, Validators.maxLength(100)],
      }),

      nom: new FormControl('', {
        nonNullable: true,
        validators: [Validators.required, Validators.maxLength(100)],
      }),

      nomEntreprise: new FormControl('', {
        nonNullable: true,
        validators: [Validators.maxLength(150)],
      }),

      email: new FormControl('', {
        nonNullable: true,
        validators: [Validators.required, Validators.email],
      }),

      password: new FormControl('', {
        nonNullable: true,
        validators: [Validators.required, Validators.minLength(12)],
      }),

      passwordConfirmation: new FormControl('', {
        nonNullable: true,
        validators: [Validators.required],
      }),

      acceptTerms: new FormControl(false, {
        nonNullable: true,
        validators: [Validators.requiredTrue],
      }),

      acceptPrivacy: new FormControl(false, {
        nonNullable: true,
        validators: [Validators.requiredTrue],
      }),
    },
    {
      validators: [this.passwordsMatchValidator],
    },
  );

  protected onSubmit(): void {
    this.errorMessage.set(null);
    this.successMessage.set(null);

    if (this.registerForm.invalid || this.isLoading()) {
      this.registerForm.markAllAsTouched();
      return;
    }

    const formValue = this.registerForm.getRawValue();

    const request: RegisterRequest = {
      prenom: formValue.prenom,
      nom: formValue.nom,
      nomEntreprise: formValue.nomEntreprise,
      email: formValue.email,
      password: formValue.password,
      passwordConfirmation: formValue.passwordConfirmation,
      acceptTerms: formValue.acceptTerms,
      acceptPrivacy: formValue.acceptPrivacy,
    };

    this.isLoading.set(true);

    this.authService
      .register(request)
      .pipe(
        finalize(() => {
          this.isLoading.set(false);
        }),
      )
      .subscribe({
        next: () => {
          this.registerForm.reset();

          this.successMessage.set(
            'Compte créé avec succès. Vous pouvez maintenant vous connecter.',
          );
        },

        error: (error: unknown) => {
          this.errorMessage.set(this.getRegisterErrorMessage(error));
        },
      });
  }

  protected togglePasswordVisibility(): void {
    this.showPassword.update((visible) => !visible);
  }

  protected togglePasswordConfirmationVisibility(): void {
    this.showPasswordConfirmation.update((visible) => !visible);
  }

  private passwordsMatchValidator(control: AbstractControl): ValidationErrors | null {
    const password = control.get('password')?.value;
    const passwordConfirmation = control.get('passwordConfirmation')?.value;

    if (password === '' || passwordConfirmation === '') {
      return null;
    }

    return password === passwordConfirmation ? null : { passwordMismatch: true };
  }

  private getRegisterErrorMessage(error: unknown): string {
    if (!(error instanceof HttpErrorResponse)) {
      return 'Une erreur est survenue. Veuillez réessayer.';
    }

    if (error.status === 409) {
      return 'Cette adresse e-mail est déjà utilisée.';
    }

    if (error.status === 0) {
      return 'Impossible de contacter le serveur.';
    }

    const apiMessage = error.error?.message;

    if (typeof apiMessage === 'string' && apiMessage.trim() !== '') {
      return apiMessage;
    }

    const firstFieldError = error.error?.errors?.[0]?.message;

    if (typeof firstFieldError === 'string' && firstFieldError.trim() !== '') {
      return firstFieldError;
    }

    return 'Une erreur est survenue. Veuillez réessayer.';
  }
}
