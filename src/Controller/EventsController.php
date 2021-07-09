<?php
// src/Controller/EventsController.php
namespace App\Controller;

use App\Entity\Event;
use App\Service\AccessCheckerService;
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
    public function __construct(
        SerializerInterface $serializer,
        EntityManagerInterface $entityManager,
        RequestService $request,
        NotificationService $notification,
        AccessCheckerService $accessChecker,
    ) {
        $this->serializer = $serializer;
        $this->em = $entityManager;
        $this->request = $request;
        $this->notification = $notification;
        $this->accessChecker = $accessChecker;
    }


    /**
     * @Route("/v1/event", name="set_event", methods={"POST"})
     */
    public function setEventAction(Request $request)
    {
        $cache = new FilesystemAdapter();
        $cache->deleteItem('rooms.list.admin');
        $cache->deleteItem('rooms.list.visible');

        $title = $request->request->get("title");
        $description = $request->request->get("description");
        $date = $request->request->get("date");
        $endDate = $request->request->get("end_date");
        $slug = $request->request->get("slug");
        //$repeat = $request->request->get("repeat") ?: "";
        $url = $request->request->get("url");
        $type = $request->request->get("type");
        if ($type === 'offline') {
            $country = $request->request->get("country");
            $city = $request->request->get("city");
            $address = $request->request->get("address");
            $postalCode = $request->request->get("postal_code");
            $contactPhone = $request->request->get("contact_phone");
            $contactEmail = $request->request->get("contact_email");
        }
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
            $event->setDate(new \DateTime($date));
            // $event->setTime(\DateTime::createFromFormat('H:i', $time));
            if ($endDate) {
                $event->setDateEnd(new \DateTime($endDate));
                // $event->setTimeEnd(\DateTime::createFromFormat('H:i', $endTime));
            }
            $event->setUrl($url);
            if ($type === 'offline') {
                $event->setCountry($country);
                if (in_array(strtolower($city), ['cdmx', 'df'])) {
                    $city = "Ciudad de México";
                }

                $event->setCity($city);
                $event->setAddress($address);
                $event->setPostalCode($postalCode);
                $event->setContactPhone($contactPhone);
                $event->setContactEmail($contactEmail);
            }

            $event->setMinage($minage);
            $event->setCreator($creator);
            $event->setRecursion(false);
            $event->setType($type);

            if (!empty($imageFile)) {
                $absolutePath = '/var/www/vhosts/frikiradar.com/app.frikiradar.com/images/events/' . $creator->getId() . '/';
                $server = "https://app.frikiradar.com";
                $filename =  microtime();
                $uploader = new FileUploaderService($absolutePath, $filename);
                $image = $uploader->uploadImage($imageFile, false, 70);
                $src = str_replace("/var/www/vhosts/frikiradar.com/app.frikiradar.com", $server, $image);
                $event->setImage($src);
            }

            $this->em->persist($event);
            $this->em->flush();

            return new Response($this->serializer->serialize($event, "json", ['groups' => 'default']));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al crear el evento - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Route("/v1/edit-event", name="edit_event", methods={"POST"})
     */
    public function editEventAction(Request $request)
    {
        $cache = new FilesystemAdapter();
        $cache->deleteItem('rooms.list.admin');
        $cache->deleteItem('rooms.list.visible');

        $id = $request->request->get("id");
        $title = $request->request->get("title");
        $description = $request->request->get("description");
        $date = $request->request->get("date");
        $endDate = $request->request->get("end_date");
        $url = $request->request->get("url");
        $type = $request->request->get("type");
        if ($type === 'offline') {
            $country = $request->request->get("country");
            $city = $request->request->get("city");
            $address = $request->request->get("address");
            $postalCode = $request->request->get("postal_code");
            $contactPhone = $request->request->get("contact_phone");
            $contactEmail = $request->request->get("contact_email");
        }
        $minage = $request->request->get("minage") ?: 0;
        $imageFile = $request->files->get('image');
        $creator = $this->getUser();

        try {
            /**
             * @var Event
             */
            $event = $this->em->getRepository('App:Event')->findOneBy(array('id' => $id));
            $event->setTitle($title);
            $event->setDescription($description);
            $event->setDate(new \DateTime($date));
            // $event->setTime(\DateTime::createFromFormat('H:i', $time));
            if ($endDate) {
                $event->setDateEnd(new \DateTime($endDate));
                // $event->setTimeEnd(\DateTime::createFromFormat('H:i', $endTime));
            }
            $event->setUrl($url);
            if ($type === 'offline') {
                $event->setCountry($country);
                if (in_array(strtolower($city), ['cdmx', 'df'])) {
                    $city = "Ciudad de México";
                }

                $event->setCity($city);
                $event->setAddress($address);
                $event->setPostalCode($postalCode);
                $event->setContactPhone($contactPhone);
                $event->setContactEmail($contactEmail);
            }

            $event->setMinage($minage);
            $event->setCreator($creator);
            $event->setRecursion(false);
            $event->setType($type);

            if (!empty($imageFile)) {
                // Borramos primero imagen antigua
                $image = $event->getImage();
                if ($image) {
                    $file = str_replace('https://app.frikiradar.com/', '/var/www/vhosts/frikiradar.com/app.frikiradar.com/', $image);
                    unlink($file);
                }

                // Subimos imagen nueva
                $absolutePath = '/var/www/vhosts/frikiradar.com/app.frikiradar.com/images/events/' . $creator->getId() . '/';
                $server = "https://app.frikiradar.com";
                $filename =  microtime();
                $uploader = new FileUploaderService($absolutePath, $filename);
                $image = $uploader->uploadImage($imageFile, false, 70);
                $src = str_replace("/var/www/vhosts/frikiradar.com/app.frikiradar.com", $server, $image);
                $event->setImage($src);
            }

            $this->em->persist($event);
            $this->em->flush();

            return new Response($this->serializer->serialize($event, "json", ['groups' => 'default']));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al crear el evento - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Route("/v1/event/{id}", name="get_event_id", methods={"GET"})
     */
    public function getEventAction($id)
    {
        $fromUser = $this->getUser();
        $this->accessChecker->checkAccess($fromUser);

        try {
            $event = $this->em->getRepository('App:Event')->findOneBy(array('id' => $id));
            if ($event->getSlug()) {
                $page = $this->em->getRepository('App:Page')->findOneBy(array('slug' => $event->getSlug()));
                $event->setPage($page);
            }

            return new Response($this->serializer->serialize($event, "json", ['groups' => ['default']]));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al obtener el evento - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Route("/v1/my-events", name="get_my_events", methods={"GET"})
     */
    public function getMyEventsAction()
    {
        $user = $this->getUser();
        $this->accessChecker->checkAccess($user);

        try {
            $events = $this->em->getRepository('App:Event')->findUserEvents($user);
            return new Response($this->serializer->serialize($events, "json", ['groups' => ['default']]));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al obtener los eventos - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Route("/v1/suggested-events", name="get_suggested_events", methods={"GET"})
     */
    public function getSuggestedEventsAction()
    {
        $user = $this->getUser();
        $this->accessChecker->checkAccess($user);

        try {
            $events = $this->em->getRepository('App:Event')->findSuggestedEvents($user);
            return new Response($this->serializer->serialize($events, "json", ['groups' => ['default']]));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al obtener los eventos - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Route("/v1/online-events", name="get_online_events", methods={"GET"})
     */
    public function getOnlineEventsAction()
    {
        $user = $this->getUser();
        $this->accessChecker->checkAccess($user);

        try {
            $events = $this->em->getRepository('App:Event')->findOnlineEvents($user);
            return new Response($this->serializer->serialize($events, "json", ['groups' => ['default']]));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al obtener los eventos - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Route("/v1/near-events", name="get_near_events", methods={"GET"})
     */
    public function getNearEventsAction()
    {
        $user = $this->getUser();
        $this->accessChecker->checkAccess($user);

        try {
            $events = $this->em->getRepository('App:Event')->findNearEvents($user);
            return new Response($this->serializer->serialize($events, "json", ['groups' => ['default']]));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al obtener los eventos - Error: {$ex->getMessage()}");
        }
    }
}
