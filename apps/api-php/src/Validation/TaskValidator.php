<?php

declare(strict_types=1);

namespace App\Validation;

use App\Http\ValidationException;

/**
 * 🅲 LOT C — Validation des données reçues en JSON (règles : docs/api-contract.md).
 *
 * Messages à utiliser (identiques à Symfony, pour que le front se comporte pareil avec les deux API) :
 *   title vide / absent / pas une string  : "This value should not be blank."
 *   title < 3 ou > 255 caractères         : "This value should have between 3 and 255 characters."
 *   description pas une string           : "This value should be of type string."
 *   description > 2000 caractères         : "This value is too long. It should have 2000 characters or less."
 *   status inconnu                        : "The value should be one of: todo, in_progress, done."
 * Attention : longueur en CARACTÈRES, pas en octets (UTF-8).
 */
final class TaskValidator
{
    /**
     * @param array<string, mixed> $data    le JSON décodé
     * @param bool                 $partial true pour un PATCH : seuls les champs présents sont validés
     *
     * @throws ValidationException avec TOUTES les erreurs trouvées (pas seulement la première)
     */
    public function validate(array $data, bool $partial = false): void
    {
        throw new \LogicException('TODO Lot C : TaskValidator::validate()');
    }
}
