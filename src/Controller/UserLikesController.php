<?php
// src/Controller/ChatController.php
namespace App\Controller;

use App\Entity\LikeUser;
use App\Repository\LikeUserRepository;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpKernel\Exception\HttpException;
use App\Service\NotificationService;
use App\Service\RequestService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Serializer\SerializerInterface;

#[Route(path: '/api')]
class UserLikesController extends AbstractController
{
    private $serializer;
    private $request;
    private $notification;
    private $security;
    private $likeUserRepository;
    private $userRepository;

    public function __construct(
        SerializerInterface $serializer,
        RequestService $request,
        NotificationService $notification,
        AuthorizationCheckerInterface $security,
        LikeUserRepository $likeUserRepository,
        UserRepository $userRepository
    ) {
        $this->serializer = $serializer;
        $this->request = $request;
        $this->notification = $notification;
        $this->security = $security;
        $this->likeUserRepository = $likeUserRepository;
        $this->userRepository = $userRepository;
    }


    #[Route('/v1/like', name: 'like', methods: ['PUT'])]
    public function putLikeAction(Request $request)
    {
        /** @var \App\Entity\User $fromUser */
        $fromUser = $this->getUser();
        try {
            $toUser = $this->userRepository->findOneBy(array('id' => $this->request->get($request, 'user')));
            $cache = new FilesystemAdapter();
            $cache->deleteItem('users.get.' . $fromUser->getId() . '.' . $toUser->getId());
            $cache->deleteItem('users.get.' . $toUser->getId());

            $like = $this->likeUserRepository->findOneBy(array('to_user' => $toUser, 'from_user' => $fromUser()));

            if (empty($like)) {
                $newLike = new LikeUser();
                $newLike->setFromUser($fromUser);
                $newLike->setToUser($toUser);
                $this->likeUserRepository->save($newLike);

                $title = $fromUser->getUsername();
                $text = "Te ha entregado su kokoro ❤️. Haz click aquí para ver su perfil.";
                $url = "/profile/" . $fromUser->getId();

                $this->notification->set($fromUser, $toUser, $title, $text, $url, "like");
            }

            $user = $this->userRepository->findOneUser($fromUser, $toUser);

            return new JsonResponse($this->serializer->serialize($user, "json", ['groups' => 'default']), Response::HTTP_OK, [], true);
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al entregar tu kokoro - Error: {$ex->getMessage()}");
        }
    }


    #[Route('/v1/like/{id}', name: 'unlike', methods: ['DELETE'])]
    public function removeLikeAction(int $id)
    {
        /** @var \App\Entity\User $fromUser */
        $fromUser = $this->getUser();
        try {
            $toUser = $this->userRepository->findOneBy(array('id' => $id));
            $cache = new FilesystemAdapter();
            $cache->deleteItem('users.get.' . $fromUser->getId() . '.' . $toUser->getId());
            $cache->deleteItem('users.get.' . $toUser->getId());

            $like = $this->likeUserRepository->findOneBy(array('to_user' => $toUser, 'from_user' => $this->getUser()));
            $this->likeUserRepository->remove($like);

            $user = $this->userRepository->findOneUser($fromUser, $toUser);

            return new JsonResponse($this->serializer->serialize($user, "json", ['groups' => 'default']), Response::HTTP_OK, [], true);
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al retirarle tu kokoro - Error: {$ex->getMessage()}");
        }
    }


    #[Route('/v1/likes', name: 'get_likes', methods: ['GET'])]
    public function getLikesAction(Request $request)
    {
        $cache = new FilesystemAdapter();
        try {
            /** @var \App\Entity\User $user */
            $param = $this->request->get($request, "param") ?: "received";
            $page = $this->request->get($request, "page", false);
            $id = $this->request->get($request, "user", false);
            if ($id) {
                /** @var \App\Entity\User $user */
                $user = $this->userRepository->findOneBy(array('id' => $id));
            } else {
                /** @var \App\Entity\User $user */
                $user = $this->getUser();
            }

            if ($user->getId() === $user->getId() || !$user->isHideLikes() || $this->security->isGranted('ROLE_MASTER')) {
                $likesCache = $cache->getItem('users.likes.' . $user->getId() . $param . $page);
                if (!$likesCache->isHit()) {
                    $likesCache->expiresAfter(5 * 60);
                    $likes = $this->likeUserRepository->getLikeUsers($user, $param, $page);
                    $likesCache->set($likes);
                    $cache->save($likesCache);
                } else {
                    $likes = $likesCache->get();
                }
                return new JsonResponse($this->serializer->serialize($likes, "json", ['groups' => 'default']), Response::HTTP_OK, [], true);
            } else {
                throw new HttpException(400, "El usuario no permite ver sus kokoros");
            }
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al obtener los likes - Error: {$ex->getMessage()}");
        }
    }


    #[Route('/v1/read-like/{id}', name: 'read_like', methods: ['GET'])]
    public function markAsReadAction(int $id)
    {
        try {
            /** @var \App\Entity\User $user */
            $user = $this->getUser();
            $like = $this->likeUserRepository->findOneBy(array('from_user' => $id, 'to_user' => $user->getId()));
            $like->setTimeRead(new \DateTime);
            $this->likeUserRepository->save($like);

            return new JsonResponse($this->serializer->serialize($like, "json", ['groups' => 'like']), Response::HTTP_OK, [], true);
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al marcar como leido - Error: {$ex->getMessage()}");
        }
    }
}
