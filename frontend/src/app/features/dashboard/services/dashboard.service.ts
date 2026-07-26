import { HttpClient, HttpParams } from '@angular/common/http';
import { inject, Injectable, signal } from '@angular/core';
import { Observable } from 'rxjs';

import { environment } from '../../../../environments/environment';
import {
  ActiviteRecente,
  DashboardData,
  DerniereFacture,
  EvolutionChiffreAffaires,
  FactureEcheance,
  RepartitionFactures,
} from '../models/dashboard-data';
export interface DashboardPeriodSelection {
  annee: number;
  mois: number;
}

@Injectable({
  providedIn: 'root',
})
export class DashboardService {
  private readonly http = inject(HttpClient);

  private readonly apiUrl = `${environment.apiUrl}/dashboard`;

  private readonly currentDate = new Date();

  private readonly selectedPeriodSignal = signal<DashboardPeriodSelection>({
    annee: this.currentDate.getFullYear(),
    mois: this.currentDate.getMonth() + 1,
  });

  readonly selectedPeriod = this.selectedPeriodSignal.asReadonly();

  setSelectedPeriod(annee: number, mois: number): void {
    this.selectedPeriodSignal.set({
      annee,
      mois,
    });
  }

  getDashboard(annee: number, mois: number): Observable<DashboardData> {
    const params = new HttpParams().set('annee', annee.toString()).set('mois', mois.toString());

    return this.http.get<DashboardData>(this.apiUrl, { params });
  }

  getEvolutionChiffreAffaires(annee: number): Observable<EvolutionChiffreAffaires> {
    const params = new HttpParams().set('annee', annee.toString());

    return this.http.get<EvolutionChiffreAffaires>(`${this.apiUrl}/evolution-chiffre-affaires`, {
      params,
    });
  }

  getRepartitionFactures(): Observable<RepartitionFactures> {
    return this.http.get<RepartitionFactures>(`${this.apiUrl}/repartition-factures`);
  }

  getActiviteRecente(): Observable<ActiviteRecente[]> {
    return this.http.get<ActiviteRecente[]>(`${this.apiUrl}/activite`);
  }

  getDernieresFactures(): Observable<DerniereFacture[]> {
    return this.http.get<DerniereFacture[]>(`${this.apiUrl}/dernieres-factures`);
  }

  getFacturesAEcheance(): Observable<FactureEcheance[]> {
    return this.http.get<FactureEcheance[]>(`${this.apiUrl}/factures-echeance`);
  }
}
