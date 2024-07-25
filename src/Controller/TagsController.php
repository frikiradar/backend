<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Tag;
use App\Repository\CategoryRepository;
use App\Repository\PageRepository;
use App\Repository\TagRepository;
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

#[Route(path: '/api')]
class TagsController extends AbstractController
{
    private $tagRepository;
    private $categoryRepository;
    private $pageRepository;
    private $request;
    private $serializer;

    public function __construct(
        TagRepository $tagRepository,
        CategoryRepository $categoryRepository,
        PageRepository $pageRepository,
        SerializerInterface $serializer,
        RequestService $request,
    ) {
        $this->tagRepository = $tagRepository;
        $this->categoryRepository = $categoryRepository;
        $this->pageRepository = $pageRepository;
        $this->request = $request;
        $this->serializer = $serializer;
    }


    #[Route('/v1/tags', name: 'tags', methods: ['POST'])]
    public function searchTags(Request $request)
    {
        $tags = $this->tagRepository->searchTags($this->request->get($request, 'tag'), $this->request->get($request, 'category'));

        return new JsonResponse($this->serializer->serialize($tags, "json"), Response::HTTP_OK, [], true);
    }

    #[Route('/v1/add-tag', name: 'add_tag', methods: ['PUT'])]
    public function addTag(Request $request)
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $cache = new FilesystemAdapter();
        $cache->deleteItem('users.get.' . $user->getId() . '.' . $user->getId());
        $cache->deleteItem('users.get.' . $user->getId());
        try {
            $name = $this->request->get($request, 'name');
            $slug = $this->request->get($request, 'slug', false);
            $categoryName = $this->request->get($request, 'category');
            $category = $this->categoryRepository->findOneBy(array('name' => $categoryName));
            $oldTag = $this->tagRepository->findOneBy(array('name' => $name, 'user' => $user->getId(), 'category' => !empty($category) ? $category->getId() : null));

            if (empty($oldTag)) {
                $tag = new Tag();
                $tag->setUser($user);
                $tag->setName($name);
                $tag->setCategory($category);

                if (!empty($category)) {
                    $tag->setCategory($category);

                    if (empty($slug)/* && in_array($categoryName, ['games', 'films'])*/) {
                        // Creamos página
                        $page = $this->pageRepository->setPage($tag);
                        if (!empty($page)) {
                            $tag->setSlug($page->getSlug());
                        } else {
                            $tag->setSlug($slug);
                        }
                    } else {
                        $tag->setSlug($slug);
                    }
                } else {
                    $newCategory = new Category();
                    $newCategory->setName($categoryName);
                    $tag->setCategory($newCategory);
                    $this->categoryRepository->save($newCategory);
                }

                $this->tagRepository->save($tag);
                return new JsonResponse($this->serializer->serialize($tag, "json", ['groups' => 'default']), Response::HTTP_OK, [], true);
            } else {
                if (!$oldTag->getSlug()) {
                    $oldTag->setSlug($this->request->get($request, 'slug', false));
                    $this->tagRepository->save($oldTag);
                }
                throw new HttpException(400, "Error al añadir tag. Ya estaba añadida.");
            }
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al añadir tag - Error: {$ex->getMessage()}");
        }
    }

    #[Route('/v1/tag/{id}', name: 'remove_tag', methods: ['DELETE'])]
    public function removeTag($id)
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $cache = new FilesystemAdapter();
        $cache->deleteItem('users.get.' . $user->getId() . '.' . $user->getId());
        $cache->deleteItem('users.get.' . $user->getId());
        try {
            if (!is_numeric($id)) { // TODO: Eliminar medida provisional
                $username = $id;
                $tag = $this->tagRepository->findOneBy(array('name' => $username, 'user' => $user));
            } else {
                $tag = $this->tagRepository->findOneBy(array('id' => $id));
            }

            if ($tag->getUser()->getId() == $user->getId()) {
                $this->tagRepository->remove($tag);

                $data = [
                    'code' => 200,
                    'message' => "Tag eliminada correctamente",
                ];
                return new JsonResponse($data, 200);
            } else {
                throw new HttpException(401, "El usuario no eres tu, ¿intentando hacer trampa?");
            }
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al añadir tag - Error: {$ex->getMessage()}");
        }
    }
}
