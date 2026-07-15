<?php

namespace App\Service;

use App\Entity\Facture;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

final class FacturePdfService
{
    public function __construct(
        private readonly Environment $twig,
    ) {}

    /**
     * Génère le contenu binaire PDF d'une facture.
     */
    public function generate(Facture $facture): string
    {
        $html = $this->twig->render('pdf/facture.html.twig', [
            'facture' => $facture,
        ]);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);

        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Génère un nom de fichier sûr et cohérent.
     */
    public function generateFilename(Facture $facture): string
    {
        $numero = $facture->getNumero() ?? sprintf(
            'facture-%d',
            $facture->getId() ?? 0
        );

        $safeNumero = preg_replace(
            '/[^a-zA-Z0-9_-]+/',
            '-',
            $numero
        );

        return sprintf(
            'facture-%s.pdf',
            trim((string) $safeNumero, '-')
        );
    }
}
