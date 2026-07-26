import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormControl, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router } from '@angular/router';
import { finalize } from 'rxjs';

import { LoginRequest } from '../../../../core/models/login-request';
import { AuthService } from '../../../../core/services/auth.service';

@Component({
  selector: 'app-login',
  imports: [ReactiveFormsModule],
  templateUrl: './login.html',
  styleUrl: './login.scss',
})
export class Login {
  private readonly authService = inject(AuthService);
  private readonly router = inject(Router);

  protected readonly isLoading = signal(false);
  protected readonly showPassword = signal(false);
  protected readonly errorMessage = signal<string | null>(null);
  protected readonly noticeMessage = signal<string | null>(null);

  protected readonly loginForm = new FormGroup({
    email: new FormControl('', {
      nonNullable: true,
      validators: [Validators.required, Validators.email],
    }),

    password: new FormControl('', {
      nonNullable: true,
      validators: [Validators.required, Validators.minLength(12)],
    }),

    rememberMe: new FormControl(false, {
      nonNullable: true,
    }),
  });

  protected onSubmit(): void {
    this.errorMessage.set(null);
    this.noticeMessage.set(null);

    if (this.loginForm.invalid || this.isLoading()) {
      this.loginForm.markAllAsTouched();
      return;
    }

    const { email, password, rememberMe } = this.loginForm.getRawValue();

    const credentials: LoginRequest = {
      email,
      password,
    };

    this.isLoading.set(true);

    this.authService
      .login(credentials, rememberMe)
      .pipe(
        finalize(() => {
          this.isLoading.set(false);
        }),
      )
      .subscribe({
        next: () => {
          void this.router.navigate(['/dashboard']);
        },

        error: (error: unknown) => {
          this.errorMessage.set(this.getLoginErrorMessage(error));
        },
      });
  }

  protected togglePasswordVisibility(): void {
    this.showPassword.update((visible) => !visible);
  }

  protected showForgotPasswordNotice(): void {
    this.errorMessage.set(null);
    this.noticeMessage.set('La récupération du mot de passe sera disponible prochainement.');
  }

  protected showCreateAccountNotice(): void {
    this.errorMessage.set(null);
    this.noticeMessage.set('La création de compte sera disponible prochainement.');
  }

  private getLoginErrorMessage(error: unknown): string {
    if (!(error instanceof HttpErrorResponse)) {
      return 'Une erreur est survenue. Veuillez réessayer.';
    }

    if (error.status === 401) {
      return 'Adresse e-mail ou mot de passe incorrect.';
    }

    if (error.status === 429) {
      return 'Trop de tentatives. Veuillez patienter quelques minutes.';
    }

    if (error.status === 0) {
      return 'Impossible de contacter le serveur.';
    }

    const apiMessage = error.error?.message;

    if (typeof apiMessage === 'string' && apiMessage.trim() !== '') {
      return apiMessage;
    }

    return 'Une erreur est survenue. Veuillez réessayer.';
  }
}
