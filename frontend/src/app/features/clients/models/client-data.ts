export type ClientFilter = 'tous' | 'nouveaux' | 'a_jour' | 'en_attente' | 'en_retard' | 'archives';

export type ClientSortField = 'nom' | 'entreprise' | 'createdAt' | 'ville';

export type SortOrder = 'ASC' | 'DESC';

export interface ClientPayload {
  nom: string;
  prenom: string | null;
  entreprise: string | null;
  email: string;
  telephone: string | null;
  adresse: string | null;
  codePostal: string | null;
  ville: string | null;
  pays: string;
  siret: string | null;
  tvaIntracom: string | null;
}

export interface ClientDetails extends ClientPayload {
  id: number;
  typeDelaiPaiement: string | null;
  delaiPaiement: number | null;
  createdAt: string | null;
  updatedAt: string | null;
  archivee: boolean;
}

export interface Client extends ClientDetails {
  nombreFactures: number;
  chiffreAffaires: string;
  montantEnCours: string;
  statut: string;
}

export interface ClientCreateResponse {
  message: string;
  id: number;
}

export interface ClientUpdateResponse {
  message: string;
  client: ClientDetails;
}

export interface ClientArchiveResponse {
  message: string;
  client: ClientDetails;
}

export interface ClientsFilters {
  recherche: string | null;
  filtre: ClientFilter;
  tri: ClientSortField;
  ordre: SortOrder;
}

export interface ClientsPagination {
  page: number;
  limit: number;
  total: number;
  nombrePages: number;
  nombreResultats: number;
  pagePrecedente: number | null;
  pageSuivante: number | null;
}

export interface ClientsResponse {
  filtres: ClientsFilters;
  pagination: ClientsPagination;
  clients: Client[];
}

export interface ClientsDashboard {
  totalClients: number;
  nouveauxClients: number;
  clientsEnAttente: number;
  clientsAJour: number;
  clientsEnRetard: number;
}
