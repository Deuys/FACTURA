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
