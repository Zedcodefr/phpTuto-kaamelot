<?php

/**
 * Mini-framework de vérification utilisé par les scripts lot-a / lot-b / lot-c.
 * (Volontairement simple : c'est un avant-goût de PHPUnit, vu en partie 2.)
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require __DIR__ . '/Fake/InMemoryTaskRepository.php';

final class Checks
{
    private static int $passed = 0;
    private static int $failed = 0;
    private static int $todo = 0;

    public static function title(string $title): void
    {
        echo "\n\033[1m$title\033[0m\n";
    }

    public static function section(string $name): void
    {
        echo "\n$name\n";
    }

    /**
     * @param callable(): mixed $test renvoie true si OK ; false ou une exception sinon
     */
    public static function check(string $label, callable $test): void
    {
        try {
            $result = $test();
        } catch (\LogicException $e) {
            if (str_starts_with($e->getMessage(), 'TODO')) {
                self::$todo++;
                echo "  \033[33m… $label\033[0m \033[2m({$e->getMessage()})\033[0m\n";

                return;
            }
            $result = $e;
        } catch (\Throwable $e) {
            $result = $e;
        }

        if ($result === true) {
            self::$passed++;
            echo "  \033[32m✔\033[0m $label\n";

            return;
        }

        self::$failed++;
        echo "  \033[31m✘ $label\033[0m\n";
        if ($result instanceof \Throwable) {
            echo '      ' . get_class($result) . ' : ' . $result->getMessage()
                . ' (' . basename($result->getFile()) . ':' . $result->getLine() . ")\n";
        }
    }

    /**
     * Exécute $fn et renvoie l'exception attendue (ou null si rien n'a été lancé).
     *
     * @template T of \Throwable
     *
     * @param class-string<T> $class
     *
     * @return T|null
     */
    public static function catch(string $class, callable $fn): ?\Throwable
    {
        try {
            $fn();
        } catch (\LogicException $e) {
            if (str_starts_with($e->getMessage(), 'TODO')) {
                throw $e;
            }
            if ($e instanceof $class) {
                return $e;
            }
            throw $e;
        } catch (\Throwable $e) {
            if ($e instanceof $class) {
                return $e;
            }
            throw $e;
        }

        return null;
    }

    public static function end(): never
    {
        $total = self::$passed + self::$failed + self::$todo;
        echo "\n";
        if (self::$failed === 0 && self::$todo === 0) {
            echo "\033[42;30m LOT TERMINÉ \033[0m " . self::$passed . "/$total vérifications OK 🎉\n\n";
            exit(0);
        }
        printf(
            "\033[1m%d/%d OK\033[0m · \033[33m%d à implémenter\033[0m · \033[31m%d en échec\033[0m\n\n",
            self::$passed,
            $total,
            self::$todo,
            self::$failed,
        );
        exit(1);
    }
}
