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
            ->andWhere('p.cover IS NOT NULL')
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

        // Hacemos una primera búsqueda por slug
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

        // Si no hay resultados, hacemos una búsqueda por nombre
        if (empty($games)) {
            $body = 'search "' . $search . '"; fields name, cover.url, game_modes.slug, multiplayer_modes.*, aggregated_rating, slug, summary, first_release_date, involved_companies.company.name, involved_companies.developer, artworks.*; where version_parent = null; limit 500;';
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
            if ($game['slug'] === 'pokemon-red-version') {
                $game['slug'] = 'pokemon';
                $game['name'] = 'Pokémon';
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

            if (isset($game['involved_companies'])) {
                foreach ($game['involved_companies'] as $company) {
                    if ($company['developer']) {
                        $game['developer'] = $company['company']['name'];
                    }
                }
            }

            if (isset($game['summary'])) {
                try {
                    $trans = new GoogleTranslate();
                    $text = strlen($game['summary']) > 2000 ? (substr($game['summary'], 0, 1999) . '...') : $game['summary'];
                    $game['summary'] = $trans->translate('en', 'es', $text);
                } catch (Exception $ex) {
                    // Omitimos traducción si falla, lo metemos en inglés
                    // return false;
                    echo $ex->getMessage();
                }
            }

            // Imágenes
            $server = "https://app.frikiradar.com";
            $absolutePath = '/var/www/vhosts/frikiradar.com/app.frikiradar.com/images/pages/games/' . $game['slug'] . '/';
            if (isset($game['cover'])) {
                $filename =  'cover';
                if (!file_exists($absolutePath)) {
                    mkdir($absolutePath, 0777, true);
                } elseif (file_exists($absolutePath . $filename . '.jpg')) {
                    unlink($absolutePath . $filename . '.jpg');
                }

                $uploader = new FileUploaderService($absolutePath, $filename);
                $image = $uploader->uploadImage('https:' . $game['cover']['url'], false, 90, 300);
                $cover = str_replace("/var/www/vhosts/frikiradar.com/app.frikiradar.com", $server, $image);
            }

            if (isset($game['artworks'][0])) {
                $filename = 'artwork';
                if (!file_exists($absolutePath)) {
                    mkdir($absolutePath, 0777, true);
                } elseif (file_exists($absolutePath . $filename . '.jpg')) {
                    unlink($absolutePath . $filename . '.jpg');
                }

                $uploader = new FileUploaderService($absolutePath, $filename);
                $image = $uploader->uploadImage('https:' . $game['artworks'][0]['url'], false, 90, 300);
                $artwork = str_replace("/var/www/vhosts/frikiradar.com/app.frikiradar.com", $server, $image);
            }

            if (isset($game['first_release_date'])) {
                $date = new \DateTime();
                $date->setTimestamp($game['first_release_date']);
                $releaseDate = $date;
                // si es booleano, es que no tiene fecha de salida
                if (is_bool($releaseDate)) {
                    $releaseDate = null;
                }
            }

            $result = [
                'name' => $game['name'],
                'description' => $game['summary'] ?? "",
                'cover' => $cover ?? null,
                'artwork' => $artwork ?? null,
                'slug' => $game['slug'],
                'rating' => $game['aggregated_rating'] ?? null,
                'release_date' => $releaseDate ?? null,
                'developer' => $game['developer'] ?? null,
                'game_mode' => $game['game_mode'] ?? null
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
            if (isset($info['results'])) {
                $films = [...$films, ...$info['results']];
            }
        } while (isset($info['results']) && count($info['results']) == 20);
        // print_r($films);
        usort($films, function ($a, $b) {
            return ((isset($b['popularity']) ? $b['popularity'] : 0) <=> (isset($a['popularity']) ? $a['popularity'] : 0));
        });

        if (isset($films[0])) {
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
            } elseif (isset($films[0])) {
                $film = $films[0];
            }
        }

        if (!empty($film)) {
            if (isset($film['original_language']) && in_array($film['original_language'], ['en', 'es'])) {
                $name = isset($film['original_title']) ? $film['original_title'] : $film['original_name'];
            } else {
                $name = isset($film['title']) ? $film['title'] : $film['name'];
            }
            $film['name'] = $name;
            $film['slug'] = $this->nameToSlug($name);

            if ($film['slug'] == 're-zero-empezar-de-cero-en-un-mundo-diferente') {
                $film['slug'] = 're-zero';
                $film['name'] = 'Re:Zero';
            }

            if ($film['slug'] == 'los-siete-pecados-capitales-los-cuatro-jinetes-del-apocalipsis') {
                $film['slug'] = 'nanatsu-no-taizai';
                $film['name'] = 'Nanatsu no Taizai';
            }

            // Imágenes
            $server = "https://app.frikiradar.com";
            $path = '/var/www/vhosts/frikiradar.com/app.frikiradar.com/images/pages/films/' . $film['slug'] . '/';
            if (isset($film['poster_path'])) {
                $file =  'cover';
                if (!file_exists($path)) {
                    mkdir($path, 0777, true);
                }

                $uploader = new FileUploaderService($path, $file);
                $image = $uploader->uploadImage('https://image.tmdb.org/t/p/w200' . $film['poster_path'], false, 90, 300);
                $cover = str_replace("/var/www/vhosts/frikiradar.com/app.frikiradar.com", $server, $image);
            }

            if (isset($film['backdrop_path']) || isset($film['poster_path'])) {
                $file = 'artwork';
                if (!file_exists($path)) {
                    mkdir($path, 0777, true);
                }

                $uploader = new FileUploaderService($path, $file);
                $image = $uploader->uploadImage('https://image.tmdb.org/t/p/w400' . (isset($film['backdrop_path']) ? $film['backdrop_path'] : $film['poster_path']), false, 90, 300);
                $artwork = str_replace("/var/www/vhosts/frikiradar.com/app.frikiradar.com", $server, $image);
            }

            if (isset($film['release_date']) || isset($film['first_air_date'])) {
                $date = \DateTime::createFromFormat('Y-m-d', isset($film['release_date']) ? $film['release_date'] : $film['first_air_date']);
                $releaseDate = $date;
                // si es booleano, es que no tiene fecha de salida
                if (is_bool($releaseDate)) {
                    $releaseDate = null;
                }
            }

            $result = [
                'name' => $film['name'],
                'description' => $film['overview'] ?? null,
                'cover' => $cover ?? null,
                'artwork' => $artwork ?? null,
                'slug' => $film['slug'],
                'rating' => isset($film['vote_average']) ? ($film['vote_average'] * 100) : null,
                'release_date' => $releaseDate ?? null
            ];

            return $result;
        }

        return  [];
    }

    public function setPage($tag)
    {
        $name = preg_replace('/(\s\(saga\)|^saga\s|\ssaga$|\strilogia$|^trilogia\s)/i', '', $tag->getName());
        $category = $tag->getCategory()->getName();

        $slug = $this->nameToSlug($name);
        // buscamos pagina con esta categoria y este slug
        $page = $this->findOneBy(array('slug' => $slug, 'category' => $category));

        if (empty($page)) {
            switch ($category) {
                case 'games':
                    $result = $this->getGamesApi($name);
                    break;

                case 'films':
                    $result = $this->getFilmsApi($name);
                    break;
            }

            if (!empty($result)) {
                $slug = $result['slug'];
                $page = $this->findOneBy(array('slug' => $result['slug']));
                $oldPage = $page;

                if (empty($oldPage) || (null !== $oldPage && $oldPage->getCategory() !== $category)) {
                    /**
                     * @var Page
                     */
                    $page = new Page();
                    $page->setName($result['name']);
                    $page->setDescription($result['description']);
                    $page->setSlug($result['slug'] . (null !== $oldPage && $oldPage->getCategory() !== $category ? '-' . $category : ''));
                    $page->setRating($result['rating']);
                    $page->setCategory($category);
                    if (isset($result['developer'])) {
                        $page->setDeveloper($result['developer']);
                    }
                    $page->setReleaseDate($result['release_date']);
                    $page->setTimeCreation();
                    $page->setLastUpdate();
                    if (isset($result['game_mode'])) {
                        $page->setGameMode($result['game_mode']);
                    }
                    $page->setCover($result['cover']);
                    $page->setArtwork($result['artwork']);

                    try {
                        $this->em->persist($page);
                        $this->em->flush();
                    } catch (\Exception $ex) {
                        // Si falla, es que ya existe, lo buscamos
                        $page = $this->findOneBy(array('slug' => $result['slug']));
                    }
                }
            } else {
                // De momento no vamos a crear paginas vacías
                // return false;
                /*if (empty($page)) {
                    $page = new Page();
                    $page->setName($name);
                    $page->setSlug($slug);
                    $page->setTimeCreation();
                    $page->setLastUpdate();
                    $page->setCategory($tag->getCategory()->getName());
                    try {
                        $this->em->persist($page);
                        $this->em->flush();
                    } catch (\Exception $ex) {
                        return false;
                    }
                }*/
            }
        }

        // actualizamos todas las etiquetas con este mismo nombre de esta categoria
        $this->em->getRepository('App:Tag')->setTagsSlug($tag, $slug);

        return $page;
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
        $slug = \transliterator_transliterate('Any-Latin; Latin-ASCII;', $slug);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');

        return $slug;
    }
}
