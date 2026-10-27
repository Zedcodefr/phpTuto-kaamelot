<?php

declare(strict_types=1);

namespace App\Tests\Labo;

use App\Kata\Entity\Author;
use App\Kata\Entity\Book;
use App\Kata\Repository\AuthorRepository;
use App\Kata\Repository\BookRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Middleware\Debug\DebugDataHolder;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Base des expériences du labo Doctrine (make sf-labo).
 *
 * Principe : vous notez votre pronostic dans pronostics.php, le test exécute l'expérience,
 * affiche le SQL réellement envoyé à PostgreSQL, puis compare avec votre pronostic.
 *
 * Le journal SQL vient du même collecteur que l'onglet Doctrine du profiler
 * (seuls les alias de colonnes sont retirés pour la lisibilité).
 * Données : celles du kata (src/DataFixtures/KataFixtures.php). Chaque test tourne dans une
 * transaction annulée à la fin (DAMA) : les expériences qui écrivent ne cassent rien.
 */
abstract class LaboTestCase extends KernelTestCase
{
    protected EntityManagerInterface $em;
    protected BookRepository $books;
    protected AuthorRepository $authors;

    private DebugDataHolder $sqlLog;
    /** @var list<string> le journal affiché à la fin de l'expérience */
    private array $journal = [];
    private int $sqlAffichees = 0;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->books = $container->get(BookRepository::class);
        $this->authors = $container->get(AuthorRepository::class);
        $this->sqlLog = $container->get('doctrine.debug_data_holder');

        if (0 === $this->books->count([])) {
            self::markTestSkipped('Données du labo absentes : lancez `make sf-labo` (et non phpunit directement).');
        }
    }

    /**
     * Renvoie le pronostic noté dans pronostics.php. Pas de pronostic = pas d'expérience :
     * on ne regarde pas la réponse avant d'avoir parié.
     *
     * @return array<string, mixed>
     */
    protected function pronostic(string $experience): array
    {
        $pronostic = (require __DIR__.'/pronostics.php')[$experience];
        $manquants = array_keys(array_filter($pronostic, fn ($valeur) => null === $valeur));
        if ([] !== $manquants) {
            self::markTestSkipped(sprintf(
                "%s : notez d'abord votre pronostic dans tests/Labo/pronostics.php (%s).",
                $experience,
                implode(', ', $manquants),
            ));
        }

        return $pronostic;
    }

    /**
     * Point de départ propre : Identity Map vide, compteur SQL à zéro.
     * Tout ce qui est chargé avant cet appel (pour préparer l'expérience) ne compte pas.
     */
    protected function demarrer(): void
    {
        $this->em->clear();
        $this->sqlLog->reset();
        $this->sqlAffichees = 0;
    }

    /** Nombre de requêtes SQL envoyées depuis demarrer() (hors transactions et savepoints). */
    protected function requetes(): int
    {
        return \count($this->sqlRequetes());
    }

    /** Nombre de requêtes SQL d'un type donné (SELECT, INSERT, UPDATE, DELETE) depuis demarrer(). */
    protected function requetesDeType(string $type): int
    {
        return \count(array_filter($this->sqlRequetes(), fn (string $sql) => str_starts_with($sql, $type.' ')));
    }

    /** Note l'étape qui vient de s'exécuter dans le journal, suivie du SQL qu'elle a envoyé. */
    protected function noter(string $etape): void
    {
        $this->journal[] = '▶ '.$etape;
        $nouvelles = \array_slice($this->sqlRequetes(), $this->sqlAffichees);
        foreach ($nouvelles as $sql) {
            $this->journal[] = '     SQL  '.$sql;
        }
        if ([] === $nouvelles) {
            $this->journal[] = '     (aucune requête)';
        }
        $this->sqlAffichees += \count($nouvelles);
    }

    /** Ajoute un commentaire sous l'étape notée en dernier (résultat, exception…). */
    protected function commenter(string $commentaire): void
    {
        $this->journal[] = '     → '.$commentaire;
    }

    /**
     * Affiche le journal, puis compare le pronostic à la réalité.
     *
     * @param array<string, mixed> $pronostic
     * @param array<string, mixed> $realite
     */
    protected function verdict(array $pronostic, array $realite): void
    {
        $lignes = ['', '──── '.substr($this->name(), 4).' ────', ...$this->journal, ''];
        $erreurs = 0;
        foreach ($realite as $cle => $valeur) {
            $juste = $pronostic[$cle] === $valeur;
            $erreurs += $juste ? 0 : 1;
            $lignes[] = sprintf(
                '  %s %-26s pronostic : %-20s réalité : %s',
                $juste ? '✅' : '❌',
                $cle,
                self::afficher($pronostic[$cle]),
                self::afficher($valeur),
            );
        }
        fwrite(\STDOUT, implode(\PHP_EOL, $lignes).\PHP_EOL.\PHP_EOL);

        if ($erreurs > 0) {
            self::fail(sprintf(
                '%d pronostic(s) faux sur %d. Relisez le journal : quelle ligne a envoyé (ou pas) du SQL, et pourquoi ?',
                $erreurs,
                \count($realite),
            ));
        }
        $this->addToAssertionCount(1);
    }

    /** Nombre d'objets Book gérés par l'EntityManager (les proxys d'auteurs ne comptent pas). */
    protected function livresEnMemoire(): int
    {
        return \count($this->em->getUnitOfWork()->getIdentityMap()[Book::class] ?? []);
    }

    protected function book(string $title): Book
    {
        return $this->books->findOneBy(['title' => $title]) ?? throw new \LogicException("Livre $title absent des fixtures");
    }

    protected function author(string $name): Author
    {
        return $this->authors->findOneBy(['name' => $name]) ?? throw new \LogicException("Auteur $name absent des fixtures");
    }

    /** @return list<string> */
    private function sqlRequetes(): array
    {
        $sql = [];
        foreach ($this->sqlLog->getData() as $requetes) {
            foreach ($requetes as $requete) {
                $texte = $requete['sql'];
                // Transactions et savepoints (flush, isolation des tests) : ce ne sont pas des requêtes métier
                if (str_starts_with($texte, '"') || preg_match('/^(SAVEPOINT|RELEASE|ROLLBACK)\b/', $texte)) {
                    continue;
                }
                // Les alias de colonnes (t0.title AS title_3) sont retirés pour la lisibilité
                $sql[] = preg_replace('/ AS \w+/', '', $texte).self::parametres($requete['params'] ?? []);
            }
        }

        return $sql;
    }

    /** @param array<mixed> $params */
    private static function parametres(array $params): string
    {
        if ([] === $params) {
            return '';
        }

        return '   ← ['.implode(', ', array_map(self::afficher(...), $params)).']';
    }

    private static function afficher(mixed $valeur): string
    {
        return match (true) {
            null === $valeur => 'null',
            \is_bool($valeur) => $valeur ? 'true' : 'false',
            \is_string($valeur) => "'$valeur'",
            \is_scalar($valeur) => (string) $valeur,
            $valeur instanceof \DateTimeInterface => $valeur->format('Y-m-d'),
            default => get_debug_type($valeur),
        };
    }
}
