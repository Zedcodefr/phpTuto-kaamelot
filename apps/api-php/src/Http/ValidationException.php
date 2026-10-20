<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Erreur de validation (422). Le Lot C la lance avec la liste des erreurs,
 * le Lot A la formate.
 *
 * Propriétaire : 🅰️ Lot A. Le constructeur est FOURNI.
 */
final class ValidationException extends HttpException
{
    /**
     * @param array<string, string> $errors champ => message, ex. ['title' => 'This value should not be blank.']
     */
    public function __construct(public readonly array $errors)
    {
        parent::__construct(422, 'Validation failed');
    }

    /**
     * 🅰️ LOT A — Le corps de HttpException + une clé "violations" :
     * [['propertyPath' => 'title', 'title' => 'This value should not be blank.'], ...]
     */
    protected function toArray(): array
    {
        throw new \LogicException('TODO Lot A : ValidationException::toArray()');
    }
}
