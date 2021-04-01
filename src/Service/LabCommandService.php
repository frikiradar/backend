<?php

namespace App\Service;

use Symfony\Component\Console\Style\SymfonyStyle;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\GeolocationService;
use App\Service\NotificationService;
use App\Entity\User;
use Imagine\Imagick\Imagine;
use Imagine\Image\ImageInterface;
use Imagine\Image\Box;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class LabCommandService
{
    protected $io;
    protected $o;

    private $notification;
    private $em;
    private $mailer;
    private $twig;

    public function __construct(
        EntityManagerInterface $entityManager,
        GeolocationService $geolocation,
        NotificationService $notification,
        \Swift_Mailer $mailer
    ) {
        $this->em = $entityManager;
        $this->geolocation = $geolocation;
        $this->notification = $notification;
        $this->mailer = $mailer;
    }

    public function setIo($i, $o)
    {
        $this->io = new SymfonyStyle($i, $o);
        $this->o = $o;
    }

    public function geolocation()
    {
        /*$users = $this->em->getRepository('App:User')->findAll();

        foreach ($users as $user) {
            if ((empty($user->getCountry()) || empty($user->getLocation())) && !empty($user->getCoordinates())) {
                $latitude = $user->getCoordinates()->getLatitude();
                $longitude = $user->getCoordinates()->getLongitude();

                $location = $this->geolocation->getLocationName($latitude, $longitude);
                $country = $location["country"];
                $location = $location["locality"];
                if (!empty($country)) {
                    $user->setCountry($country);
                }
                if (!empty($location)) {
                    $user->setLocation($location);
                }
                $this->em->persist($user);
                $this->em->flush();

                $this->o->writeln($user->getId() . " - " . $user->getUsername() . " - " . $country . " - " . $location);
                $this->em->detach($user);
            }
        }*/
    }

    public function notification($fromId, $toId)
    {
        $fromUser = $this->em->getRepository('App:User')->findOneBy(array('id' => $fromId));
        $toUser = $this->em->getRepository('App:User')->findOneBy(array('id' => $toId));
        $title = "Notificación de prueba";
        $text = "test";
        $url = "/profile/" . $fromId;
        $this->notification->push($fromUser, $toUser, $title, $text, $url, "radar");
    }

    public function email($toId)
    {
        $user = $this->em->getRepository('App:User')->findOneBy(array('id' => $toId));
        $message = (new \Swift_Message('¡FrikiRadar te extraña 💔!'))
            ->setFrom(['hola@frikiradar.com' => 'FrikiRadar'])
            ->setTo($user->getEmail())
            ->setBody(
                $this->twig->render(
                    "emails/registration.html.twig",
                    [
                        'username' => $user->getUsername(),
                        'code' => 'ABCDEF'
                    ]
                ),
                'text/html'
            );

        if (0 === $this->mailer->send($message)) {
            throw new \RuntimeException('Unable to send email');
        }
    }

    public function thumbnails()
    {
        /**
         * @var User[]
         */
        $users = $this->em->getRepository('App:User')->findAll();

        foreach ($users as $user) {
            $id = $user->getId();

            if ($id > 38718) {
                $files = glob("/var/www/vhosts/frikiradar.com/app.frikiradar.com/images/avatar/" . $id . "/*.jpg");
                foreach ($files as $src) {
                    if (!strpos($src, '-128px')) {
                        // Es una imagen normal, comprobamos si ya tiene thumbnail
                        $file = basename($src);
                        $file = explode(".", $file);
                        $thumbnail = $file[0] . '-128px.' . $file[1];

                        // Si tiene thumbnail ignoramos, sino lo creamos
                        $targetSrc = "/var/www/vhosts/frikiradar.com/app.frikiradar.com/images/avatar/" . $id . "/" . $thumbnail;
                        if (array_search($targetSrc, $files) === false) {
                            // No tiene thumbnail, lo creamos
                            $thumbnailSrc = $this->avatarToThumbnail($src, $targetSrc);
                        }
                    }
                }

                $avatar = $user->getAvatar();
                if (strpos($avatar, 'default.jpg') == false) {
                    $server = "https://app.frikiradar.com/images/avatar/" . $id . "/";

                    $file = basename($avatar);
                    $file = explode(".", $file);
                    $thumbnail = $server . $file[0] . '-128px.' . $file[1];

                    $user->setThumbnail($thumbnail);
                    $this->em->persist($user);
                    $this->em->flush();

                    $this->o->writeln($user->getId() . " - " . $user->getUsername() . " - " . $thumbnail);
                    $this->em->detach($user);
                }
            }
        }
    }

    private function avatarToThumbnail($src, $targetSrc)
    {
        try {
            $imagine = new Imagine();

            $options = array(
                'resolution-units' => ImageInterface::RESOLUTION_PIXELSPERINCH,
                'resolution-x' => 150,
                'resolution-y' => 150,
                'jpeg_quality' => 70
            );

            $image = $imagine
                ->open($src);
            $image->resize(new Box(128, 128));

            $image = $image->save($targetSrc, $options);
            if ($image) {
                return $targetSrc;
            }
        } catch (FileException $e) {
            // ... handle exception if something happens during file upload
            print_r($e);
        }
    }

    public function testLab()
    {
        // $search = "The Legend of Zelda: Ocarina of Time";
        $search = "Mario Kart";
        $search = trim(str_replace('Saga', '', $search));
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
        // print_r($info);

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
        if (isset($game['game_modes'])) {
            foreach ($game['game_modes'] as $gameMode) {
                if ($gameMode['slug'] === 'massively-multiplayer-online-mmo' && !isset($game['multiplayer_modes'][0]['onlinecoop'])) {
                    $game['multiplayer_modes'][0]['onlinecoop'] = 1;
                }
            }
        }

        print_r($game);
    }
}
