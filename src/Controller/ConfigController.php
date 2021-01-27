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

/**
 * Class ConfigController
 *
 * @Route(path="/api")
 */
class ConfigController extends AbstractController
{
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
        try {
            $config['maintenance'] = (bool) $this->configRepository->findOneBy(['name' => 'maintenance'])->getValue();
            $config['min_version'] = $this->configRepository->findOneBy(['name' => 'min_version'])->getValue();
            $config['chat'] = (bool) $this->configRepository->findOneBy(['name' => 'chat'])->getValue();
            $config['push_url'] = $this->configRepository->findOneBy(['name' => 'push_url'])->getValue();
            $config['patreon'] = $this->configRepository->findOneBy(['name' => 'patreon'])->getValue();

            return new Response($this->serializer->serialize($config, "json"));
        } catch (Exception $ex) {
            throw new HttpException(500, "No se puede obtener la configuración - Error: {$ex->getMessage()}");
        }
    }
}
