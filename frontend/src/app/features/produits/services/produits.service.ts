import { HttpClient, HttpParams } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { Observable } from 'rxjs';

import { environment } from '../../../../environments/environment';
import {
  ProduitFilter,
  ProduitSortField,
  ProduitSortOrder,
  ProduitsResponse,
} from '../models/produit-data';

@Injectable({
  providedIn: 'root',
})
export class ProduitsService {
  private readonly http = inject(HttpClient);

  private readonly produitsApiUrl = `${environment.apiUrl}/produits`;

  getProduits(
    page = 1,
    limit = 100,
    recherche = '',
    filtre: ProduitFilter = 'tous',
    tri: ProduitSortField = 'nom',
    ordre: ProduitSortOrder = 'ASC',
  ): Observable<ProduitsResponse> {
    let params = new HttpParams()
      .set('page', page.toString())
      .set('limit', limit.toString())
      .set('filtre', filtre)
      .set('tri', tri)
      .set('ordre', ordre);

    const rechercheNettoyee = recherche.trim();

    if (rechercheNettoyee !== '') {
      params = params.set('recherche', rechercheNettoyee);
    }

    return this.http.get<ProduitsResponse>(this.produitsApiUrl, { params });
  }
}
