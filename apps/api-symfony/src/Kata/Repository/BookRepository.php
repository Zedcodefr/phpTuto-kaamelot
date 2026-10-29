<?php

declare(strict_types=1);

namespace App\Kata\Repository;

use App\Kata\Dto\BookSummary;
use App\Kata\Entity\Author;
use App\Kata\Entity\Book;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\OffsetPaginator;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\ORM\Tools\Pagination\Window;
use Doctrine\Persistence\ManagerRegistry;

/**
 * KATA DOCTRINE & DQL — À COMPLÉTER.
 * Lancez `make sf-kata` (ou `make sf-kata c="--filter=K05"`) et faites passer les tests un par un.
 * Expérimentez vos requêtes avec `make dql q="SELECT ..."` et regardez le SQL généré dans le profiler.
 *
 * @extends ServiceEntityRepository<Book>
 */
class BookRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Book::class);
    }

    /**
     * K01 — Tous les livres, triés par titre (A → Z).
     * Notions : createQueryBuilder(), orderBy(), getQuery()->getResult().
     *
     * @return list<Book>
     */
    public function k01FindAllOrderedByTitle(): array
    {
        return $this->createQueryBuilder('b')
            ->orderBy('b.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * K02 — Les livres dont l'auteur porte exactement ce nom, triés par titre.
     * Notions : join() sur une RELATION (b.author), where() + paramètre nommé (:name) + setParameter().
     * ⚠️ Jamais de concaténation de valeur dans une requête.
     *
     * @return list<Book>
     */
    public function k02FindByAuthorName(string $name): array
    {
        return $this->createQueryBuilder('b')
            ->join('b.author', 'a')
            ->where('a.name = :name')
            ->setParameter('name', $name)
            ->orderBy('b.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * K03 — Les livres publiés entre $from et $to (bornes incluses), du plus ancien au plus récent.
     * Notions : BETWEEN, paramètres de type date (Doctrine convertit les DateTimeImmutable).
     *
     * @return list<Book>
     */
    public function k03FindPublishedBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.publishedAt BETWEEN :from AND :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('b.publishedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * K04 — Les livres d'au moins $minPages pages, les plus longs d'abord.
     * ⚠️ Contrainte : écrire la requête en DQL "à la main" avec $this->getEntityManager()->createQuery('SELECT ...'),
     * sans QueryBuilder. On interroge des CLASSES (App\Kata\Entity\Book) et des PROPRIÉTÉS, pas des tables.
     *
     * @return list<Book>
     */
    public function k04FindWithAtLeastPages(int $minPages): array
    {
        return $this->getEntityManager()
            ->createQuery('SELECT b FROM ' . Book::class . ' b WHERE b.pages >= :minPages ORDER BY b.pages DESC')
            ->setParameter('minPages', $minPages)
            ->getResult();
    }

    /**
     * K05 — Nombre de livres par auteur, AUTEURS SANS LIVRE COMPRIS, trié par nom d'auteur.
     * Format : ['Agatha Christie' => 2, ..., 'Margaret Atwood' => 0, ...]
     * Notions : partir de Author, LEFT JOIN, COUNT(), GROUP BY, résultats scalaires (getArrayResult / getScalarResult).
     * Indice : COUNT() peut revenir sous forme de string selon le pilote : convertir en int.
     *
     * @return array<string, int>
     */
    public function k05CountBooksPerAuthor(): array
    {
        $query = $this->getEntityManager()->createQueryBuilder()->select('a')->from(Author::class, 'a')
                    ->leftJoin('a.books', 'b')
                    ->groupBy('a.id')
                    ->orderBy('a.name', 'ASC')
                    ->getQuery()
                    ->getResult();

        $result = [];
        foreach($query as $author) {
            $result[$author->getName()] = (int) count($author->getBooks());
        }
        return $result;
    }

    /**
     * K06 — Le livre d'id $id AVEC ses critiques chargées dans la MÊME requête (null si absent).
     * Notions : "fetch join" = leftJoin() + addSelect(). Sans addSelect, les critiques seraient chargées plus tard
     * en lazy loading (une requête de plus par livre : le fameux problème N+1).
     * getOneOrNullResult().
     */
    public function k06FindOneWithReviews(int $id): ?Book
    {
       return $this->createQueryBuilder('b')
           ->leftJoin('b.reviews', 'r')
           ->addSelect('r')
           ->where('b.id = :id')
           ->setParameter('id', $id)
           ->getQuery()
           ->getOneOrNullResult();
    }

    /**
     * K07 — Pour chaque livre ayant une note moyenne >= $minAverage : ['title' => ..., 'average' => float arrondi à 2 décimales].
     * Tri : moyenne décroissante, puis titre.
     * Notions : select() de champs (pas d'entité), AVG(), groupBy(), having(), alias dans orderBy().
     *
     * @return list<array{title: string, average: float}>
     */
    public function k07FindAverageRatingsAtLeast(float $minAverage): array
    {
        $qb = $this->createQueryBuilder('b')
            ->select('b.title, AVG(r.rating) as average')
            ->leftJoin('b.reviews', 'r')
            ->groupBy('b.id')
            ->having('AVG(r.rating) >= :minAverage')
            ->setParameter('minAverage', $minAverage)
            ->orderBy('average', 'DESC')
            ->addOrderBy('b.title', 'ASC');

        $query = $qb->getQuery()->getArrayResult();

        return array_map(fn($row) => ['title' => $row['title'], 'average' => round((float) $row['average'], 2)], $query);
    }

    /**
     * K08 — Les livres portant l'étiquette $tagName, triés par titre.
     * Notions : jointure sur une relation ManyToMany (Doctrine gère la table de jointure kata_book_tag pour vous).
     *
     * @return list<Book>
     */
    public function k08FindByTag(string $tagName): array
    {
        return $this->createQueryBuilder('b')
            ->innerJoin('b.tags', 't')
            ->where('t.name = :tagName')
            ->setParameter('tagName', $tagName)
            ->orderBy('b.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * K09 — Recherche multicritère, triée par titre. Chaque critère null est IGNORÉ :
     *   - $text : contenu dans le titre, insensible à la casse
     *   - $authorCountry : pays exact de l'auteur
     *   - $outOfPrint : true / false
     * Notions : construire la requête dynamiquement avec andWhere(), LOWER(), LIKE avec des % dans le PARAMÈTRE.
     * C'est exactement ce dont le Dev Back 3 aura besoin pour les filtres de GET /api/tasks.
     *
     * @return list<Book>
     */
    public function k09Search(?string $text = null, ?string $authorCountry = null, ?bool $outOfPrint = null): array
    {
        $qb = $this->createQueryBuilder('b')
                ->orderBy('b.title', 'ASC');

        if ($text !== null) {
            $qb->andWhere('LOWER(b.title) LIKE :text')
               ->setParameter('text', '%' . strtolower($text) . '%');
        }
        if ($authorCountry !== null) {
            $qb->innerJoin('b.author', 'a')
            ->andWhere('a.country = :authorCountry')
               ->setParameter('authorCountry', $authorCountry);
        }
        if ($outOfPrint !== null) {
            $qb->andWhere('b.outOfPrint = :outOfPrint')
               ->setParameter('outOfPrint', $outOfPrint);
        }
        return $qb->getQuery()
                  ->getResult();
    }

    /**
     * K10 — Une page de livres triés par titre + le nombre TOTAL de livres.
     * Format : ['items' => list<Book>, 'total' => int]
     * Notions : setFirstResult() / setMaxResults(), Doctrine\ORM\Tools\Pagination\Paginator (count() + itération).
     * Pourquoi le Paginator plutôt que setMaxResults seul ? (indice : fetch joins sur collections)
     *
     * @return array{items: list<Book>, total: int}
     */
    public function k10Paginate(int $page, int $limit): array
    {
        $qb = $this->createQueryBuilder('b')
        ->orderBy('b.title', 'ASC');

        //Utiliser OffsetPaginator, Doctrine\ORM\Tools\Pagination\Paginator est déprécié
        $result = (new OffsetPaginator())
        ->paginate($qb, Window::fromPageNumberAndSize($page, $limit));

        return [
            'items' => $result->getItems(),
            'total' => $result->getTotalCount(),
        ];
    }

    /**
     * K11 — Un BookSummary par livre (titre, nom de l'auteur, nombre de critiques), trié par titre.
     * Notions : SELECT NEW App\Kata\Dto\BookSummary(...) en DQL : Doctrine instancie directement le DTO.
     * Avantage : pas d'entité chargée ni suivie par l'Unit of Work → lecture légère.
     *
     * @return list<BookSummary>
     */
    public function k11FindSummaries(): array
    {
        throw new \LogicException('TODO K11');
    }

    /**
     * K12 — Marque comme épuisés (outOfPrint = true) les livres publiés avant $date qui ne l'étaient pas encore.
     * Retourne le nombre de livres modifiés.
     * Notions : UPDATE en DQL + execute() : une seule requête SQL, aucune entité chargée.
     * ⚠️ Les entités déjà en mémoire ne sont PAS mises à jour (l'Unit of Work est contourné).
     */
    public function k12MarkOutOfPrintPublishedBefore(\DateTimeImmutable $date): int
    {
        throw new \LogicException('TODO K12');
    }

    /**
     * K13 — Les livres qui n'ont reçu aucune critique, triés par titre.
     * Notions : LEFT JOIN + IS NULL (ou une sous-requête NOT EXISTS, ou SIZE()).
     *
     * @return list<Book>
     */
    public function k13FindNeverReviewed(): array
    {
        throw new \LogicException('TODO K13');
    }
}
