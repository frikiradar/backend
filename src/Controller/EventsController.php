<?php
// src/Controller/EventsController.php
namespace App\Controller;

use App\Entity\Event;
use App\Service\FileUploaderService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpKernel\Exception\HttpException;
use App\Service\NotificationService;
use App\Service\RequestService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Class EventsController
 *
 * @Route(path="/api")
 */
class EventsController extends AbstractController
{
    public function __construct(SerializerInterface $serializer, EntityManagerInterface $entityManager, RequestService $request, NotificationService $notification)
    {
        $this->serializer = $serializer;
        $this->em = $entityManager;
        $this->request = $request;
        $this->notification = $notification;
    }


    /**
     * @Route("/v1/event", name="set_event", methods={"POST"})
     */
    public function setEvent(Request $request)
    {
        $cache = new FilesystemAdapter();
        $cache->deleteItem('rooms.list.admin');
        $cache->deleteItem('rooms.list.visible');

        $title = $request->request->get("title");
        $description = $request->request->get("description");
        $date = $request->request->get("date");
        $time = $request->request->get("time");
        $endDate = $request->request->get("endDate");
        $endTime = $request->request->get("endTime");
        $slug = $request->request->get("slug");
        //$repeat = $request->request->get("repeat") ?: "";
        $url = $request->request->get("url");
        $location = $request->request->get("location");
        $minage = $request->request->get("minage") ?: 0;
        $imageFile = $request->files->get('image');
        $creator = $this->getUser();

        try {
            /**
             * @var Event
             */
            $event = new Event();
            $event->setTitle($title);
            $event->setDescription($description);
            $event->setDate(\DateTime::createFromFormat('Y-m-d', $date));
            $event->setTime(\DateTime::createFromFormat('H:i', $time));
            if ($endDate && $endTime) {
                $event->setDateEnd(\DateTime::createFromFormat('Y-m-d', $endDate));
                $event->setTimeEnd(\DateTime::createFromFormat('H:i', $endTime));
            } else {
                $event->setDateEnd(\DateTime::createFromFormat('Y-m-d', $date));
                $event->setTimeEnd(\DateTime::createFromFormat('H:i', '23:59'));
            }
            $event->setUrl($url);
            $event->setLocation($location);
            $event->setMinage($minage);
            $event->setCreator($creator);
            $event->setRecursion(false);

            /*if (!empty($imageFile)) {
                $absolutePath = '/var/www/vhosts/frikiradar.com/app.frikiradar.com/images/rooms/';
                $server = "https://app.frikiradar.com";
                $filename = microtime();
                $uploader = new FileUploaderService($absolutePath, $filename);
                $image = $uploader->uploadImage($imageFile, true, 70);
                $src = str_replace("/var/www/vhosts/frikiradar.com/app.frikiradar.com", $server, $image);
                $event->setImage($src);
            }*/

            $this->em->persist($event);
            $this->em->flush();

            return new Response($this->serializer->serialize($event, "json", ['groups' => 'default']));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al crear el event - Error: {$ex->getMessage()}");
        }
    }
}
