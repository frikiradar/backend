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
use App\Service\RequestService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Class DevicesController
 *
 * @Route(path="/api")
 */
class DevicesController extends AbstractController
{
    public function __construct(
        DeviceRepository $deviceRepository,
        SerializerInterface $serializer,
        EntityManagerInterface $entityManager,
        RequestService $request,
        AuthorizationCheckerInterface $security
    ) {
        $this->deviceRepository = $deviceRepository;
        $this->serializer = $serializer;
        $this->em = $entityManager;
        $this->request = $request;
        $this->security = $security;
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
            $token = $this->request->get($request, "token") ?: "";
            $platform = $this->request->get($request, "platform", false);
            $this->em->getRepository('App:Device')->set(
                $this->getUser(),
                $this->request->get($request, "id"),
                $this->request->get($request, "name"),
                $token,
                $platform,
            );

            if ($token) {
                $key = 'AAAAZI4Tcp4:APA91bHi1b30Lb-c-AvrqhFBLcBrFOf2fwEn417i9UQvmJra7VgMl8LMgCfQjgNtQ4aMdCBOnYX9q7kWlnLrN9jpnSUUM-hyqYeXLuegLeFiqVHTNboEv3-EIuNsIi6sg7LW6UykvzEZ';
                $headers = array('Authorization: key=' . $key, 'Content-Type: application/json');

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_POSTFIELDS, array());
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

                curl_setopt($ch, CURLOPT_URL, "https://iid.googleapis.com/iid/v1/$token/rel/topics/rooms");
                curl_exec($ch);

                curl_setopt($ch, CURLOPT_URL, "https://iid.googleapis.com/iid/v1/$token/rel/topics/frikiradar");
                curl_exec($ch);

                if ($this->security->isGranted('ROLE_MASTER')) {
                    curl_setopt($ch, CURLOPT_URL, "https://iid.googleapis.com/iid/v1/$token/rel/topics/test");
                    curl_exec($ch);
                }

                if ($this->security->isGranted('ROLE_PATREON') || $this->security->isGranted('ROLE_MASTER')) {
                    curl_setopt($ch, CURLOPT_URL, "https://iid.googleapis.com/iid/v1/$token/rel/topics/patreon");
                    curl_exec($ch);
                }

                curl_close($ch);
            }

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
                ->setFrom(['noreply@frikiradar.app' => 'FrikiRadar'])
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
            $device = $this->em->getRepository('App:Device')->findOneBy(array('user' => $this->getUser(), 'device_id' => $uuid));

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
