export interface RegisterResponse {
  message: string;
  user: {
    id: number;
    prenom: string;
    nom: string;
    email: string;
    roles: string[];
  };
  entreprise: {
    id: number;
    nom: string;
    complete: boolean;
  } | null;
}
