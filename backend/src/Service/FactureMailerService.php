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
        $clientEmail = $client?->getEmail();

        if ($client === null || empty($clientEmail)) {
            throw new \InvalidArgumentException(
                'Le client ne possède aucune adresse e-mail.'
            );
        }

        $pdf = $this->facturePdfService->generate($facture);
        $filename = $this->facturePdfService->generateFilename($facture);

        $clientName = trim(sprintf(
            '%s %s',
            $client->getPrenom() ?? '',
            $client->getNom() ?? ''
        ));

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
}
