<?php

namespace App\Service;

use App\Entity\Devis;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

final class DevisPdfService
{
    public function __construct(
        private readonly Environment $twig,
    ) {
    }

    /**
     * Génère le contenu binaire PDF d'un devis.
     */
    public function generate(Devis $devis): string
    {
        $html = $this->twig->render('pdf/devis.html.twig', [
            'devis' => $devis,
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
    public function generateFilename(Devis $devis): string
    {
        $numero = $devis->getNumero() ?? sprintf(
            'devis-%d',
            $devis->getId() ?? 0
        );

        $safeNumero = preg_replace(
            '/[^a-zA-Z0-9_-]+/',
            '-',
            $numero
        );

        return sprintf(
            'devis-%s.pdf',
            trim((string) $safeNumero, '-')
        );
    }
}