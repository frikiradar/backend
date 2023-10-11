<?php

namespace App\Service;

use Symfony\Component\Console\Style\SymfonyStyle;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\GeolocationService;
use App\Service\NotificationService;
use App\Entity\User;
use DateTime;
use Symfony\Component\Config\Definition\Exception\Exception;
use Imagine\Imagick\Imagine;
use Imagine\Image\ImageInterface;
use Imagine\Image\Box;
use Patreon\API;
use Patreon\OAuth;
use Symfony\Component\Filesystem\Filesystem;
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
        $this->notification->set($fromUser, $toUser, $title, $text, $url, "radar");
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

    public function removeAccount($username)
    {
        $user = $this->em->getRepository('App:User')->findOneBy(array('username' => $username));

        try {
            // borramos chats y sus archivos
            $this->em->getRepository('App:Chat')->deleteChatsFiles($user);

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
            $this->o->writeln("Error al eliminar: ${$ex}");
        }
    }

    public function testLab()
    {
        ini_set('memory_limit', '-1');
        $tags = $this->em->getRepository('App:Tag')->findAllGroupedTags();

        foreach ($tags as $tag) {
            $category = $tag->getCategory()->getName();
            if (in_array($category, array('films', 'games'))) {
                $slug = $tag->getSlug();
                if (!isset($slug)) {
                    $this->o->writeln($tag->getName() . " - " . $category);
                    try {
                        $page = $this->em->getRepository('App:Page')->setPage($tag);
                        if ($page) {
                            // $tag->setSlug($page->getSlug());
                            $this->o->writeln("Página generada: " . $page->getName() . " (" . $page->getSlug() . ") - " . (!is_null($page->getDescription()) ? 'ok' : 'fail'));
                        } else {
                            $this->o->writeln("Error al generar página para: " . $tag->getName() . " - " . $ex->getMessage());
                        }
                    } catch (Exception $ex) {
                        $this->o->writeln("Error al generar página: " . $page->getName() . " - " . $ex->getMessage());
                    }
                }
            }
        }
    }
}
