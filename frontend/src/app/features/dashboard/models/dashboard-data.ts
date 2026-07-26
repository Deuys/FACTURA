export interface DashboardPeriod {
  annee: number;
  mois: number;
  dateDebut: string;
  dateFin: string;
}

export interface DashboardData {
  periode: DashboardPeriod;
  facturesPayees: number;
  facturesEnAttente: number;
  facturesEnRetard: number;
  chiffreAffaires: string;
  montantAEncaisser: string;
  devisEnAttente: number;
  nouveauxClients: number;
}

export interface EvolutionChiffreAffairesDonnee {
  mois: string;
  numeroMois: number;
  annee: number;
  cle: string;
  montant: string;
}

export interface EvolutionChiffreAffaires {
  periode: string | null;
  annee: number | null;
  dateDebut: string;
  dateFin: string;
  total: string;
  donnees: EvolutionChiffreAffairesDonnee[];
}

export interface RepartitionFacture {
  statut: string;
  nombre: number;
  pourcentage: number;
}

export interface RepartitionFactures {
  totalDocuments: number;
  repartition: RepartitionFacture[];
}

export interface ActiviteRecente {
  id: number;
  type: string;
  titre: string;
  description: string;
  date: string | null;
}

export interface DashboardFacture {
  numero: string;
  client: string;
  montant: string;
  statut: string;
}

export interface DerniereFacture extends DashboardFacture {
  date: string | null;
}

export interface FactureEcheance extends DashboardFacture {
  dateEcheance: string | null;
}
