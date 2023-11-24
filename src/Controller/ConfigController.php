<?php
// src/Controller/ChatController.php
namespace App\Controller;

use App\Repository\ConfigRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Class ConfigController
 *
 * @Route(path="/api")
 */
class ConfigController extends AbstractController
{
    private $configRepository;
    private $serializer;

    public function __construct(ConfigRepository $configRepository, SerializerInterface $serializer)
    {
        $this->configRepository = $configRepository;
        $this->serializer = $serializer;
    }

    /**
     * @Route("/config", name="config", methods={"GET"})
     */
    public function getConfig()
    {
        $cache = new FilesystemAdapter();

        try {
            $configCache = $cache->getItem('app.cache');
            if (!$configCache->isHit()) {
                $configCache->expiresAfter(60);
                $config['maintenance'] = (bool) $this->configRepository->findOneBy(['name' => 'maintenance'])->getValue();
                $config['min_version'] = $this->configRepository->findOneBy(['name' => 'min_version'])->getValue();
                $config['chat'] = (bool) $this->configRepository->findOneBy(['name' => 'chat'])->getValue();
                $config['push_url'] = $this->configRepository->findOneBy(['name' => 'push_url'])->getValue();
                $configCache->set($config);
                $cache->save($configCache);
            } else {
                $config = $configCache->get();
            }
            return new JsonResponse($this->serializer->serialize($config, "json"), Response::HTTP_OK, [], true);
        } catch (Exception $ex) {
            throw new HttpException(500, "No se puede obtener la configuración - Error: {$ex->getMessage()}");
        }
    }
}
