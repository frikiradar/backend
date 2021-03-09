<?php
// src/Controller/RoomsController.php
namespace App\Controller;

use App\Entity\Chat;
use App\Entity\Room;
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
     * @Route("/v1/rooms", name="get_rooms", methods={"GET"})
     */
    public function getRoomsAction()
    {
        $rooms = $this->em->getRepository('App:Room')->findVisibleRooms();
        return new Response($this->serializer->serialize($rooms, "json", ['groups' => ['default']]));
    }
}
