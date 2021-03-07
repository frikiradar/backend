<?php
// src/Controller/RoomsController.php
namespace App\Controller;

use App\Entity\Chat;
use App\Entity\Rooms;
use App\Repository\ChatRepository;
use App\Service\AccessCheckerService;
use App\Service\FileUploaderService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Mercure\PublisherInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpKernel\Exception\HttpException;
use App\Service\NotificationService;
use App\Service\RequestService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Class RoomsController
 *
 * @Route(path="/api")
 */
class RoomsController extends AbstractController
{
    public function __construct(
        ChatRepository $chatRepository,
        EntityManagerInterface $entityManager,
        SerializerInterface $serializer,
        RequestService $request,
        NotificationService $notification,
        AccessCheckerService $accessChecker
    ) {
        $this->chatRepository = $chatRepository;
        $this->em = $entityManager;
        $this->serializer = $serializer;
        $this->request = $request;
        $this->notification = $notification;
        $this->accessChecker = $accessChecker;
    }

    /**
     * @Route("/v1/room", name="put_rom", methods={"POST"})
     */
    public function put(Request $request, PublisherInterface $publisher)
    {
        $name = $request->request->get("name");
        $description = $request->request->get("description");
        $permissions = [$request->request->get("permissions")];
        $visible = $request->request->get("visible") == 'true' ? true : false;
        $imageFile = $request->files->get('image');
        $creator = $this->getUser();

        try {
            /**
             * @var Rooms
             */
            $room = new Rooms();
            $room->setName($name);
            $room->setDescription($description);
            $room->setPermissions($permissions);
            $room->setVisible($visible);
            $room->setCreator($creator);

            if (!empty($imageFile)) {
                $absolutePath = '/var/www/vhosts/frikiradar.com/app.frikiradar.com/images/rooms/';
                $server = "https://app.frikiradar.com";
                $uploader = new FileUploaderService($absolutePath, $name . '.jpg');
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
}
