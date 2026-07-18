<?php

namespace App\Service;

use App\Entity\Facture;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

final class FactureMailerService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly FacturePdfService $facturePdfService,

        #[Autowire('%env(MAILER_FROM_EMAIL)%')]
        private readonly string $mailerFromEmail,

        #[Autowire('%env(MAILER_FROM_NAME)%')]
        private readonly string $mailerFromName,
    ) {}

    public function send(Facture $facture): void
    {
        $client = $facture->getClient();
        $clientEmail = trim((string) $client?->getEmail());

        if ($client === null || $clientEmail === '') {
            throw new \InvalidArgumentException(
                'Le client ne possède aucune adresse e-mail.'
            );
        }

        $pdf = $this->facturePdfService->generate($facture);

        $filename = $this->facturePdfService
            ->generateFilename($facture);

        $clientName = $this->getClientName(
            $client->getPrenom(),
            $client->getNom(),
            $client->getEntreprise(),
            $clientEmail
        );

        $email = (new TemplatedEmail())
            ->from(new Address(
                $this->mailerFromEmail,
                $this->mailerFromName
            ))
            ->to(new Address(
                $clientEmail,
                $clientName
            ))
            ->subject(sprintf(
                'Votre facture %s',
                $facture->getNumero()
            ))
            ->htmlTemplate('emails/facture.html.twig')
            ->context([
                'facture' => $facture,
                'client' => $client,
            ])
            ->attach(
                $pdf,
                $filename,
                'application/pdf'
            );

        $this->mailer->send($email);
    }

    public function sendRelance(Facture $facture): void
    {
        $client = $facture->getClient();
        $clientEmail = trim((string) $client?->getEmail());

        if ($client === null || $clientEmail === '') {
            throw new \InvalidArgumentException(
                'Le client ne possède aucune adresse e-mail.'
            );
        }

        $numeroRelance = $facture->getNombreRelances() + 1;

        $pdf = $this->facturePdfService->generate(
            $facture,
            $numeroRelance
        );

        $filename = sprintf(
            'relance-%d-%s',
            $numeroRelance,
            $this->facturePdfService->generateFilename($facture)
        );

        $clientName = $this->getClientName(
            $client->getPrenom(),
            $client->getNom(),
            $client->getEntreprise(),
            $clientEmail
        );

        $email = (new TemplatedEmail())
            ->from(new Address(
                $this->mailerFromEmail,
                $this->mailerFromName
            ))
            ->to(new Address(
                $clientEmail,
                $clientName
            ))
            ->subject(sprintf(
                'Relance n°%d — Facture %s en retard',
                $numeroRelance,
                $facture->getNumero()
            ))
            ->htmlTemplate('emails/facture_relance.html.twig')
            ->context([
                'facture' => $facture,
                'client' => $client,
                'numeroRelance' => $numeroRelance,
            ])
            ->attach(
                $pdf,
                $filename,
                'application/pdf'
            );

        $this->mailer->send($email);
    }

    private function getClientName(
        ?string $prenom,
        ?string $nom,
        ?string $entreprise,
        string $email
    ): string {
        $clientName = trim(sprintf(
            '%s %s',
            $prenom ?? '',
            $nom ?? ''
        ));

        if ($clientName !== '') {
            return $clientName;
        }

        $entreprise = trim((string) $entreprise);

        return $entreprise !== ''
            ? $entreprise
            : $email;
    }
}
