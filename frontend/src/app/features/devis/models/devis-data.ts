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
  createdAt?: string | null;
  updatedAt?: string | null;
}

export type DevisDetail = DevisItem;

export interface DevisLine {
  id: number;
  produitId: number | null;
  designation: string;
  description: string | null;
  quantite: string;
  prixUnitaireHT: string;
  tva: string;
  remise: string | null;
  unite: string | null;
  totalHT: string;
  totalTVA: string;
  totalTTC: string;
  createdAt?: string | null;
  updatedAt?: string | null;
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

export interface DevisCreatePayload {
  clientId: number;
  commentaire: string | null;
}

export interface DevisCreateResponse {
  message: string;
  id: number;
  numero: string;
}

export interface DevisUpdatePayload {
  clientId?: number;
  dateEmission?: string;
  dateValidite?: string;
  statut?: DevisStatus;
  commentaire?: string | null;
}

export interface DevisUpdateResponse {
  message: string;
  devis: Partial<DevisItem> & Pick<DevisItem, 'id' | 'numero'>;
}

export interface DevisLineCreatePayload {
  produitId: number;
  quantite: number;
  remise: number;
}

export interface DevisLineUpdatePayload {
  quantite?: number;
  remise?: number;
  prixUnitaireHT?: number;
  tva?: number;
  designation?: string;
  description?: string | null;
  unite?: string | null;
}

export interface DevisTotals {
  totalHT: string;
  totalTVA: string;
  totalTTC: string;
}

export interface DevisLineCreateResponse {
  message: string;
  ligne: DevisLine;
  totauxDevis: DevisTotals;
}

export interface DevisLineUpdateResponse {
  message: string;
  ligne: DevisLine;
  totauxDevis: DevisTotals;
}

export interface DevisLineDeleteResponse {
  message: string;
  totauxDevis: DevisTotals;
}

export interface DevisActionResponse {
  message: string;
}

export interface DevisDeleteResponse {
  message: string;
}

export interface DevisTransformResponse {
  message: string;
  devis: {
    id: number;
    numero: string;
    statut: DevisStatus;
  };
  facture: {
    id: number;
    numero: string;
    statut: string;
    dateEmission: string;
    dateEcheance: string;
    totalHT: string;
    totalTVA: string;
    totalTTC: string;
  };
}
