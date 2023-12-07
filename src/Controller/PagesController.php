<?php

namespace App\Controller;

use App\Repository\PageRepository;
use App\Repository\TagRepository;
use App\Service\AccessCheckerService;
use App\Service\RequestService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;


#[Route(path: '/api')]
class PagesController extends AbstractController
{
    private $request;
    private $serializer;
    private $accessChecker;
    private $pageRepository;
    private $tagRepository;

    public function __construct(
        SerializerInterface $serializer,
        RequestService $request,
        AccessCheckerService $accessChecker,
        PageRepository $pageRepository,
        TagRepository $tagRepository
    ) {
        $this->request = $request;
        $this->serializer = $serializer;
        $this->accessChecker = $accessChecker;
        $this->pageRepository = $pageRepository;
        $this->tagRepository = $tagRepository;
    }


    #[Route('/v1/pages', name: 'pages', methods: ['GET'])]
    public function getPages(Request $request)
    {
        $user = $this->getUser();
        $this->accessChecker->checkAccess($user);

        $limit = $this->request->get($request, "limit", false) ?? null;

        try {
            $pages = $this->pageRepository->findPages($user, $limit);

            return new JsonResponse($this->serializer->serialize($pages, "json", ['groups' => 'default']), Response::HTTP_OK, [], true);
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al obtener las páginas - Error: {$ex->getMessage()}");
        }
    }

    #[Route('/v1/page/{slug}', name: 'page', methods: ['GET'])]
    public function getPage(string $slug)
    {
        $user = $this->getUser();
        $this->accessChecker->checkAccess($user);
        $cache = new FilesystemAdapter();
        try {
            $pageCache = $cache->getItem('page.get.' . $slug);
            if (!$pageCache->isHit()) {
                $page = $this->pageRepository->findOneBy(array('slug' => $slug));

                if (isset($page)) {
                    $likes = $this->tagRepository->countTag($page->getSlug(), $page->getName(), $page->getCategory());
                    if (isset($likes['total'])) {
                        $page->setLikes($likes['total']);
                    }

                    $pageCache->expiresAfter(3600 * 1);
                    $pageCache->set($page);
                    $cache->save($pageCache);
                } else {
                    throw new HttpException(404, "Página no encontrada");
                }
            } else {
                $page = $pageCache->get();
            }

            return new JsonResponse($this->serializer->serialize($page, "json", ['groups' => 'default', 'datetime_format' => 'Y-m-d', AbstractObjectNormalizer::SKIP_NULL_VALUES => true]), Response::HTTP_OK, [], true);
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al obtener la página - Error: {$ex->getMessage()}");
        }
    }

    #[Route('/page/{slug}', name: 'public_page', methods: ['GET'])]
    public function getPublicPage(string $slug)
    {
        $cache = new FilesystemAdapter();
        try {
            $pageCache = $cache->getItem('page.get.' . $slug);
            if (!$pageCache->isHit()) {
                $page = $this->pageRepository->findOneBy(array('slug' => $slug));

                if (isset($page)) {
                    $likes = $this->tagRepository->countTag($page->getSlug(), $page->getName(), $page->getCategory());
                    $page->setLikes($likes['total']);
                    $pageCache->expiresAfter(3600 * 1);
                    $pageCache->set($page);
                    $cache->save($pageCache);
                } else {
                    throw new HttpException(404, "Página no encontrada");
                }
            } else {
                $page = $pageCache->get();
            }

            return new JsonResponse($this->serializer->serialize($page, "json", ['groups' => 'default', 'datetime_format' => 'Y-m-d', AbstractObjectNormalizer::SKIP_NULL_VALUES => true]), Response::HTTP_OK, [], true);
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al obtener la página - Error: {$ex->getMessage()}");
        }
    }

    #[Route('/v1/page', name: 'set_page', methods: ['POST'])]
    public function setPage(Request $request)
    {
        $user = $this->getUser();
        $this->accessChecker->checkAccess($user);
        try {
            $tag = $this->tagRepository->findOneBy(array('id' => $this->request->get($request, 'id')));
            $page = $this->pageRepository->setPage($tag);

            if ($page) {
                $cache = new FilesystemAdapter();
                $cache->deleteItem('page.get.' . $page->getSlug());
                return new JsonResponse($this->serializer->serialize($page, "json", ['groups' => 'default']), Response::HTTP_OK, [], true);
            } else {
                throw new HttpException(400, "Error al crear la página");
            }
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al crear la página - Error: {$ex->getMessage()}");
        }
    }
}
