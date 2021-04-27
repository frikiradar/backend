<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Page;
use App\Entity\Room;
use App\Entity\Tag;
use App\Service\AccessCheckerService;
use App\Service\RequestService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;

/**
 * Class PagesController
 *
 * @Route(path="/api")
 */
class PagesController extends AbstractController
{
    public function __construct(
        SerializerInterface $serializer,
        RequestService $request,
        AccessCheckerService $accessChecker,
        EntityManagerInterface $entityManager
    ) {
        $this->request = $request;
        $this->serializer = $serializer;
        $this->accessChecker = $accessChecker;
        $this->em = $entityManager;
    }


    /**
     * @Route("/v1/pages", name="pages", methods={"GET"})
     */
    public function getPages()
    {
        $user = $this->getUser();
        $this->accessChecker->checkAccess($user);

        try {
            $pages = $this->em->getRepository('App:Page')->findPages($user);
            return new Response($this->serializer->serialize($pages, "json", ['groups' => 'default']));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al obtener las páginas - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Route("/v1/page/{slug}", name="page", methods={"GET"})
     */
    public function getPage(string $slug)
    {
        $user = $this->getUser();
        $this->accessChecker->checkAccess($user);
        $cache = new FilesystemAdapter();
        try {
            $cache->deleteItem('page.get.' . $slug);
            $pageCache = $cache->getItem('page.get.' . $slug);
            if (!$pageCache->isHit()) {
                $page = $this->em->getRepository('App:Page')->findOneBy(array('slug' => $slug));

                if (isset($page)) {
                    $room = new Room();
                    $room->setName($page->getName());
                    $room->setDescription($page->getDescription());
                    $room->setImage($page->getCover());
                    $room->setSlug($slug);
                    $room->setVisible(false);
                    $room->setPermissions(['ROLE_USER']);
                    $page->setRoom($room);

                    $likes = $this->em->getRepository('App:Tag')->countTag($page->getName(), $page->getCategory());
                    $page->setLikes($likes['total']);
                    $pageCache->expiresAfter(3600 * 24);
                    $pageCache->set($page);
                    $cache->save($pageCache);
                } else {
                    throw new HttpException(404, "Página no encontrada");
                }
            } else {
                $page = $pageCache->get();
            }

            $messages = $this->em->getRepository('App:Room')->getLastMessages([$slug], $user);
            if (isset($messages[0])) {
                $page->getRoom()->setLastMessage($messages[0]['last_message']);
            }

            return new Response($this->serializer->serialize($page, "json", ['groups' => 'default', 'datetime_format' => 'Y-m-d', AbstractObjectNormalizer::SKIP_NULL_VALUES => true]));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al obtener la página - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Route("/v1/page", name="set_page", methods={"POST"})
     */
    public function setPage(Request $request)
    {
        $user = $this->getUser();
        $this->accessChecker->checkAccess($user);
        try {
            $tag = $this->em->getRepository('App:Tag')->findOneBy(array('id' => $this->request->get($request, 'id')));
            $name = $tag->getName();
            $category = $tag->getCategory()->getName();
            switch ($category) {
                case 'games':
                    $result = $this->em->getRepository('App:Page')->getGamesApi($name);
                    break;

                case 'films':
                    $result = $this->em->getRepository('App:Page')->getFilmsApi($name);
                    break;
            }

            if ($result) {
                $slug = $result['slug'];
                $page = $this->em->getRepository('App:Page')->findOneBy(array('slug' => $result['slug']));
                $oldPage = $page;

                if (empty($oldPage) || (null !== $oldPage && $oldPage->getCategory() !== $category)) {
                    /**
                     * @var Page
                     */
                    $page = new Page();
                    $page->setName($result['name']);
                    $page->setDescription($result['description']);
                    $page->setSlug($result['slug'] . (null !== $oldPage && $oldPage->getCategory() !== $category ? '-' . $category : ''));
                    $page->setRating($result['rating']);
                    $page->setCategory($category);
                    if (isset($result['developer'])) {
                        $page->setDeveloper($result['developer']);
                    }
                    $page->setReleaseDate($result['release_date']);
                    $page->setTimeCreation();
                    $page->setLastUpdate();
                    if (isset($result['game_mode'])) {
                        $page->setGameMode($result['game_mode']);
                    }
                    $page->setCover($result['cover']);
                    $page->setArtwork($result['artwork']);

                    $this->em->persist($page);
                    $this->em->flush();
                }
            } else {
                $slug = $this->em->getRepository('App:Page')->nameToSlug($name);
                $page = $this->em->getRepository('App:Page')->findOneBy(array('slug' => $slug));
                if (empty($page)) {
                    /**
                     * @var Page
                     */
                    $page = new Page();
                    $page->setName($name);
                    $page->setSlug($slug);
                    $page->setTimeCreation();
                    $page->setLastUpdate();
                    $page->setCategory($tag->getCategory()->getName());
                    $this->em->persist($page);
                    $this->em->flush();
                }
            }

            // actualizamos todas las etiquetas con este mismo nombre de esta categoria
            $this->em->getRepository('App:Tag')->setTagsSlug($tag, $slug);

            $cache = new FilesystemAdapter();
            $cache->deleteItem('page.get.' . $slug);

            return new Response($this->serializer->serialize($page, "json", ['groups' => 'default']));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al crear la página - Error: {$ex->getMessage()}");
        }
    }
}
