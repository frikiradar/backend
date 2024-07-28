<?php
// src/Controller/StoriesController.php
namespace App\Controller;

use App\Entity\Comment;
use App\Entity\LikeStory;
use App\Entity\Story;
use App\Entity\ViewStory;
use App\Repository\BlockUserRepository;
use App\Repository\CommentRepository;
use App\Repository\LikeStoryRepository;
use App\Repository\NotificationRepository;
use App\Repository\StoryRepository;
use App\Repository\UserRepository;
use App\Repository\ViewStoryRepository;
use App\Service\AccessCheckerService;
use App\Service\FileUploaderService;
use App\Service\NotificationService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpKernel\Exception\HttpException;
use App\Service\RequestService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;

#[Route(path: '/api')]
class StoriesController extends AbstractController
{
    private $serializer;
    private $request;
    private $accessChecker;
    private $notification;
    private $security;
    private $storyRepository;
    private $userRepository;
    private $viewStoryRepository;
    private $likeStoryRepository;
    private $blockUserRepository;
    private $commentRepository;
    private $notificationRepostory;

    public function __construct(
        SerializerInterface $serializer,
        RequestService $request,
        AccessCheckerService $accessChecker,
        NotificationService $notification,
        NotificationRepository $notificationRepository,
        AuthorizationCheckerInterface $security,
        StoryRepository $storyRepository,
        UserRepository $userRepository,
        ViewStoryRepository $viewStoryRepository,
        LikeStoryRepository $likeStoryRepository,
        BlockUserRepository $blockUserRepository,
        CommentRepository $commentRepository
    ) {
        $this->serializer = $serializer;
        $this->request = $request;
        $this->accessChecker = $accessChecker;
        $this->notification = $notification;
        $this->security = $security;
        $this->storyRepository = $storyRepository;
        $this->userRepository = $userRepository;
        $this->viewStoryRepository = $viewStoryRepository;
        $this->likeStoryRepository = $likeStoryRepository;
        $this->blockUserRepository = $blockUserRepository;
        $this->commentRepository = $commentRepository;
        $this->notificationRepostory = $notificationRepository;
    }

    #[Route('/v1/stories', name: 'get_stories', methods: ['GET'])]
    public function getStoriesAction(Request $request)
    {
        $page = $this->request->get($request, "page", false) ?? 1;
        $stories = $this->storyRepository->getStories($page);

        return new JsonResponse($this->serializer->serialize($stories, "json", ['groups' => ['story']]), Response::HTTP_OK, [], true);
    }

    #[Route('/v1/stories-slug/{slug}', name: 'get_stories_slug', methods: ['GET'])]
    public function getStoriesSlugAction(string $slug, Request $request)
    {
        $page = $this->request->get($request, "page", false) ?? 1;
        $stories = $this->storyRepository->getStoriesBySlug($slug, $page);

        return new JsonResponse($this->serializer->serialize($stories, "json", ['groups' => ['story']]), Response::HTTP_OK, [], true);
    }

    #[Route('/v1/posts-slug/{slug}', name: 'get_posts_slug', methods: ['GET'])]
    public function getPostsSlugAction(string $slug, Request $request)
    {
        $page = $this->request->get($request, "page", false) ?? 1;
        $posts = $this->storyRepository->getPostsBySlug($slug, $page);

        return new JsonResponse($this->serializer->serialize($posts, "json", ['groups' => ['story']]), Response::HTTP_OK, [], true);
    }

    #[Route('/v1/posts', name: 'posts', methods: ['GET'])]
    public function getPostsAction(Request $request)
    {
        $page = $this->request->get($request, "page", false) ?? 1;
        $filter = $this->request->get($request, "filter", false) ?? 'show-all';

        $posts = $this->storyRepository->getPosts($page, $filter);

        return new JsonResponse($this->serializer->serialize($posts, "json", ['groups' => ['story']]), Response::HTTP_OK, [], true);
    }

