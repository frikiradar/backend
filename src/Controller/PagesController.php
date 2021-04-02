<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Page;
use App\Entity\Tag;
use App\Service\AccessCheckerService;
use App\Service\RequestService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Class PagesController
 *
 * @Route(path="/api")
 */
class PagesController extends AbstractController
{
    public function __construct(
        SerializerInterface $serializer,
        RequestService $request,
        AccessCheckerService $accessChecker,
        EntityManagerInterface $entityManager
    ) {
        $this->request = $request;
        $this->serializer = $serializer;
        $this->accessChecker = $accessChecker;
        $this->em = $entityManager;
    }


    /**
     * @Route("/v1/page/{slug}", name="page", methods={"GET"})
     */
    public function getPage(string $slug)
    {
        $user = $this->getUser();
        $this->accessChecker->checkAccess($user);

        try {
            $page = $this->em->getRepository('App:Page')->findOneBy(array('slug' => $slug));
            return new Response($this->serializer->serialize($page, "json", ['groups' => 'default']));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al obtener la página - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Route("/v1/page", name="set_page", methods={"POST"})
     */
    public function setPage(Request $request)
    {
        $user = $this->getUser();
        $this->accessChecker->checkAccess($user);
        /**
         * @var Page
         */
        $page = new Page();
        try {
            $tag = $this->em->getRepository('App:Tag')->findOneBy(array('id' => $this->request->get($request, 'id')));
            $name = $tag->getName();
            $search = trim(str_replace('Saga', '', $name));
            $search = str_replace('&', 'and', $search);
            $search = str_replace(': ', ' ', $search);
            $search = str_replace(':', ' ', $search);
            $search = transliterator_transliterate('Any-Latin; Latin-ASCII;', $search);

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
            $ch = curl_init($url . $endpoint); // Initialise cURL
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array($bearer, $client_id)); // Inject the token into the header
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, 1); // Specify the request method as POST
            $output = curl_exec($ch); // Execute the cURL statement
            curl_close($ch); // Close the cURL connection
            $games = json_decode($output, true); // Return the received data
            if (!empty($games)) {
                usort($games, function ($a, $b) {
                    return (isset($a['first_release_date']) ? $a['first_release_date'] : 99999999999) <=> (isset($b['first_release_date']) ? $b['first_release_date'] : 99999999999);
                });
                // print_r($games);
                $gameFound = [];

                foreach ($games as $game) {
                    similar_text(strtolower($game['name']), strtolower($search), $percent);
                    if ($percent >= 98) {
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
                    $page->setName($game['name']);
                    $page->setDescription($game['summary']);
                    $page->setSlug($game['slug']);
                    $page->setRating($game['rating']);
                    if (isset($game['first_release_date'])) {
                        $date = new \DateTime();
                        $date->setTimestamp($game['first_release_date']);
                        $page->setReleaseDate($date);
                    }
                    $page->setTimeCreation();
                    $page->setLastUpdate();
                    $page->setGameMode($game['game_mode']);

                    $server = "https://app.frikiradar.com";
                    $path = '/var/www/vhosts/frikiradar.com/app.frikiradar.com/images/pages/' . $game['slug'] . '/';
                    $fs = new Filesystem();
                    if (isset($game['cover'])) {
                        $file =  'cover.jpg';
                        if (!file_exists($path)) {
                            mkdir($path, 0777, true);
                        }

                        $fs->appendToFile($path . $file, file_get_contents('https:' . $game['cover']['url']));
                        $src = str_replace("/var/www/vhosts/frikiradar.com/app.frikiradar.com", $server, $path . $file);
                        $page->setCover($src);
                    }

                    if (isset($game['artworks'][0])) {
                        $file = 'artwork.jpg';
                        if (!file_exists($path)) {
                            mkdir($path, 0777, true);
                        }

                        $fs->appendToFile($path . $file, file_get_contents('https:' . $game['artworks'][0]['url']));
                        $src = str_replace("/var/www/vhosts/frikiradar.com/app.frikiradar.com", $server, $path . $file);
                        $page->setArtwork($src);
                    }

                    $this->em->persist($page);
                    $this->em->flush();
                    // actualizamos todas las etiquetas con este mismo nombre de esta categoria
                    $this->em->getRepository('App:Tag')->setTagsSlug($tag, $game['slug']);

                    return new Response($this->serializer->serialize($page, "json", ['groups' => 'default']));
                } else {
                    throw new HttpException(400, "No se han obtenido resultados");
                }
            } else {
                throw new HttpException(400, "No se han obtenido resultados");
            }
        } catch (Exception $ex) {
            return new Response($this->serializer->serialize($page, "json", ['groups' => 'default']));
        }
    }
}
