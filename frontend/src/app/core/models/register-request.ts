export interface RegisterRequest {
  prenom: string;
  nom: string;
  nomEntreprise: string;
  email: string;
  password: string;
  passwordConfirmation: string;
  acceptTerms: boolean;
  acceptPrivacy: boolean;
}