    #[Route('/v1/user-stories/{id}', name: 'get_user_stories', methods: ['GET'])]
    public function getUserStoriesAction(int $id)
    {
        $user = $this->userRepository->findOneBy(array('id' => $id));
        $stories = $this->storyRepository->getUserStories($user);
        return new JsonResponse($this->serializer->serialize($stories, "json", ['groups' => ['story']]), Response::HTTP_OK, [], true);
    }

    #[Route('/v1/user-posts/{id}', name: 'get_user_posts', methods: ['GET'])]
    public function getUserPostsAction(int $id, Request $request)
    {
        $page = $this->request->get($request, "page", false) ?? 1;
        $user = $this->userRepository->findOneBy(array('id' => $id));
        $posts = $this->storyRepository->getUserPosts($user, $page);

        return new JsonResponse($this->serializer->serialize($posts, "json", ['groups' => ['story']]), Response::HTTP_OK, [], true);
    }

    #[Route('/v1/story/{id}', name: 'get_story', methods: ['GET'])]
    public function getStoryAction(int $id)
    {
        try {
            /** @var \App\Entity\User $user */
            $user = $this->getUser();

            $story = $this->storyRepository->findOneBy(array('id' => $id));
            $story->setLike($story->isLikedByUser($user));
            $story->setViewed($story->isViewedByUser($user));
            if (!is_null($story)) {
                return new JsonResponse($this->serializer->serialize($story, "json", ['groups' => ['story']]), Response::HTTP_OK, [], true);
            } else {
                throw new HttpException(400, "Historia no encontrada");
            }
        } catch (Exception $ex) {
            throw new HttpException(400, "Historia no encontrada - Error: {$ex->getMessage()}");
        }
    }

    #[Route('/public-story/{id}', name: 'get_public_story', methods: ['GET'])]
    public function getPublicStoryAction(int $id)
    {
        try {
            $story = $this->storyRepository->findOneBy(array('id' => $id));

            if (!is_null($story)) {
                return new JsonResponse($this->serializer->serialize($story, "json", ['groups' => ['story']]), Response::HTTP_OK, [], true);
            } else {
                throw new HttpException(400, "Historia no encontrada");
            }
        } catch (Exception $ex) {
            throw new HttpException(400, "Historia no encontrada - Error: {$ex->getMessage()}");
        }
    }


    #[Route('/v1/story-upload', name: 'story_upload', methods: ['POST'])]
    public function upload(Request $request)
    {
        /** @var \App\Entity\User $fromUser */
        $fromUser = $this->getUser();
        $this->accessChecker->checkAccess($fromUser);
        try {
            $cache = new FilesystemAdapter();
            $cache->deleteItem('stories.get.' . $fromUser->getId());
            $story = new Story();
            $imageFile = $request->files->get('image');
            $text = $request->request->get("text");
            $color = $request->request->get("color");
            $slug = $request->request->get("slug");
            $type = $request->request->get("type") ?? 'story';

            $story->setText($text);
            $story->setColor($color);
            $story->setUser($fromUser);
            $story->setSlug($slug);

            if ($imageFile) {
                $filename = microtime(true);
                $absolutePath = '/var/www/vhosts/frikiradar.com/app.frikiradar.com/images/stories/';
                $server = "https://app.frikiradar.com";
                $uploader = new FileUploaderService($absolutePath . $fromUser->getId() . "/", $filename);
                $image = $uploader->uploadImage($imageFile, false, 80);
                $src = str_replace("/var/www/vhosts/frikiradar.com/app.frikiradar.com", $server, $image);
                $story->setImage($src);
            }

            $story->setTimeCreation();

            if ($type == 'story') {
                $story->setTimeEnd(new \DateTime('+1 day'));
            }
            $story->setType($type);

            $this->storyRepository->save($story);

            if ($type === 'story') {
                $url = "/story/" . $story->getId();
            } else {
                $url = "/post/" . $story->getId();
            }

            if ($fromUser->getId() !== 1) {
                // notificamos a personas interesadas: bien porque le han dado kokoro al usuario o porque tiene intereses en el slug
                $users = $this->userRepository->getInterestedUsers($fromUser, $slug);
                foreach ($users as $userData) {
                    $userId = $userData['id']; // Obten el ID del usuario del array
                    $toUser = $this->userRepository->find($userId); // Obten la entidad User

                    if ($toUser && $toUser->getId() !== $fromUser->getId() && in_array($fromUser->getGender(), $toUser->getLovegender())) {
                        $language = $userData['language'];

                        $title = '@' . $fromUser->getUsername();
                        if ($userData['interestType'] === 'slug') {
                            if ($type === 'story') {
                                $text = $language == 'es'
                                    ? $fromUser->getName() . " compartió una historia sobre " . $slug . " que te podría interesar."
                                    : $fromUser->getName() . " shared a story about " . $slug . " you might be interested in.";
                            } elseif ($type === 'post') {
                                $text = $language == 'es'
                                    ? $fromUser->getName() . " compartió un post sobre " . $slug . " que te podría interesar."
                                    : $fromUser->getName() . " shared a post about " . $slug . " you might be interested in.";
                            }
                        } else { // 'like'
                            if ($type === 'story') {
                                $text = $language == 'es'
                                    ? $fromUser->getName() . ", a quien le has dado kokoro, compartió una historia que te podría interesar."
                                    : $fromUser->getName() . ", who you've given kokoro, shared a story you might be interested in.";
                            } elseif ($type === 'post') {
                                $text = $language == 'es'
                                    ? $fromUser->getName() . ", a quien le has dado kokoro, compartió un post que te podría interesar."
                                    : $fromUser->getName() . ", who you've given kokoro, shared a post you might be interested in.";
                            }
                        }

                        // Asegúrate de que $toUser es una instancia de App\Entity\User
                        $this->notification->set($fromUser, $toUser, $title, $text, $url, "suggestions");
                    }
                }
            }

            $data = [
                'code' => 200,
                'message' => "Historia publicada correctamente",
            ];
            return new JsonResponse($data, 200);
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al subir el archivo - Error: {$ex->getMessage()}");
        }
    }

