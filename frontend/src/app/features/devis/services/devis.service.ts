import { HttpClient, HttpParams } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { Observable } from 'rxjs';

import { environment } from '../../../../environments/environment';
import {
  DevisDeleteResponse,
  DevisFilter,
  DevisResponse,
  DevisSortField,
  SortOrder,
} from '../models/devis-data';

@Injectable({
  providedIn: 'root',
})
export class DevisService {
  private readonly http = inject(HttpClient);

  private readonly devisApiUrl = `${environment.apiUrl}/devis`;

  getDevis(
    page = 1,
    limit = 10,
    recherche = '',
    statut: DevisFilter = 'tous',
    tri: DevisSortField = 'dateEmission',
    ordre: SortOrder = 'DESC',
  ): Observable<DevisResponse> {
    let params = new HttpParams()
      .set('page', page.toString())
      .set('limit', limit.toString())
      .set('tri', tri)
      .set('ordre', ordre);

    const rechercheNettoyee = recherche.trim();

    if (rechercheNettoyee !== '') {
      params = params.set('recherche', rechercheNettoyee);
    }

    if (statut !== 'tous') {
      params = params.set('statut', statut);
    }

    return this.http.get<DevisResponse>(this.devisApiUrl, { params });
  }

  deleteDevis(id: number): Observable<DevisDeleteResponse> {
    return this.http.delete<DevisDeleteResponse>(`${this.devisApiUrl}/${id}`);
  }
}
