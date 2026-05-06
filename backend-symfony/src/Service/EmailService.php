<?php

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class EmailService
{
    private string $fromEmail = 'mennahibi@moenn-technologies.com'; // ← remplace 
    private string $fromName  = 'ShopCRM';

    private MailerInterface $mailer;

    public function __construct(MailerInterface $mailer)
    {
    $this->mailer = $mailer;
    }

    public function sendEmail(string $to, string $subject, string $html): void
    {
        $email = (new Email())
            ->from(sprintf('%s <%s>', $this->fromName, $this->fromEmail))
            ->to($to)
            ->subject($subject)
            ->html($html);

        $this->mailer->send($email);
    }

    public function sendOrderConfirmation(array $c): void
    {
        $this->sendEmail(
            $c['emailClient'],
            "Confirmation de votre commande #{$c['ref']}",
            "
            <div style='font-family:DM Sans,sans-serif;max-width:600px;margin:auto;padding:24px'>
                <h2 style='color:#5a54e6'>Bonjour {$c['client']},</h2>
                <p>Votre commande a bien été enregistrée. Voici le récapitulatif :</p>
                <table style='width:100%;border-collapse:collapse;margin:16px 0'>
                    <tr style='background:#eef1f7'>
                        <td style='padding:10px;border:1px solid #e2e6f0'>Référence</td>
                        <td style='padding:10px;border:1px solid #e2e6f0'><strong>#{$c['ref']}</strong></td>
                    </tr>
                    <tr>
                        <td style='padding:10px;border:1px solid #e2e6f0'>Désignation</td>
                        <td style='padding:10px;border:1px solid #e2e6f0'>{$c['designation']}</td>
                    </tr>
                    <tr style='background:#eef1f7'>
                        <td style='padding:10px;border:1px solid #e2e6f0'>Montant</td>
                        <td style='padding:10px;border:1px solid #e2e6f0'><strong>{$c['prixVenteTotal']} DH</strong></td>
                    </tr>
                    <tr>
                        <td style='padding:10px;border:1px solid #e2e6f0'>Ville</td>
                        <td style='padding:10px;border:1px solid #e2e6f0'>{$c['ville']}</td>
                    </tr>
                </table>
                <p style='color:#6b7280;font-size:14px'>Merci pour votre confiance — <strong>ShopCRM</strong></p>
            </div>
            "
        );
    }

    public function sendLivraisonUpdate(array $l, array $c): void
    {
        $this->sendEmail(
            $c['emailClient'],
            "Mise à jour livraison — commande #{$c['ref']}",
            "
            <div style='font-family:DM Sans,sans-serif;max-width:600px;margin:auto;padding:24px'>
                <h2 style='color:#5a54e6'>Bonjour {$c['client']},</h2>
                <p>Votre commande <strong>#{$c['ref']}</strong> est en cours de livraison.</p>
                <table style='width:100%;border-collapse:collapse;margin:16px 0'>
                    <tr style='background:#eef1f7'>
                        <td style='padding:10px;border:1px solid #e2e6f0'>Transporteur</td>
                        <td style='padding:10px;border:1px solid #e2e6f0'><strong>{$l['transporteur']}</strong></td>
                    </tr>
                    <tr>
                        <td style='padding:10px;border:1px solid #e2e6f0'>Numéro de suivi</td>
                        <td style='padding:10px;border:1px solid #e2e6f0'><strong>{$l['numeroSuivi']}</strong></td>
                    </tr>
                    <tr style='background:#eef1f7'>
                        <td style='padding:10px;border:1px solid #e2e6f0'>Statut</td>
                        <td style='padding:10px;border:1px solid #e2e6f0'>{$l['statut']}</td>
                    </tr>
                </table>
                <p style='color:#6b7280;font-size:14px'>Merci pour votre confiance — <strong>ShopCRM</strong></p>
            </div>
            "
        );
    }

    public function sendRetourConfirmation(array $r): void
    {
        $this->sendEmail(
            $r['emailClient'],
            "Votre retour #{$r['ref']} a été enregistré",
            "
            <div style='font-family:DM Sans,sans-serif;max-width:600px;margin:auto;padding:24px'>
                <h2 style='color:#5a54e6'>Bonjour {$r['client']},</h2>
                <p>Votre demande de retour a bien été enregistrée.</p>
                <table style='width:100%;border-collapse:collapse;margin:16px 0'>
                    <tr style='background:#eef1f7'>
                        <td style='padding:10px;border:1px solid #e2e6f0'>Référence retour</td>
                        <td style='padding:10px;border:1px solid #e2e6f0'><strong>#{$r['ref']}</strong></td>
                    </tr>
                    <tr>
                        <td style='padding:10px;border:1px solid #e2e6f0'>Motif</td>
                        <td style='padding:10px;border:1px solid #e2e6f0'>{$r['motif']}</td>
                    </tr>
                    <tr style='background:#eef1f7'>
                        <td style='padding:10px;border:1px solid #e2e6f0'>Montant remboursé</td>
                        <td style='padding:10px;border:1px solid #e2e6f0'><strong>{$r['montantRembourse']} DH</strong></td>
                    </tr>
                </table>
                <p style='color:#6b7280;font-size:14px'>Merci pour votre confiance — <strong>ShopCRM</strong></p>
            </div>
            "
        );
    }
}