    #[Route('/v1/view-story', name: 'view_story', methods: ['PUT'])]
    public function putViewAction(Request $request)
    {
        try {
            /** @var \App\Entity\User $user */
            $user = $this->getUser();
            /**
             * @var Story
             */
            $story = $this->storyRepository->findOneBy(array('id' => $this->request->get($request, 'story')));
            $cache = new FilesystemAdapter();
            $cache->deleteItem('stories.get.' . $story->getUser()->getId());

            if ($story->getUser()->getId() !== $user->getId() && !$story->isViewedByUser($user)) {
                $view = new ViewStory();
                $view->setDate(new \DateTime);
                $view->setStory($story);
                $view->setUser($user);

                $this->viewStoryRepository->save($view);

                return new JsonResponse($this->serializer->serialize("Historia vista correctamente", "json"), Response::HTTP_OK, [], true);
            } else {
                throw new HttpException(400, "No puedes marcar como vista tu propia historia o ver la misma historia dos veces.");
            }
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al ver la historia - Error: {$ex->getMessage()}");
        }
    }

    #[Route('/v1/delete-story/{id}', name: 'delete_story', methods: ['DELETE'])]
    public function removeStoryAction(int $id)
    {
        try {
            /** @var \App\Entity\User $user */
            $user = $this->getUser();
            $story = $this->storyRepository->findOneBy(array('id' => $id));

            if ($story->getType() == 'story') {
                $url = "/story/" . $story->getId();
            } else {
                $url = "/post/" . $story->getId();
            }

            // Eliminamos las notificaciones asociadas a la historia
            $this->notificationRepostory->removeByUrl($url);

            $cache = new FilesystemAdapter();
            $cache->deleteItem('stories.get.' . $story->getUser()->getId());
            if ($this->security->isGranted('ROLE_MASTER')) {
                $cache->deleteItem('stories.get.' . $user->getId());
            }

            if ($story->getUser()->getId() === $user->getId() || $this->security->isGranted('ROLE_MASTER')) {
                $image = $story->getImage();
                if ($image) {
                    $f = explode("/", $image);
                    $filename = $f[count($f) - 1];
                    $file = "/var/www/vhosts/frikiradar.com/app.frikiradar.com/images/stories/" . $story->getUser()->getId() . '/' . $filename;
                    unlink($file);
                }

                $this->storyRepository->remove($story);

                $data = [
                    'code' => 200,
                    'message' => "Historia eliminada correctamente",
                ];
                return new JsonResponse($data, 200);
            } else {
                throw new HttpException(404, "No se puede eliminar la historia de otro usuario.");
            }
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al eliminar la historia - Error: {$ex->getMessage()}");
        }
    }

