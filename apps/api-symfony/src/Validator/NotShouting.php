<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;

/**
 * A2 — La contrainte : une simple classe-attribut qui porte la configuration (seuil, message).
 * La logique est dans NotShoutingValidator, trouvé par convention de nom (validatedBy() = classe + "Validator").
 * ≈ une ValidationAttribute C#, mais séparée en deux : la déclaration et le code qui vérifie.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
final class NotShouting extends Constraint
{
    public string $message = 'Le titre ne doit pas être écrit entièrement en majuscules.';

    #[HasNamedArguments]
    public function __construct(
        public int $minLetters = 5,
        ?string $message = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(null, $groups, $payload);
        $this->message = $message ?? $this->message;
    }
}
