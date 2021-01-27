<?php
// src/Controller/DevicesController.php
namespace App\Controller;

use App\Repository\DeviceRepository;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Device;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Class DevicesController
 *
 * @Route(path="/api")
 */
class DevicesController extends AbstractController
{
    public function __construct(DeviceRepository $deviceRepository, SerializerInterface $serializer, EntityManagerInterface $entityManager)
    {
        $this->deviceRepository = $deviceRepository;
        $this->serializer = $serializer;
        $this->em = $entityManager;
    }

    /**
     * @Route("/v1/devices", name="get_devices", methods={"GET"})
     */
    public function getDevices()
    {
        try {
            $response = $this->getUser()->getDevices();
            return new Response($this->serializer->serialize($response, "json", ['groups' => 'default']));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al obtener los dispositivos - Error: {$ex->getMessage()}");
        }
    }


    /**
     * @Route("/v1/device", name="put_device", methods={"PUT"})
     */
    public function setDeviceAction(Request $request)
    {
        try {
            $this->em->getRepository('App:Device')->set(
                $this->getUser(),
                $this->request->get($request, "id"),
                $this->request->get($request, "name"),
                $this->request->get($request, "token") ?: ""
            );

            return new Response($this->serializer->serialize($this->getUser(), "json", ['groups' => 'default']));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al registrar el dispositivo - Error: {$ex->getMessage()}");
        }
    }


    /**
     * @Route("/v1/unknown-device", name="unknown_device", methods={"PUT"})
     */
    public function unknownDeviceAction(Request $request, \Swift_Mailer $mailer)
    {
        try {
            $device = $this->request->get($request, "device");

            $message = (new \Swift_Message('Aviso de inicio de sesión desde un dispositivo desconocido'))
                ->setFrom(['hola@frikiradar.com' => 'FrikiRadar'])
                ->setTo($this->getUser()->getEmail())
                ->setBody(
                    $this->renderView(
                        "emails/unknown-device.html.twig",
                        [
                            'username' => $this->getUser()->getUsername(),
                            'device' => $device['device_name']
                        ]
                    ),
                    'text/html'
                );

            if (0 === $mailer->send($message)) {
                throw new \RuntimeException('Unable to send email');
            }

            $response = [
                'code' => 200,
                'error' => false,
                'data' => "Email enviado correctamente",
            ];
        } catch (Exception $ex) {
            $response = [
                'code' => 500,
                'error' => true,
                'data' => "Error al enviar el email de verificación - Error: {$ex->getMessage()}",
            ];
        }

        return new Response($this->serializer->serialize($response, "json"));
    }


    /**
     * @Route("/v1/device/{id}", name="delete_device", methods={"DELETE"})
     */
    public function deleteAction(int $id)
    {
        try {
            $user = $this->getUser();
            $device = $this->em->getRepository('App:Device')->findOneBy(array('user' => $user, 'id' => $id));
            $user->removeDevice($device);
            $this->em->persist($user);
            $this->em->flush();

            return new Response($this->serializer->serialize($user, "json", ['groups' => 'default']));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al eliminar el dispositivo - Error: {$ex->getMessage()}");
        }
    }


    /**
     * @Route("/v1/switch-device/{id}", name="switch_device", methods={"GET"})
     */
    public function switchAction(int $id)
    {
        try {
            /**
             * @var Device
             */
            $device = $this->em->getRepository('App:Device')->findOneBy(array('user' => $this->getUser(), 'id' => $id));
            $device->setActive(!$device->getActive());
            $this->em->persist($device);
            $this->em->flush();

            return new Response($this->serializer->serialize($this->getUser(), "json", ['groups' => 'default']));
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al acivar/desactivar el dispositivo - Error: {$ex->getMessage()}");
        }
    }


    /**
     * @Route("/v1/turnoff-device/{uuid}", name="turnoff_device", methods={"GET"})
     */
    public function turnOffAction(string $uuid)
    {
        try {
            /**
             * @var Device
             */
            $device = $this->em->getRepository('App:Device')->findOneBy(array('user' => $this->getUser(), 'deviceId' => $uuid));

            if (!empty($device)) {
                $device->setToken(null);
                $this->em->persist($device);
                $this->em->flush();
                return new Response($this->serializer->serialize($device, "json", ['groups' => 'default']));
            } else {
                return new Response($this->serializer->serialize("Dispositivo no encontrado", "json"));
            }
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al desactivar el dispositivo - Error: {$ex->getMessage()}");
        }
    }
}
