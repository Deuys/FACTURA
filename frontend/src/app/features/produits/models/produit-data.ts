export type ProduitFilter = 'tous' | 'produits' | 'services' | 'archives';

export type ProduitSortField = 'nom' | 'reference' | 'prixHT' | 'createdAt';

export type ProduitSortOrder = 'ASC' | 'DESC';

export interface ProduitItem {
  id: number;
  nom: string;
  description: string | null;
  type: 'produit' | 'service';
  reference: string;
  prixHT: string;
  tva: string;
  unite: string | null;
  actif: boolean;
  createdAt?: string | null;
  updatedAt?: string | null;
}

export interface ProduitsPagination {
  page: number;
  limit: number;
  total: number;
  nombrePages: number;
  nombreResultats: number;
  pagePrecedente: number | null;
  pageSuivante: number | null;
}

export interface ProduitsResponse {
  filtres: {
    recherche: string;
    filtre: ProduitFilter;
    tri: ProduitSortField;
    ordre: ProduitSortOrder;
  };
  pagination: ProduitsPagination;
  produits: ProduitItem[];
}
