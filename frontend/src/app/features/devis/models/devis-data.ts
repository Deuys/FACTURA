export type DevisStatus =
  'Brouillon' | 'En attente' | 'Envoyé' | 'Accepté' | 'Refusé' | 'Expiré' | 'Transformé';

export type DevisFilter = 'tous' | DevisStatus;

export type DevisSortField =
  'numero' | 'dateEmission' | 'dateValidite' | 'totalHT' | 'totalTTC' | 'statut' | 'client';

export type SortOrder = 'ASC' | 'DESC';

export interface DevisClient {
  id: number;
  nom: string;
  prenom: string | null;
  entreprise: string | null;
}

export interface DevisItem {
  id: number;
  numero: string;
  dateEmission: string;
  dateValidite: string;
  statut: DevisStatus;
  totalHT: string;
  totalTVA: string;
  totalTTC: string;
  commentaire: string | null;
  client: DevisClient;
  createdAt: string;
  updatedAt: string;
}

export interface DevisFilters {
  recherche: string;
  statut: DevisStatus | null;
  tri: DevisSortField;
  ordre: SortOrder;
}

export interface DevisPagination {
  page: number;
  limit: number;
  total: number;
  totalPages: number;
  hasPreviousPage: boolean;
  hasNextPage: boolean;
}

export interface DevisResponse {
  filtres: DevisFilters;
  nombreResultats: number;
  nombreResultatsPage: number;
  pagination: DevisPagination;
  devis: DevisItem[];
}
export interface DevisDeleteResponse {
  message: string;
}