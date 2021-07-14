<?php
// src/Controller/EventsController.php
namespace App\Controller;

use App\Entity\Chat;
use App\Entity\Event;
use App\Service\AccessCheckerService;
use App\Service\FileUploaderService;
use App\Service\MessageService;
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
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
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
        MessageService $message,
        AuthorizationCheckerInterface $security
    ) {
        $this->serializer = $serializer;
        $this->em = $entityManager;
        $this->request = $request;
        $this->notification = $notification;
        $this->accessChecker = $accessChecker;
        $this->message = $message;
        $this->security = $security;
    }


    /**
     * @Route("/v1/event", name="set_event", methods={"POST"})
     */
    public function setEventAction(Request $request)
    {
        $title = $request->request->get("title");
        $description = $request->request->get("description");
        $date = $request->request->get("date");
        $endDate = $request->request->get("end_date");
        $slug = $request->request->get("slug");
        //$repeat = $request->request->get("repeat") ?: "";
        $url = $request->request->get("url");
        $type = $request->request->get("type");
        $userId = $request->request->get("user");
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

        $official = $request->request->get("official");
        if ($official && $this->security->isGranted('ROLE_ADMIN')) {
            $creator = $this->em->getRepository('App:User')->findOneBy(array('username' => 'frikiradar'));
        } else {
            $creator = $this->getUser();
        }

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
            $event->setStatus('active');
            if ($creator->getUsername() !== 'frikiradar') {
                $event->addParticipant($creator);
            }

            if ($userId) {
                $user = $this->em->getRepository('App:User')->findOneBy(array('id' => $userId));
                $event->setUser($user);
            }

            if ($slug) {
                $event->setSlug($slug);
            }

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

            if (isset($user) || $slug) {
                // Mensaje de chat especial citas
                $chat = new Chat();

                if (isset($user)) {
                    $chat->setTouser($user);
                }
                $chat->setFromuser($creator);
                $chat->setTimeCreation();

                if (isset($user)) {
                    $min = min($chat->getFromuser()->getId(), $chat->getTouser()->getId());
                    $max = max($chat->getFromuser()->getId(), $chat->getTouser()->getId());
                    $conversationId = $min . "_" . $max;
                    $text = "¡" . $creator->getName() . " te ha invitado a una cita!";
                } else {
                    $conversationId = $slug;
                    $text = "Nuevo evento creado";
                }
                $chat->setText($text);
                $chat->setConversationId($conversationId);
                $chat->setEvent($event);
                $this->em->persist($chat);
                $this->em->flush();

                if (isset($user)) {
                    $this->message->send($chat, $user, true);
                    $this->message->send($chat, $creator);
                } else {
                    $this->message->sendTopic($chat, $slug, false);
                }
            }

            return new Response($this->serializer->serialize($event, "json", ['groups' => ['default', 'message']]));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al crear el evento - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Route("/v1/edit-event", name="edit_event", methods={"POST"})
     */
    public function editEventAction(Request $request)
    {
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

        try {
            /**
             * @var Event
             */
            $event = $this->em->getRepository('App:Event')->findOneBy(array('id' => $id));
            if ($event->getCreator()->getId() === $this->getUser()->getId() || $this->security->isGranted('ROLE_ADMIN')) {
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
                $event->setRecursion(false);
                $event->setType($type);

                if (!empty($imageFile)) {
                    // Borramos primero imagen antigua
                    $image = $event->getImage();
                    if ($image) {
                        $file = str_replace('https://app.frikiradar.com/', '/var/www/vhosts/frikiradar.com/app.frikiradar.com/', $image);
                        unlink($file);
                    }

                    $creator = $event->getCreator();
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
            } else {
                throw new HttpException(401, "No puedes editar el evento de otro usuario");
            }
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
     * @Route("/v1/delete-event/{id}", name="delete_event", methods={"DELETE"})
     */
    public function deleteEventAction(int $id)
    {
        $user = $this->getUser();
        $this->accessChecker->checkAccess($user);

        try {
            /**
             * @var Event
             */
            $event = $this->em->getRepository('App:Event')->findOneBy(array('id' => $id));
            if ($event->getCreator()->getId() === $this->getUser()->getId()) {
                $image = $event->getImage();
                if ($image && !strpos($image, '/avatar/')) {
                    $file = str_replace('https://app.frikiradar.com/', '/var/www/vhosts/frikiradar.com/app.frikiradar.com/', $image);
                    unlink($file);
                }

                $participants = $event->getParticipants();
                // Avisamos a los usuarios del evento eliminado
                $fromUser = $this->em->getRepository('App:User')->findOneBy(array('username' => 'frikiradar'));
                $title = 'Evento eliminado.';
                $text = 'El evento ' . $event->getTitle() . ' ha sido eliminado.';
                $url = "/tabs/events";
                foreach ($participants as $participant) {
                    $this->notification->set($fromUser, $participant, $title, $text, $url, 'event');
                }

                $this->em->remove($event);
                $this->em->flush();

                $slug = 'event-' . $id;
                $this->em->getRepository('App:Chat')->deleteChatSlug($slug);

                $data = [
                    'code' => 200,
                    'message' => "Evento eliminado correctamente",
                ];
                return new JsonResponse($data, 200);
            } else {
                throw new HttpException(401, "No puedes eliminar el evento de otro usuario");
            }
        } catch (Exception $ex) {
            throw new HttpException(400, "Error eliminar el evento - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Route("/v1/cancel-event", name="cancel_event", methods={"PUT"})
     */
    public function cancelEventAction(Request $request)
    {
        $user = $this->getUser();
        $this->accessChecker->checkAccess($user);
        $id = $this->request->get($request, "id");

        try {
            /**
             * @var Event
             */
            $event = $this->em->getRepository('App:Event')->findOneBy(array('id' => $id));
            if ($event->getCreator()->getId() === $this->getUser()->getId()) {

                $event->setStatus('cancelled');

                $this->em->persist($event);
                $this->em->flush();

                // Avisamos a los usuarios del evento cancelado
                $participants = $event->getParticipants();
                $fromUser = $this->em->getRepository('App:User')->findOneBy(array('username' => 'frikiradar'));
                $title = 'Evento cancelado.';
                $text = 'El evento ' . $event->getTitle() . ' ha sido cancelado.';
                $url = "/event/" . $event->getId();
                foreach ($participants as $participant) {
                    $this->notification->set($fromUser, $participant, $title, $text, $url, 'event');
                }

                return new Response($this->serializer->serialize($event, "json", ['groups' => ['default']]));
            } else {
                throw new HttpException(401, "No puedes cancelar el evento de otro usuario");
            }
        } catch (Exception $ex) {
            throw new HttpException(400, "Error eliminar el evento - Error: {$ex->getMessage()}");
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

    /**
     * @Route("/v1/slug-events/{slug}", name="slug_events", methods={"GET"})
     */
    public function getSlugEvents(string $slug)
    {
        $user = $this->getUser();
        $this->accessChecker->checkAccess($user);

        try {
            $events = $this->em->getRepository('App:Event')->findSlugEvents($slug);
            return new Response($this->serializer->serialize($events, "json", ['groups' => ['default']]));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al obtener los eventos - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Route("/v1/participate-event", name="participate_event", methods={"POST"})
     */
    public function participateEventAction(Request $request)
    {
        $user = $this->getUser();
        $this->accessChecker->checkAccess($user);
        $id = $this->request->get($request, "id");

        try {
            /**
             * @var Event
             */
            $event = $this->em->getRepository('App:Event')->findOneBy(array('id' => $id));
            $event->addParticipant($user);
            $this->em->persist($event);
            $this->em->flush();

            return new Response($this->serializer->serialize($event, "json", ['groups' => 'default']));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al participar en el evento - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Route("/v1/remove-participant-event/{id}", name="remove_participant_event", methods={"DELETE"})
     */
    public function removeParticipantEventAction(int $id)
    {
        $user = $this->getUser();
        $this->accessChecker->checkAccess($user);

        try {
            /**
             * @var Event
             */
            $event = $this->em->getRepository('App:Event')->findOneBy(array('id' => $id));
            $event->removeParticipant($user);
            $this->em->persist($event);
            $this->em->flush();

            return new Response($this->serializer->serialize($event, "json", ['groups' => 'default']));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al quitar participación en el evento - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Route("/v1/confirm-date", name="confirm_date", methods={"POST"})
     */
    public function confirmDateAction(Request $request)
    {
        $user = $this->getUser();
        $this->accessChecker->checkAccess($user);
        $id = $this->request->get($request, "id");

        try {
            $chat = $this->em->getRepository('App:Chat')->findOneBy(array('id' => $id));

            /**
             * @var Event
             */
            $event = $chat->getEvent();
            $event->addParticipant($user);
            $this->em->persist($event);
            $this->em->flush();

            $chat->setEvent($event);

            $this->message->send($chat, $user);
            $chat->setText($user->getName() . " ha aceptado tu invitación de cita.");
            $url = "/chat/" + $user->getId();
            $this->message->send($chat, $event->getCreator(), true, $url);

            return new Response($this->serializer->serialize($event, "json", ['groups' => 'default']));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al confirmar la cita - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Route("/v1/decline-date", name="decline_date", methods={"PUT"})
     */
    public function declineDateAction(Request $request)
    {
        $user = $this->getUser();
        $this->accessChecker->checkAccess($user);
        $id = $this->request->get($request, "id");

        try {
            $chat = $this->em->getRepository('App:Chat')->findOneBy(array('id' => $id));

            /**
             * @var Event
             */
            $event = $chat->getEvent();
            if ($event->getCreator()->getId() === $this->getUser()->getId() || $event->getUser()->getId() === $this->getUser()->getId()) {
                $event->setStatus('cancelled');

                $this->em->persist($event);
                $this->em->flush();
                $chat->setEvent($event);

                $this->message->send($chat, $user);
                $chat->setText($user->getName() . " ha rechazado tu invitación de cita.");

                $url = "/chat/" + $user->getId();
                $this->message->send($chat, $event->getCreator(), true, $url);

                return new Response($this->serializer->serialize($event, "json", ['groups' => 'default']));
            } else {
                throw new HttpException(401, "No puedes rechazar la cita de otro usuario");
            }
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al rechazar la cita - Error: {$ex->getMessage()}");
        }
    }
}
