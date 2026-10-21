<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\ApiToken;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * B2-4b — À utiliser dans tout WebTestCase qui écrit (POST, PATCH, DELETE sous /api) :
 *
 *     use LogsInUsers;
 *     ...
 *     $this->logIn($this->client);                                          // utilisateur de test simple
 *     $alice = $this->logIn($this->client, 'alice@taskboard.local', ['ROLE_ADMIN']);
 *     $this->logOut($this->client);
 *
 * ⚠️ Pourquoi pas $client->loginUser() ? Avec un firewall STATELESS, loginUser() pose le token dans le conteneur,
 * mais le KernelBrowser redémarre le kernel entre deux requêtes : sans session pour le retrouver, le token est
 * perdu dès la 2e requête (→ 401). On crée donc un vrai ApiToken et on envoie "Authorization: Bearer ..."
 * sur TOUTES les requêtes du client : c'est aussi le vrai chemin d'authentification qui est testé.
 */
trait LogsInUsers
{
    /**
     * @param list<string> $roles
     */
    private function logIn(KernelBrowser $client, string $email = 'tester@taskboard.local', array $roles = []): User
    {
        $em = $client->getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);

        if (null === $user) {
            $user = new User($email, 'Tester', $roles); // pas de mot de passe : on ne se connecte jamais avec
            $em->persist($user);
        }

        [$token, $plainToken] = ApiToken::issueFor($user);
        $em->persist($token);
        $em->flush();

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . $plainToken);

        return $user;
    }

    private function logOut(KernelBrowser $client): void
    {
        $client->setServerParameter('HTTP_AUTHORIZATION', '');
    }
}
