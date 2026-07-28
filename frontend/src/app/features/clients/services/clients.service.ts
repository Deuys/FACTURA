import { HttpClient, HttpParams } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { Observable } from 'rxjs';

import { environment } from '../../../../environments/environment';
import {
  ClientFilter,
  ClientSortField,
  ClientsDashboard,
  ClientsResponse,
  SortOrder,
} from '../models/client-data';

@Injectable({
  providedIn: 'root',
})
export class ClientsService {
  private readonly http = inject(HttpClient);

  private readonly clientsApiUrl = `${environment.apiUrl}/clients`;
  private readonly dashboardApiUrl = `${environment.apiUrl}/dashboard/clients`;

  getClients(
    page = 1,
    limit = 20,
    recherche = '',
    filtre: ClientFilter = 'tous',
    tri: ClientSortField = 'nom',
    ordre: SortOrder = 'ASC',
  ): Observable<ClientsResponse> {
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

    return this.http.get<ClientsResponse>(this.clientsApiUrl, { params });
  }

  getClientsDashboard(): Observable<ClientsDashboard> {
    return this.http.get<ClientsDashboard>(this.dashboardApiUrl);
  }
}
