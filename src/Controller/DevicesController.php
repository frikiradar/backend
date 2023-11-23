<?php
// src/Controller/DevicesController.php
namespace App\Controller;

use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Device;
use App\Service\RequestService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Class DevicesController
 *
 * @Route(path="/api")
 */
class DevicesController extends AbstractController
{
    private $serializer;
    private $em;
    private $request;
    private $security;

    public function __construct(
        SerializerInterface $serializer,
        EntityManagerInterface $entityManager,
        RequestService $request,
        AuthorizationCheckerInterface $security
    ) {
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
            /** @var \App\Entity\User $user */
            $user = $this->getUser();
            $response = $user->getDevices();
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
            $this->em->getRepository(\App\Entity\Device::class)->set(
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

                curl_setopt($ch, CURLOPT_URL, "https://iid.googleapis.com/iid/v1/$token/rel/topics/frikiradar");
                curl_exec($ch);

                if ($this->security->isGranted('ROLE_MASTER')) {
                    curl_setopt($ch, CURLOPT_URL, "https://iid.googleapis.com/iid/v1/$token/rel/topics/test");
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
    public function unknownDeviceAction(Request $request, MailerInterface $mailer)
    {
        try {
            /** @var \App\Entity\User $user */
            $user = $this->getUser();
            $device = $this->request->get($request, "device");

            $email = (new Email())
                ->from(new Address('hola@frikiradar.com', 'FrikiRadar'))
                ->to(new Address($user->getEmail(), $user->getUsername()))
                ->subject('Aviso de inicio de sesión desde un dispositivo desconocido')
                ->html($this->renderView(
                    "emails/unknown-device.html.twig",
                    [
                        'username' => $user->getUsername(),
                        'device' => $device['device_name']
                    ]
                ));

            $mailer->send($email);

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
            /** @var \App\Entity\User $user */
            $user = $this->getUser();
            $device = $this->em->getRepository(\App\Entity\Device::class)->findOneBy(array('user' => $user, 'id' => $id));
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
            $device = $this->em->getRepository(\App\Entity\Device::class)->findOneBy(array('user' => $this->getUser(), 'id' => $id));
            $device->setActive(!$device->isActive());
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
            $device = $this->em->getRepository(\App\Entity\Device::class)->findOneBy(array('user' => $this->getUser(), 'device_id' => $uuid));

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
