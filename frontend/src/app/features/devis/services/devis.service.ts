import { HttpClient, HttpParams } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { Observable } from 'rxjs';

import { environment } from '../../../../environments/environment';
import {
  DevisActionResponse,
  DevisCreatePayload,
  DevisCreateResponse,
  DevisDetail,
  DevisDeleteResponse,
  DevisFilter,
  DevisLine,
  DevisLineCreatePayload,
  DevisLineCreateResponse,
  DevisLineDeleteResponse,
  DevisLineUpdatePayload,
  DevisLineUpdateResponse,
  DevisResponse,
  DevisSortField,
  DevisTransformResponse,
  DevisUpdatePayload,
  DevisUpdateResponse,
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

  createDevis(payload: DevisCreatePayload): Observable<DevisCreateResponse> {
    return this.http.post<DevisCreateResponse>(this.devisApiUrl, payload);
  }

  getDevisById(id: number): Observable<DevisDetail> {
    return this.http.get<DevisDetail>(`${this.devisApiUrl}/${id}`);
  }

  updateDevis(id: number, payload: DevisUpdatePayload): Observable<DevisUpdateResponse> {
    return this.http.put<DevisUpdateResponse>(`${this.devisApiUrl}/${id}`, payload);
  }

  getLignesDevis(id: number): Observable<DevisLine[]> {
    return this.http.get<DevisLine[]>(`${this.devisApiUrl}/${id}/lignes`);
  }

  createLigneDevis(
    devisId: number,
    payload: DevisLineCreatePayload,
  ): Observable<DevisLineCreateResponse> {
    return this.http.post<DevisLineCreateResponse>(
      `${this.devisApiUrl}/${devisId}/lignes`,
      payload,
    );
  }

  updateLigneDevis(
    ligneId: number,
    payload: DevisLineUpdatePayload,
  ): Observable<DevisLineUpdateResponse> {
    return this.http.put<DevisLineUpdateResponse>(
      `${environment.apiUrl}/lignes-devis/${ligneId}`,
      payload,
    );
  }

  deleteLigneDevis(ligneId: number): Observable<DevisLineDeleteResponse> {
    return this.http.delete<DevisLineDeleteResponse>(
      `${environment.apiUrl}/lignes-devis/${ligneId}`,
    );
  }

  deleteDevis(id: number): Observable<DevisDeleteResponse> {
    return this.http.delete<DevisDeleteResponse>(`${this.devisApiUrl}/${id}`);
  }

  downloadPdf(id: number): Observable<Blob> {
    return this.http.get(`${this.devisApiUrl}/${id}/pdf`, {
      responseType: 'blob',
    });
  }

  envoyerDevis(id: number): Observable<DevisActionResponse> {
    return this.http.post<DevisActionResponse>(`${this.devisApiUrl}/${id}/envoyer`, {});
  }

  transformerDevis(id: number): Observable<DevisTransformResponse> {
    return this.http.post<DevisTransformResponse>(`${this.devisApiUrl}/${id}/transformer`, {});
  }
}
