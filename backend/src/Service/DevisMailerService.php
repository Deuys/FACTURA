<?php

namespace App\Service;

use App\Entity\Devis;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

final class DevisMailerService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly DevisPdfService $devisPdfService,

        #[Autowire('%env(MAILER_FROM_EMAIL)%')]
        private readonly string $mailerFromEmail,

        #[Autowire('%env(MAILER_FROM_NAME)%')]
        private readonly string $mailerFromName,
    ) {}

    public function send(Devis $devis): void
    {
        $client = $devis->getClient();
        $clientEmail = $client?->getEmail();

        if ($client === null || empty($clientEmail)) {
            throw new \InvalidArgumentException(
                'Le client ne possède aucune adresse e-mail.'
            );
        }

        $pdf = $this->devisPdfService->generate($devis);
        $filename = $this->devisPdfService->generateFilename($devis);

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
                'Votre devis %s',
                $devis->getNumero()
            ))
            ->htmlTemplate('emails/devis.html.twig')
            ->context([
                'devis' => $devis,
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