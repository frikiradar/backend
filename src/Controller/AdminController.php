<?php

namespace App\Controller;

use App\Entity\Chat;
use App\Entity\Room;
use App\Service\FileUploaderService;
use App\Service\NotificationService;
use App\Service\RequestService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Serializer\SerializerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\Mercure\PublisherInterface;

/**
 * Class AdminController
 * Require ROLE_MASTER for only this controller method.
 * @IsGranted("ROLE_MASTER")
 * @Route(path="/api")
 */
class AdminController extends AbstractController
{
    public function __construct(
        SerializerInterface $serializer,
        RequestService $request,
        EntityManagerInterface $entityManager,
        NotificationService $notification
    ) {
        $this->request = $request;
        $this->serializer = $serializer;
        $this->em = $entityManager;
        $this->notification = $notification;
    }

    /**
     * @Route("/v1/topic-message", name="topic_message", methods={"PUT"})
     */
    public function putTopicMessage(Request $request)
    {
        $chat = new Chat();

        try {
            $fromUser = $this->em->getRepository('App:User')->findOneBy(array('username' => 'frikiradar'));
            $topic = $this->request->get($request, 'topic');
            $title = $this->request->get($request, 'title') ?: "❤ ¡Información importante! 🎏";
            $text = $this->request->get($request, 'message');
            $url = "/chat/" . $fromUser->getId();

            $chat->setFromuser($fromUser);
            if ($topic == 'test') {
                $chat->setTouser($this->getUser());
            }
            $chat->setText($title . "\r\n\r\n" . $text);
            $chat->setTimeCreation();
            $chat->setConversationId('frikiradar');
            $this->em->persist($chat);
            $this->em->flush();

            $this->notification->pushTopic($fromUser, $topic, $title, $text, $url);

            return new Response($this->serializer->serialize("Notificación enviada correctamente", "json"));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al enviar la notificación global - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Route("/v1/banned-messages/{id}", name="get_banned_messages", methods={"GET"})
     */
    public function getBannedMessagesAction(int $id)
    {
        $fromUser = $this->em->getRepository('App:User')->findOneBy(array('id' => 1));
        $toUser = $this->em->getRepository('App:User')->findOneBy(array('id' => $id));

        //marcamos como leidos los antiguos
        $unreadChats = $this->em->getRepository('App:Chat')->findBy(array('fromuser' => $toUser->getId(), 'touser' => $fromUser->getId(), 'time_read' => null));
        foreach ($unreadChats as $chat) {
            if (!is_null($chat->getFromUser())) {
                $chat->setTimeRead(new \DateTime);
                $this->em->persist($chat);
            }
        }
        $this->em->flush();

        $chats = $this->em->getRepository('App:Chat')->getChat($fromUser, $toUser, true, 1, 0, true);

        return new Response($this->serializer->serialize($chats, "json", ['groups' => 'message']));
    }

    /**
     * @Route("/v1/banned-message", name="banned_message", methods={"PUT"})
     */
    public function put(Request $request)
    {
        $fromUser = $this->em->getRepository('App:User')->findOneBy(array('id' => 1));
        $id = $this->request->get($request, "touser");

        $chat = new Chat();
        $toUser = $this->em->getRepository('App:User')->find($id);

        $chat->setTouser($toUser);
        $chat->setFromuser($fromUser);

        $conversationId = 1 . "_" . $chat->getTouser()->getId();

        $text = $this->request->get($request, "text", false);

        $chat->setText($text);
        $chat->setTimeCreation();
        $chat->setConversationId($conversationId);
        $this->em->persist($chat);
        $this->em->flush();

        $title = $fromUser->getUsername();
        $url = "/login/banned-account";

        $this->notification->push($fromUser, $toUser, $title, $text, $url, "chat");

        return new Response($this->serializer->serialize($chat, "json", ['groups' => 'message']));
    }

    /**
     * @Route("/v1/ban", name="ban", methods={"PUT"})
     */
    public function ban(Request $request)
    {
        try {
            $toUser = $this->em->getRepository('App:User')->find($this->request->get($request, "touser"));
            $reason = $this->request->get($request, 'message');
            $days = $this->request->get($request, 'days', false);
            $hours = $this->request->get($request, 'hours', false);
            $this->em->getRepository('App:User')->banUser($toUser, $reason, $days, $hours);

            return new Response($this->serializer->serialize("Baneo realizado correctamente", "json"));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al realizar el baneo - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Route("/v1/bans", name="bans", methods={"GET"})
     */
    public function getBansAction()
    {
        $users = $this->em->getRepository('App:User')->getBanUsers();

        return new Response($this->serializer->serialize($users, "json", ['groups' => 'default']));
    }

    /**
     * @Route("/v1/ban/{id}", name="unban", methods={"DELETE"})
     */
    public function removeBanAction(int $id)
    {
        try {
            /**
             * @var User
             */
            $user = $this->em->getRepository('App:User')->findOneBy(array('id' => $id));

            $user->setBanned(0);
            $user->setBanReason(null);
            $user->setBanEnd(null);
            $this->em->persist($user);
            $this->em->flush();

            $users = $this->em->getRepository('App:User')->getBanUsers();

            return new Response($this->serializer->serialize($users, "json", ['groups' => 'default']));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al desbanear al usuario - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Route("/v1/warn", name="warn_chat", methods={"PUT"})
     */
    public function warn(Request $request)
    {
        $chat = new Chat();

        try {
            $fromUser = $this->em->getRepository('App:User')->findOneBy(array('username' => 'frikiradar'));
            $toUser = $this->em->getRepository('App:User')->find($this->request->get($request, "touser"));
            $title = "⚠️ Aviso de moderación";
            $text = $this->request->get($request, 'message');
            $url = "/chat/" . $fromUser->getId();

            $chat->setFromuser($fromUser);
            $chat->setTouser($toUser);

            $chat->setText($title . "\r\n\r\n" . $text);
            $chat->setTimeCreation();
            $chat->setConversationId('frikiradar');
            $this->em->persist($chat);
            $this->em->flush();

            $this->notification->push($fromUser, $toUser, $title, $text, $url, "chat");

            return new Response($this->serializer->serialize("Aviso enviado correctamente", "json"));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al enviar el aviso de moderación - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Route("/v1/room", name="create_rom", methods={"POST"})
     */
    public function createRoomAction(Request $request, PublisherInterface $publisher)
    {
        $name = $request->request->get("name");
        $description = $request->request->get("description");
        $permissions = [$request->request->get("permissions")];
        $visible = $request->request->get("visible") == 'true' ? true : false;
        $imageFile = $request->files->get('image');
        $creator = $this->getUser();

        try {
            /**
             * @var Room
             */
            $room = new Room();
            $room->setName($name);
            $slug = str_replace(' ', '-', strtolower($name));
            $room->setSlug($slug);
            $room->setDescription($description);
            $room->setPermissions($permissions);
            $room->setVisible($visible);
            $room->setCreator($creator);

            if (!empty($imageFile)) {
                $absolutePath = '/var/www/vhosts/frikiradar.com/app.frikiradar.com/images/rooms/';
                $server = "https://app.frikiradar.com";
                $uploader = new FileUploaderService($absolutePath, $name);
                $image = $uploader->upload($imageFile, true, 70);
                $src = str_replace("/var/www/vhosts/frikiradar.com/app.frikiradar.com", $server, $image);
                $room->setImage($src);
            }

            $this->em->persist($room);
            $this->em->flush();
            // TODO: Estaría guay que cuando se cree una nueva sala llegue una notificación invitando a los usuarios o algo así
            return new Response($this->serializer->serialize($room, "json", ['groups' => 'default']));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al crear la sala - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Route("/v1/admin-rooms", name="admin_rooms", methods={"GET"})
     */
    public function getRoomsAction()
    {
        $rooms = $this->em->getRepository('App:Room')->findAll();
        return new Response($this->serializer->serialize($rooms, "json", ['groups' => ['default']]));
    }

    /**
     * @Route("/v1/edit-room", name="edit_room", methods={"POST"})
     */
    public function editRoomAction(Request $request, PublisherInterface $publisher)
    {
        $id = +$request->request->get("id");
        $name = $request->request->get("name");
        $description = $request->request->get("description");
        $permissions = [$request->request->get("permissions")];
        $visible = $request->request->get("visible") == 'true' ? true : false;
        $imageFile = $request->files->get('image');

        try {
            /**
             * @var Room
             */
            $room = $this->em->getRepository('App:Room')->findOneBy(array('id' => $id));
            $room->setName($name);
            $room->setDescription($description);
            $room->setPermissions($permissions);
            $room->setVisible($visible);

            if (!empty($imageFile)) {
                $image = $room->getImage();
                if ($image) {
                    $name = $room->getName();
                    $file = "/var/www/vhosts/frikiradar.com/app.frikiradar.com/images/rooms/" . $name . '.jpg';
                    unlink($file);
                }
                $absolutePath = '/var/www/vhosts/frikiradar.com/app.frikiradar.com/images/rooms/';
                $server = "https://app.frikiradar.com";
                $uploader = new FileUploaderService($absolutePath, $name);
                $image = $uploader->upload($imageFile, true, 70);
                $src = str_replace("/var/www/vhosts/frikiradar.com/app.frikiradar.com", $server, $image);
                $room->setImage($src);
            }

            $this->em->persist($room);
            $this->em->flush();
            return new Response($this->serializer->serialize($room, "json", ['groups' => 'default']));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al editar la sala - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Route("/v1/delete-room/{id}", name="remove_room", methods={"DELETE"})
     */
    public function removeRoomAction(int $id)
    {
        try {
            /**
             * @var Room
             */
            $room = $this->em->getRepository('App:Room')->findOneBy(array('id' => $id));
            $image = $room->getImage();
            if ($image) {
                $name = $room->getName();
                $file = "/var/www/vhosts/frikiradar.com/app.frikiradar.com/images/rooms/" . $name . '.jpg';
                unlink($file);
            }

            $this->em->remove($room);
            $this->em->flush();

            $rooms = $this->em->getRepository('App:Room')->findAll();

            return new Response($this->serializer->serialize($rooms, "json", ['groups' => 'default']));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al desbanear al usuario - Error: {$ex->getMessage()}");
        }
    }
}
