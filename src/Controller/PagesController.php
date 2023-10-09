<?php

namespace App\Controller;

use App\Entity\Page;
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
            // recogemos los tags del usuario y vemos si hay alguno sin slug y generamos páginas nuevas
            /*$tags = $user->getTags();
            foreach ($tags as $tag) {
                $category = $tag->getCategory()->getName();
                if (in_array($category, array('films', 'games'))) {
                    $slug = $tag->getSlug();
                    if (!isset($slug)) {
                        $page = $this->em->getRepository('App:Page')->setPage($tag);
                        $tag->setSlug($page->getSlug());
                    }
                }
            }*/

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
            $pageCache = $cache->getItem('page.get.' . $slug);
            if (!$pageCache->isHit()) {
                $page = $this->em->getRepository('App:Page')->findOneBy(array('slug' => $slug));

                if (isset($page)) {
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

            return new Response($this->serializer->serialize($page, "json", ['groups' => 'default', 'datetime_format' => 'Y-m-d', AbstractObjectNormalizer::SKIP_NULL_VALUES => true]));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al obtener la página - Error: {$ex->getMessage()}");
        }
    }

    /**
     * @Route("/page/{slug}", name="public_page", methods={"GET"})
     */
    public function getPublicPage(string $slug)
    {
        $cache = new FilesystemAdapter();
        try {
            $pageCache = $cache->getItem('page.get.' . $slug);
            if (!$pageCache->isHit()) {
                $page = $this->em->getRepository('App:Page')->findOneBy(array('slug' => $slug));

                if (isset($page)) {
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
            $page = $this->em->getRepository('App:Page')->setPage($tag);

            $cache = new FilesystemAdapter();
            $cache->deleteItem('page.get.' . $page->getSlug());

            return new Response($this->serializer->serialize($page, "json", ['groups' => 'default']));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al crear la página - Error: {$ex->getMessage()}");
        }
    }
}
