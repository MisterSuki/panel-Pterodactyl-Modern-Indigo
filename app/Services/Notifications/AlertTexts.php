<?php

namespace Pterodactyl\Services\Notifications;

/**
 * The texts of the messages the panel writes by itself (alerts, tickets, shop), in the language of the person who gets
 * them. {name} and the other words in braces are replaced.
 */
class AlertTexts
{
    private const TEXTS = [
        'crash' => [
            'en' => ['{name} stopped unexpectedly', 'It was not stopped from the panel. Look at its console to know why.'],
            'fr' => ['{name} s’est arrêté de façon inattendue', 'Il n’a pas été arrêté depuis le panel. Regardez sa console pour savoir pourquoi.'],
        ],
        'crash_restart' => [
            'en' => ['{name} crashed and was restarted', 'The server stopped by itself and was started again automatically.'],
            'fr' => ['{name} a planté et a redémarré', 'Le serveur s’est arrêté tout seul et a été relancé automatiquement.'],
        ],
        'start' => [
            'en' => ['{name} started', 'The server is running.'],
            'fr' => ['{name} a démarré', 'Le serveur est en marche.'],
        ],
        'stop' => [
            'en' => ['{name} was stopped', 'The server was stopped from the panel.'],
            'fr' => ['{name} a été arrêté', 'Le serveur a été arrêté depuis le panel.'],
        ],
        'memory' => [
            'en' => ['{name} is almost out of memory', '{used} of {limit} used.'],
            'fr' => ['{name} manque bientôt de mémoire', '{used} utilisés sur {limit}.'],
        ],
        'disk' => [
            'en' => ['{name} is almost out of disk space', '{used} of {limit} used.'],
            'fr' => ['{name} n’a bientôt plus d’espace disque', '{used} utilisés sur {limit}.'],
        ],
        'test' => [
            'en' => ['Test of the alerts of {name}', 'The alerts of this server reach this channel.'],
            'fr' => ['Test des alertes de {name}', 'Les alertes de ce serveur arrivent bien dans ce salon.'],
        ],
        'ticket_reply' => [
            'en' => ['New answer to your ticket', '#{id} {subject}'],
            'fr' => ['Nouvelle réponse à votre ticket', '#{id} {subject}'],
        ],
        'shop_expired' => [
            'en' => ['{name} was suspended: its time ran out', 'Renew it in the shop to get it back. Its files are kept.'],
            'fr' => ['{name} a été suspendu : sa durée est écoulée', 'Renouvelez-le dans la boutique pour le récupérer. Ses fichiers sont conservés.'],
        ],
    ];

    /**
     * @return array{0: string, 1: string}
     */
    public static function get(string $key, ?string $language, array $vars = []): array
    {
        $set = self::TEXTS[$key] ?? ['en' => [$key, '']];
        $pair = $set[$language] ?? $set['en'];
        $replace = [];
        foreach ($vars as $name => $value) {
            $replace['{' . $name . '}'] = (string) $value;
        }

        return [strtr($pair[0], $replace), strtr($pair[1], $replace)];
    }
}
