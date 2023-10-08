<?php

namespace App\Repository;

use App\Entity\Page;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Statickidz\GoogleTranslate;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Filesystem\Filesystem;
use App\Service\FileUploaderService;

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
        $this->slugs = [];
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

    public function findPages(User $user)
    {
        $userTags = $user->getTags();
        $userSlugs = [];
        foreach ($userTags as $tag) {
            if ($tag->getSlug()) {
                $userSlugs[] = $tag->getSlug();
            }
        }

        $tags = $this->em->getRepository("App:Tag")->createQueryBuilder('t')
            ->select(array(
                't.slug',
                'COUNT(t) total'
            ))
            ->andWhere('t.slug IN (:slugs)')
            ->groupBy('t.slug')
            ->orderBy('total', 'DESC')
            ->setParameter('slugs', $userSlugs)
            ->getQuery()
            ->getResult();

        $slugs = [];
        foreach ($tags as $tag) {
            $slugs[$tag['slug']] = $tag['total'];
        }
        $this->slugs = $slugs;

        $pages = $this->createQueryBuilder('p')
            ->where('p.slug IN (:slugs)')
            ->setParameter('slugs', array_keys($slugs))
            ->getQuery()
            ->getResult();

        usort($pages, function ($a, $b) {
            return $this->slugs[$b->getSlug()] <=> $this->slugs[$a->getSlug()];
        });

        return $pages;
    }

    public function getGamesApi($name)
    {
        $search = strtolower($name);
        if ($search == 'lol') {
            $search = 'league of legends';
        }
        if ($search == 'wow') {
            $search = 'world of warcraft';
        }

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
        $body = 'search "' . $search . '"; fields name, cover.url, game_modes.slug, multiplayer_modes.*, aggregated_rating, slug, summary, first_release_date, involved_companies.company.name, involved_companies.developer, artworks.*; where version_parent = null; limit 500;';

        $ch = curl_init($url . $endpoint); // Initialise cURL
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array($bearer, $client_id)); // Inject the token into the header
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, 1); // Specify the request method as POST
        $output = curl_exec($ch); // Execute the cURL statement
        curl_close($ch); // Close the cURL connection
        $games = json_decode($output, true); // Return the received data

        if (empty($games)) {
            $search = $this->nameToSlug($search);

            $body = 'fields name, cover.url, game_modes.slug, multiplayer_modes.*, aggregated_rating, slug, summary, first_release_date, involved_companies.company.name, involved_companies.developer, artworks.*; where slug ~ *"' . $search . '"* & version_parent = null; limit 500;';
            $ch = curl_init($url . $endpoint); // Initialise cURL
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array($bearer, $client_id)); // Inject the token into the header
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, 1); // Specify the request method as POST
            $output = curl_exec($ch); // Execute the cURL statement
            curl_close($ch); // Close the cURL connection
            $games = json_decode($output, true); // Return the received data
        }

        usort($games, function ($a, $b) {
            return (isset($a['first_release_date']) ? $a['first_release_date'] : 99999999999) <=> (isset($b['first_release_date']) ? $b['first_release_date'] : 99999999999);
        });
        // print_r($games);

        if (!empty($games)) {
            $gameFound = [];

            foreach ($games as $game) {
                if ($game['slug'] === $search) {
                    $gameFound = $game;
                    break;
                } else {
                    similar_text(strtolower($game['name']), strtolower($search), $percent);
                    if ($percent >= 98) {
                        $gameFound = $game;
                        break;
                    }
                }
            }

            if (!empty($gameFound)) {
                $game = $gameFound;
            } else {
                $game = $games[0];
            }
        }

        if (isset($game)) {
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

            if (isset($game['involved_companies'])) {
                foreach ($game['involved_companies'] as $company) {
                    if ($company['developer']) {
                        $game['developer'] = $company['company']['name'];
                    }
                }
            }

            if (isset($game['summary'])) {
                $trans = new GoogleTranslate();
                $text = strlen($game['summary']) > 2000 ? (substr($game['summary'], 0, 1999) . '...') : $game['summary'];
                try {
                    $game['summary'] = $trans->translate('en', 'es', $text);
                } catch (Exception $ex) {
                    // Omitimos traducción si falla, lo metemos en inglés
                }
            }

            // Imágenes
            $server = "https://app.frikiradar.com";
            $absolutePath = '/var/www/vhosts/frikiradar.com/app.frikiradar.com/images/pages/games/';
            if (isset($game['cover'])) {
                $filename =  'cover.jpg';
                if (!file_exists($absolutePath . $game['slug'])) {
                    mkdir($absolutePath . $game['slug'], 0777, true);
                }

                $uploader = new FileUploaderService($absolutePath . $game['slug'], $filename);
                $image = $uploader->uploadImage('https:' . $game['cover']['url'], false, 90, 300);
                $cover = str_replace("/var/www/vhosts/frikiradar.com/app.frikiradar.com", $server, $image);
            }

            if (isset($game['artworks'][0])) {
                $filename = 'artwork.jpg';
                if (!file_exists($absolutePath . $game['slug'])) {
                    mkdir($absolutePath . $game['slug'], 0777, true);
                }

                $uploader = new FileUploaderService($absolutePath . $game['slug'], $filename);
                $image = $uploader->uploadImage('https:' . $game['cover']['url'], false, 90, 300);
                $artwork = str_replace("/var/www/vhosts/frikiradar.com/app.frikiradar.com", $server, $image);
            }

            if (isset($game['first_release_date'])) {
                $date = new \DateTime();
                $date->setTimestamp($game['first_release_date']);
                $releaseDate = $date;
            }

            $result = [
                'name' => $game['name'],
                'description' => $game['summary'],
                'cover' => $cover ?: null,
                'artwork' => $artwork ?: null,
                'slug' => $game['slug'],
                'rating' => $game['aggregated_rating'],
                'release_date' => $releaseDate ?: null,
                'developer' => $game['developer'] ?: null,
                'game_mode' => $game['game_mode']
            ];

            return $result;
        }

        return [];
    }

    public function getFilmsApi($name)
    {
        if (strtolower($name) == 'sao') {
            $name = 'sword art online';
        }

        $search = urlencode($name);
        $token = '777a37ca29cf54c4e246266509b0901b';
        $api = 'https://api.themoviedb.org/3';
        $page = 0;
        $films = [];
        do {
            $page++;
            $endpoint = '/search/multi?language=es&query=' . $search . '&page=' . $page . '&api_key=' . $token;
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $api . $endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $output = curl_exec($ch);
            curl_close($ch);
            $info = json_decode($output, true);
            $films = [...$films, ...$info['results']];
        } while (count($info['results']) == 20);

        usort($films, function ($a, $b) {
            return ((isset($b['popularity']) ? $b['popularity'] : 0) <=> (isset($a['popularity']) ? $a['popularity'] : 0));
        });

        if (!empty($films)) {
            $filmFound = [];

            foreach ($films as $key => $film) {
                if ((isset($film['original_title']) || isset($film['original_name'])) && isset($film['poster_path'])) {
                    if (isset($film['original_language']) && in_array($film['original_language'], ['en', 'es'])) {
                        $films[$key]['name'] = isset($film['original_title']) ? $film['original_title'] : $film['original_name'];
                    } else {
                        $films[$key]['name'] = isset($film['title']) ? $film['title'] : $film['name'];
                    }
                    $title = isset($film['title']) ? $film['title'] : $film['name'];

                    $percent = 0;
                    if (strtolower($films[$key]['name']) == strtolower($name) || strtolower($title) == strtolower($name)) {
                        $percent = 100;
                    } else {
                        similar_text(strtolower($films[$key]['name']), strtolower($name), $percent);
                    }

                    if ($percent >= 95) {
                        $filmFound = $film;
                        break;
                    }
                } else {
                    unset($films[$key]);
                }
            }

            if (!empty($filmFound)) {
                $film = $filmFound;
            } else {
                $film = $films[0];
            }
        }

        if (isset($film)) {
            if (in_array($film['original_language'], ['en', 'es'])) {
                $name = isset($film['original_title']) ? $film['original_title'] : $film['original_name'];
            } else {
                $name = isset($film['title']) ? $film['title'] : $film['name'];
            }
            $film['name'] = $name;
            $film['slug'] = $this->nameToSlug($name);

            // Imágenes
            $server = "https://app.frikiradar.com";
            $path = '/var/www/vhosts/frikiradar.com/app.frikiradar.com/images/pages/films/' . $film['slug'] . '/';
            $fs = new Filesystem();
            if (isset($film['poster_path'])) {
                $file =  'cover.jpg';
                if (!file_exists($path)) {
                    mkdir($path, 0777, true);
                }

                $fs->appendToFile($path . $file, file_get_contents('https://image.tmdb.org/t/p/w200/' . $film['poster_path']));
                $cover = str_replace("/var/www/vhosts/frikiradar.com/app.frikiradar.com", $server, $path . $file);
            }

            if (isset($film['backdrop_path']) || isset($film['poster_path'])) {
                $file = 'artwork.jpg';
                if (!file_exists($path)) {
                    mkdir($path, 0777, true);
                }

                $fs->appendToFile($path . $file, file_get_contents('https://image.tmdb.org/t/p/w400/' . (isset($film['backdrop_path']) ? $film['backdrop_path'] : $film['poster_path'])));
                $artwork = str_replace("/var/www/vhosts/frikiradar.com/app.frikiradar.com", $server, $path . $file);
            }

            if (isset($film['release_date']) || isset($film['first_air_date'])) {
                $date = \DateTime::createFromFormat('Y-m-d', isset($film['release_date']) ? $film['release_date'] : $film['first_air_date']);
                $releaseDate = $date;
            }

            $result = [
                'name' => $film['name'],
                'description' => $film['overview'] ?: null,
                'cover' => $cover,
                'artwork' => $artwork,
                'slug' => $film['slug'],
                'rating' => $film['vote_average'] * 100,
                'release_date' => $releaseDate
            ];

            return $result;
        }

        return  [];
    }

    public function nameToSlug($name)
    {
        $slug = trim(strtolower($name));
        $slug = str_replace('bros.', 'bros', $slug);
        $slug = str_replace('mr.', 'mr', $slug);
        $slug = str_replace('.', '-dot-', $slug);
        $slug = str_replace('&', 'and', $slug);
        $slug = str_replace('½', 'half', $slug);
        $slug = str_replace('1/2', 'half', $slug);
        $slug = str_replace(': ', ' ', $slug);
        $slug = str_replace([':', "'", ' '], '-', $slug);
        $slug = \transliterator_transliterate('Any-Latin; Latin-ASCII;', $slug);

        return $slug;
    }
}