    #[Route('/v1/like-story', name: 'like_story', methods: ['PUT'])]
    public function putLikeAction(Request $request)
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        try {
            $story = $this->storyRepository->findOneBy(array('id' => $this->request->get($request, 'story')));
            $like = $this->likeStoryRepository->findOneBy(array('story' => $story, 'user' => $user));

            if (empty($like)) {
                $newLike = new LikeStory();
                $newLike->setUser($user);
                $newLike->setStory($story);
                $newLike->setDate();
                $this->likeStoryRepository->save($newLike);

                if ($user->getId() !== $story->getUser()->getId() && !$this->security->isGranted('ROLE_DEMO')) {
                    $title = '@' . $user->getUsername();
                    $language = $story->getUser()->getLanguage();
                    if ($language == 'es') {
                        $text = "A " . $user->getName() . " le ha gustado tu publicación.";
                    } else {
                        $text = $user->getName() . " has liked your post.";
                    }

                    if ($story->getType() == 'story') {
                        $url = "/story/" . $story->getId();
                    } else {
                        $url = "/post/" . $story->getId();
                    }

                    $this->notification->set($user, $story->getUser(), $title, $text, $url, "story");
                }
            }

            $story = $this->storyRepository->findOneBy(array('id' => $this->request->get($request, 'story')));
            $story->setLike($story->isLikedByUser($user));
            $story->setViewed($story->isViewedByUser($user));
            $cache = new FilesystemAdapter();
            $cache->deleteItem('stories.get.' . $story->getUser()->getId());

            return new JsonResponse($this->serializer->serialize($story, "json", ['groups' => 'story']), Response::HTTP_OK, [], true);
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al entregar kokoro a una historia - Error: {$ex->getMessage()}");
        }
    }


    #[Route('/v1/like-story/{id}', name: 'unlike_story', methods: ['DELETE'])]
    public function removeLikeAction(int $id)
    {
        $user = $this->getUser();

        try {
            $story = $this->storyRepository->findOneBy(array('id' => $id));
            $like = $this->likeStoryRepository->findOneBy(array('story' => $story, 'user' => $user));
            if (!empty($like)) {
                $this->likeStoryRepository->remove($like);
            }

            $story = $this->storyRepository->findOneBy(array('id' => $id));
            $story->setLike($story->isLikedByUser($user));
            $story->setViewed($story->isViewedByUser($user));
            $cache = new FilesystemAdapter();
            $cache->deleteItem('stories.get.' . $story->getUser()->getId());

            return new JsonResponse($this->serializer->serialize($story, "json", ['groups' => 'story']), Response::HTTP_OK, [], true);
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al retirarle tu kokoro a una historia - Error: {$ex->getMessage()}");
        }
    }

    #[Route('/v1/comment-story', name: 'comment_story', methods: ['PUT'])]
    public function putCommentAction(Request $request)
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $this->accessChecker->checkAccess($user);
        try {
            $story = $this->storyRepository->findOneBy(array('id' => $this->request->get($request, 'story')));

            $comment = new Comment();

            if (empty($this->blockUserRepository->isBlocked($user, $story->getUser()))) {
                $comment->setStory($story);
                $comment->setUser($user);

                $text = $this->request->get($request, "text", false);

                $comment->setText($text);
                $comment->setTimeCreation();

                $mentions = array_unique($this->request->get($request, "mentions", false));
                if ($mentions) {
                    $comment->setMentions($mentions);
                }

                $this->commentRepository->save($comment);

                if ($story->getType() == 'story') {
                    $url = "/story/" . $story->getId();
                } else {
                    $url = "/post/" . $story->getId();
                }

                if (count((array) $mentions) > 0) {
                    foreach ($mentions as $mention) {
                        $toUser = $this->userRepository->findOneBy(array('username' => $mention));
                        if ($toUser->getId() !== $user->getId()) {
                            $language = $toUser->getLanguage();
                            if ($language == 'es') {
                                $title = $user->getUserIdentifier() . ' te ha mencionado en una publicación.';
                            } else {
                                $title = $user->getUserIdentifier() . ' has mentioned you in a post.';
                            }
                            $this->notification->set($user, $toUser, $title, $text, $url, 'story');
                        }
                    }
                } elseif ($user->getId() !== $story->getUser()->getId()) {
                    $language = $story->getUser()->getLanguage();
                    if ($language == 'es') {
                        $title = $user->getName() . ' ha comentado tu publicación.';
                    } else {
                        $title = $user->getName() . ' has commented on your post.';
                    }
                    $this->notification->set($user, $story->getUser(), $title, $text, $url, "story");
                }

                $story = $this->storyRepository->findOneBy(array('id' => $this->request->get($request, 'story')));
                $cache = new FilesystemAdapter();
                $cache->deleteItem('stories.get.' . $story->getUser()->getId());

                return new JsonResponse($this->serializer->serialize($story, "json", ['groups' => 'story']), Response::HTTP_OK, [], true);
            } else {
                throw new HttpException(400, "No se puede comentar a este usuario, estás bloqueado - Error");
            }
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al comentar la historia - Error: {$ex->getMessage()}");
        }
    }

    #[Route('/v1/like-comment', name: 'like_comment', methods: ['PUT'])]
    public function putLikeCommentAction(Request $request)
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        try {
            /**
             * @var Comment
             */
            $comment = $this->commentRepository->findOneBy(array('id' => $this->request->get($request, 'comment')));
            $likes = $comment->getLikes();
            $liked = false;
            foreach ($likes as $like) {
                if ($like->getId() === $user->getId()) {
                    $liked = true;
                }
            }

            if (!$liked) {
                $comment->addLike($user);
                $this->commentRepository->save($comment);

                if ($user->getId() !== $comment->getUser()->getId() && !$this->security->isGranted('ROLE_DEMO')) {
                    $language = $comment->getUser()->getLanguage();
                    $title = '@' . $user->getUsername();
                    if ($language == 'es') {
                        $text = "A " . $user->getName() . " le ha gustado tu comentario.";
                    } else {
                        $text = $user->getName() . " has liked your comment.";
                    }

                    if ($comment->getStory()->getType() == 'story') {
                        $url = "/story/" . $comment->getStory()->getId();
                    } else {
                        $url = "/post/" . $comment->getStory()->getId();
                    }

                    $this->notification->set($user, $comment->getUser(), $title, $text, $url, "story");
                }
            }

            $story = $comment->getStory();
            $cache = new FilesystemAdapter();
            $cache->deleteItem('stories.get.' . $story->getUser()->getId());

            return new JsonResponse($this->serializer->serialize($story, "json", ['groups' => 'story']), Response::HTTP_OK, [], true);
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al entregar kokoro a una historia - Error: {$ex->getMessage()}");
        }
    }


    #[Route('/v1/like-comment/{id}', name: 'unlike_comment', methods: ['DELETE'])]
    public function removeLikeCommentAction(int $id)
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        try {
            /**
             * @var Comment
             */
            $comment = $this->commentRepository->findOneBy(array('id' => $id));
            $likes = $comment->getLikes();
            $liked = false;
            foreach ($likes as $like) {
                if ($like->getId() === $user->getId()) {
                    $liked = true;
                }
            }

            if ($liked) {
                $comment->removeLike($user);
                $this->commentRepository->save($comment);
            }

            $story = $comment->getStory();
            $cache = new FilesystemAdapter();
            $cache->deleteItem('stories.get.' . $story->getUser()->getId());

            return new JsonResponse($this->serializer->serialize($story, "json", ['groups' => 'story']), Response::HTTP_OK, [], true);
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al retirarle tu kokoro a una historia - Error: {$ex->getMessage()}");
        }
    }

    #[Route('/v1/delete-comment/{id}', name: 'delete_comment', methods: ['DELETE'])]
    public function removeCommentAction(int $id)
    {
        try {
            /** @var \App\Entity\User $user */
            $user = $this->getUser();
            $comment = $this->commentRepository->findOneBy(array('id' => $id));
            if ($comment->getUser()->getId() === $user->getId() || $this->security->isGranted('ROLE_MASTER')) {
                $story = $comment->getStory();
                $this->commentRepository->remove($comment);

                $cache = new FilesystemAdapter();
                $cache->deleteItem('stories.get.' . $story->getUser()->getId());
                if ($this->security->isGranted('ROLE_MASTER')) {
                    $cache->deleteItem('stories.get.' . $user->getId());
                }

                return new JsonResponse($this->serializer->serialize($story, "json", ['groups' => 'story']), Response::HTTP_OK, [], true);
            } else {
                throw new HttpException(404, "No se puede eliminar el comentario de otro usuario.");
            }
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al eliminar el comentario - Error: {$ex->getMessage()}");
        }
    }

    #[Route('/v1/report-story', name: 'report_story', methods: ['PUT'])]
    public function putReportStoryAction(Request $request, MailerInterface $mailer)
    {
        try {
            /**
             * @var Story
             */
            $story = $this->request->get($request, 'story', true);
            $note = $this->request->get($request, 'note', false);

            $username = $story->getUser()->getUsername();
            $text = $story->getText();
            $id = $story->getId();

            if ($story->getType() == 'story') {
                $url = "/story/" . $id;
            } else {
                $url = "/post/" . $id;
            }

            // Enviar email al administrador informando del motivo
            /** @var \App\Entity\User $user */
            $user = $this->getUser();
            $email = (new Email())
                ->from(new Address('noreply@mail.frikiradar.com', 'frikiradar'))
                ->to(new Address('hola@frikiradar.com', 'frikiradar'))
                ->subject('Historia reportada')
                ->html("El usuario " . $user->getUsername() . " ha reportado la historia <a href='" . $url . "'" . $id . "'>" . $id . "</a> del usuario <a href='https://frikiradar.app/" . urlencode($username) . "'>" . $username . "</a> por el siguiente motivo: " . $note . "<br><br>Texto de la historia: " . $text);

            $mailer->send($email);

            return new JsonResponse($this->serializer->serialize("Historia reportada correctamente", "json"), Response::HTTP_OK, [], true);
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al reportar la historia - Error: {$ex->getMessage()}");
        }
    }

    #[Route('/v1/report-comment', name: 'report_comment', methods: ['PUT'])]
    public function putReportCommentAction(Request $request, MailerInterface $mailer)
    {
        try {
            $commentId = $this->request->get($request, 'comment', true);
            $note = $this->request->get($request, 'note', false);

            $comment = $this->commentRepository->findOneBy(array('id' => $commentId));
            $story = $comment->getStory();
            $username = $comment->getUser()->getUsername();
            $text = $comment->getText();
            $id = $comment->getId();

            if ($story->getType() == 'story') {
                $url = "/story/" . $id;
            } else {
                $url = "/post/" . $id;
            }

            // Enviar email al administrador informando del motivo
            /** @var \App\Entity\User $user */
            $user = $this->getUser();
            $email = (new Email())
                ->from(new Address('noreply@mail.frikiradar.com', 'frikiradar'))
                ->to(new Address('hola@frikiradar.com', 'frikiradar'))
                ->subject('Comentario reportado')
                ->html("El usuario " . $user->getUsername() . " ha reportado un comentario de la historia <a href='" . $url . "'>" . $story->getId() . "</a> del usuario <a href='https://frikiradar.app/" . urlencode($username) . "'>" . $username . "</a> por el siguiente motivo: " . $note . "<br><br>Texto del comentario: " . $text);

            $mailer->send($email);

            return new JsonResponse($this->serializer->serialize("Comentario reportado correctamente", "json"), Response::HTTP_OK, [], true);
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al reportar el comentario - Error: {$ex->getMessage()}");
        }
    }
}
