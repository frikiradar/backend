<?php

namespace App\Service;

use Symfony\Component\Console\Style\SymfonyStyle;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\NotificationService;
use App\Entity\User;
use Geocoder\Query\GeocodeQuery;
use Symfony\Component\Config\Definition\Exception\Exception;
use Imagine\Imagick\Imagine;
use Imagine\Image\ImageInterface;
use Imagine\Image\Box;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use GuzzleHttp\Client as GuzzleClient;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class LabCommandService
{
    protected $io;
    protected $o;

    private $notification;
    private $em;
    private $mailer;
    private $twig;
    private $geoService;

    public function __construct(
        EntityManagerInterface $entityManager,
        NotificationService $notification,
        GeolocationService $geoService,
    ) {
        $this->em = $entityManager;
        $this->notification = $notification;
        $this->geoService = $geoService;
    }

    public function setIo($i, $o)
    {
        $this->io = new SymfonyStyle($i, $o);
        $this->o = $o;
    }

    public function geolocation()
    {
        $city = 'Badajoz';
        $country = 'España';
        // $key = 'AIzaSyB3VlBHlrMY6Vw9wf3_oGE2PcI7QV9EBT8';
        $httpClient = new GuzzleClient();
        // $provider = new GoogleMaps($httpClient, null, $key);
        $provider = new \Geocoder\Provider\ArcGISOnline\ArcGISOnline($httpClient);
        $geocoder = new \Geocoder\StatefulGeocoder($provider, 'en');

        $result = $geocoder->geocodeQuery(GeocodeQuery::create($city . ', ' . $country));
        $coordinates = $result->first()->getCoordinates();
        $latitude = $coordinates->getLatitude();
        $longitude = $coordinates->getLongitude();

        $this->o->writeln($latitude . " - " . $longitude);
    }

    public function notification($fromId, $toId)
    {
        $fromUser = $this->em->getRepository(\App\Entity\User::class)->findOneBy(array('id' => $fromId));
        $toUser = $this->em->getRepository(\App\Entity\User::class)->findOneBy(array('id' => $toId));
        $title = "Notificación de prueba";
        $text = "test";
        $url = "/profile/" . $fromId;
        $this->notification->set($fromUser, $toUser, $title, $text, $url, "radar");
    }

    public function email($toId, MailerInterface $mailer)
    {
        $user = $this->em->getRepository(\App\Entity\User::class)->findOneBy(array('id' => $toId));
        $email = (new Email())
            ->from(new Address('noreply@mail.frikiradar.com', 'frikiradar'))
            ->to(new Address($user->getEmail(), $user->getUsername()))
            ->subject('¡frikiradar te extraña 💔!')
            ->html($this->twig->render(
                "emails/registration.html.twig",
                [
                    'username' => $user->getUsername(),
                    'code' => 'ABCDEF'
                ]
            ));

        try {
            $mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            throw new \RuntimeException('Unable to send email');
        }
    }

    public function thumbnails()
    {
        /**
         * @var User[]
         */
        $users = $this->em->getRepository(\App\Entity\User::class)->findAll();

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

    public function removeAccount($username)
    {
        $user = $this->em->getRepository(\App\Entity\User::class)->findOneBy(array('username' => $username));

        try {
            // borramos chats y sus archivos
            $this->em->getRepository(\App\Entity\Chat::class)->deleteChatsFiles($user);

            // borramos archivos de historias
            $folder = "/var/www/vhosts/frikiradar.com/app.frikiradar.com/images/stories/" . $user->getId() . "/";
            foreach (glob($folder . "*.*") as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
            if (file_exists($folder)) {
                rmdir($folder);
            }

            // borramos carpeta del usuario imagenes y thumbnails
            $folder = "/var/www/vhosts/frikiradar.com/app.frikiradar.com/images/avatar/" . $user->getId() . "/";
            foreach (glob($folder . "*.*") as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
            if (file_exists($folder)) {
                rmdir($folder);
            }

            $username = $user->getUsername();
            // Eliminamos usuario
            $this->em->remove($user);
            $this->em->flush();

            $data = [
                'code' => 200,
                'message' => "Usuario eliminado correctamente",
            ];
            $this->o->writeln("Eliminado correctamente");
        } catch (Exception $ex) {
            $this->o->writeln("Error al eliminar: {$ex}");
        }
    }

    public function testLab()
    {
        ini_set('memory_limit', '-1');

        // recorremos todos los usuarios y le seteamos el país según su ultima ip
        /**
         * @var User[]
         */
        $users = $this->em->getRepository(\App\Entity\User::class)->findAll();
        foreach ($users as $user) {
            $active = $user->isActive();
            $ip = $user->getLastIP();
            $country = $user->getIpCountry();
            if ($active && $ip && !$country) {
                $country = $this->geoService->getIpCountry($ip);
                if ($country) {
                    $user->setIpCountry($country);
                    $this->em->persist($user);
                    $this->em->flush();
                    $this->o->writeln("Usuario: " . $user->getId() . " - " . $user->getUsername() . " - " . $country);
                } else {
                    $user->setIpCountry("ES");
                    $this->em->persist($user);
                    $this->em->flush();
                    $this->o->writeln("No se ha podido obtener el país de la ip: " . $ip);
                }
            }
        }
    }
}
