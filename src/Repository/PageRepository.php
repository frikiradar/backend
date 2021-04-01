<?php

namespace App\Repository;

use App\Entity\Page;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Exception;

/**
 * @method Page|null find($id, $lockMode = null, $lockVersion = null)
 * @method Page|null findOneBy(array $criteria, array $orderBy = null)
 * @method Page[]    findAll()
 * @method Page[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, EntityManagerInterface $entityManager)
    {
        parent::__construct($registry, Page::class);
        $this->em = $entityManager;
    }

    // /**
    //  * @return Page[] Returns an array of Page objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('p.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Page
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */

    public function createPage(string $query)
    {
        $search = trim(str_replace('Saga', '', $query));
        $search = str_replace(': ', ' ', $search);
        // $this->o->writeln($search);
        $clientId = '1xglmlbz31omgifwlnjzfjjw5bukv9';
        $clientSecret = 'niozz7jpskr27vr9c5v1go801q3wsz';
        $url = 'https://id.twitch.tv/oauth2/token?client_id=' . $clientId . '&client_secret=' . $clientSecret . '&grant_type=client_credentials';
        $ch = curl_init($url); // Initialise cURL
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, 1); // Specify the request method as POST
        $output = curl_exec($ch); // Execute the cURL statement
        curl_close($ch); // Close the cURL connection
        $info = json_decode($output, true); // Return the received data

        $url = 'https://api.igdb.com/v4';
        $endpoint = '/games';
        $bearer = "Authorization: Bearer " . $info['access_token']; // Prepare the authorisation token
        $client_id = "Client-ID: " . $clientId;
        // $body = 'search "' . $search . '"; fields name, cover.url, game_modes.slug, multiplayer_modes.*, rating, slug, summary, first_release_date, artworks.*; where version_parent = null; limit 500;';
        // $body = 'fields name, cover.url, game_modes.slug, multiplayer_modes.*, rating, slug, summary, first_release_date, artworks.*; where name ~ "' . $search . '" & version_parent = null; limit 500;';
        // $body = 'fields name, cover.url, game_modes.slug, multiplayer_modes.*, rating, slug, summary, first_release_date, artworks.*; where name ~ *"' . $search . '"* & version_parent = null; limit 500;';
        $body = 'fields name, cover.url, game_modes.slug, multiplayer_modes.*, rating, slug, summary, first_release_date, artworks.*; where slug ~ *"' . str_replace([':', ' '], '-', strtolower($search)) . '"* & version_parent = null; limit 500;';
        // $this->o->writeln($body);
        $ch = curl_init($url . $endpoint); // Initialise cURL
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array($bearer, $client_id)); // Inject the token into the header
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, 1); // Specify the request method as POST
        $output = curl_exec($ch); // Execute the cURL statement
        curl_close($ch); // Close the cURL connection
        $games = json_decode($output, true); // Return the received data
        usort($games, function ($a, $b) {
            return (isset($a['first_release_date']) ? $a['first_release_date'] : 99999999999) <=> (isset($b['first_release_date']) ? $b['first_release_date'] : 99999999999);
        });
        // print_r($games);
        $gameFound = [];

        foreach ($games as $game) {
            similar_text(strtolower($game['name']), strtolower($search), $percent);
            if ($percent >= 98) {
                $this->o->writeln($percent);
                $gameFound = $game;
                break;
            }
        }

        if (!empty($gameFound)) {
            $game = $gameFound;
        } else {
            $game = $games[0];
        }

        if (isset($game['artworks'][0]['url'])) {
            $game['artworks'][0]['url'] = str_replace('t_thumb', 't_screenshot_med', $game['artworks'][0]['url']);
        }
        if (isset($game['cover']['url']) && !isset($game['artworks'][0]['url'])) {
            $game['artworks'][0]['url'] = str_replace('t_thumb', 't_screenshot_med', $game['cover']['url']);
        }
        if (isset($game['cover']['url'])) {
            $game['cover']['url'] = str_replace('t_thumb', 't_cover_big', $game['cover']['url']);
        }

        if (isset($game['multiplayer_modes'][0]['onlinecoop']) && $game['multiplayer_modes'][0]['onlinecoop'] == 1) {
            $game['game_mode'] = 'online';
        }

        if (isset($game['game_modes'])) {
            foreach ($game['game_modes'] as $gameMode) {
                if ($gameMode['slug'] === 'massively-multiplayer-online-mmo') {
                    $game['game_mode'] = 'online';
                    break;
                }
            }
        }

        if (!isset($game['game_mode'])) {
            $game['game_mode'] = 'offline';
        }

        if (isset($game)) {
            /**
             * @var Page
             */
            $page = new Page();
            $page->setName($game['name']);
            $page->setDescription($game['summary']);
            $page->setSlug($game['slug']);
            $page->setRating($game['rating']);
            $page->setReleaseDate(new \DateTime($game['first_release_date']));
            $page->setTimeCreation();
            $page->setLastUpdate();
            $page->setGameMode($game['game_mode']);

            $server = "https://app.frikiradar.com";
            if (isset($game['cover'])) {
                // Obtenemos cover y la subimos tal cual está
                $file = '/var/www/vhosts/frikiradar.com/app.frikiradar.com/images/pages/' . $game['slug'] . '/cover.jpg';
                if (file_put_contents($file, file_get_contents('https:' . $game['cover']['url']))) {
                    $src = str_replace("/var/www/vhosts/frikiradar.com/app.frikiradar.com", $server, $file);
                    $page->setCover($src);
                }
            }

            if (isset($game['artworks'][0])) {
                // Obtenemos cover y la subimos tal cual está
                $file = '/var/www/vhosts/frikiradar.com/app.frikiradar.com/images/pages/' . $game['slug'] . '/artwork.jpg';
                if (file_put_contents($file, file_get_contents('https:' . $game['artworks'][0]['url']))) {
                    $src = str_replace("/var/www/vhosts/frikiradar.com/app.frikiradar.com", $server, $file);
                    $page->setArtwork($src);
                }
            }

            $this->em->persist($page);
            $this->em->flush();

            return $page;
        }

        throw new Exception("Error al crear la página");
    }
}
