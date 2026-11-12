<?php
/*
 * Projet  : Architecture & Engineering Services
 * Auteur  : OUNIADON HAIROULAH (ID: 411030513)
 * Fichier : src/helpers.php
 * Rôle    : Petites fonctions d'affichage partagées par toutes les pages.
 */
declare(strict_types=1);

/** Échappe une valeur pour l'affichage HTML (protection XSS). */
function e(string|int|float|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/** Formate un montant en dollars : 1200 -> "$1 200". */
function usd(float|string|null $amount): string
{
    return $amount === null ? '—' : '$' . number_format((float) $amount, 0, ',', ' ');
}

/** Formate une date SQL en français : 2026-11-12 14:05:00 -> "12/11/2026 14:05". */
function frDate(?string $sqlDate, bool $withTime = true): string
{
    if ($sqlDate === null || $sqlDate === '') {
        return '—';
    }
    $date = new DateTimeImmutable($sqlDate);
    return $date->format($withTime ? 'd/m/Y H:i' : 'd/m/Y');
}
