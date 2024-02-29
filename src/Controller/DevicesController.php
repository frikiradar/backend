<?php
// src/Controller/DevicesController.php
namespace App\Controller;

use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Device;
use App\Repository\DeviceRepository;
use App\Repository\UserRepository;
use App\Service\RequestService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Serializer\SerializerInterface;

#[Route(path: '/api')]
class DevicesController extends AbstractController
{
    private $serializer;
    private $request;
    private $security;
    private $deviceRepository;
    private $userRepository;

    public function __construct(
        SerializerInterface $serializer,
        RequestService $request,
        AuthorizationCheckerInterface $security,
        DeviceRepository $deviceRepository,
        UserRepository $userRepository
    ) {
        $this->serializer = $serializer;
        $this->request = $request;
        $this->security = $security;
        $this->deviceRepository = $deviceRepository;
        $this->userRepository = $userRepository;
    }

    #[Route('/v1/devices', name: 'get_devices', methods: ['GET'])]
    public function getDevices()
    {
        try {
            /** @var \App\Entity\User $user */
            $user = $this->getUser();
            $response = $user->getDevices();
            return new JsonResponse($this->serializer->serialize($response, "json", ['groups' => 'default']), Response::HTTP_OK, [], true);
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al obtener los dispositivos - Error: {$ex->getMessage()}");
        }
    }


    #[Route('/v1/device', name: 'put_device', methods: ['PUT'])]
    public function setDeviceAction(Request $request)
    {
        try {
            $token = $this->request->get($request, "token") ?: "";
            $platform = $this->request->get($request, "platform", false);
            $this->deviceRepository->set(
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

            return new JsonResponse($this->serializer->serialize($this->getUser(), "json", ['groups' => 'default']), Response::HTTP_OK, [], true);
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al registrar el dispositivo - Error: {$ex->getMessage()}");
        }
    }


    #[Route('/v1/unknown-device', name: 'unknown_device', methods: ['PUT'])]
    public function unknownDeviceAction(Request $request, MailerInterface $mailer)
    {
        try {
            /** @var \App\Entity\User $user */
            $user = $this->getUser();
            $device = $this->request->get($request, "device");
            $language = $user->getLanguage();

            $email = (new Email())
                ->from(new Address('noreply@mail.frikiradar.com', 'frikiradar'))
                ->to(new Address($user->getEmail(), $user->getUsername()))
                ->replyTo(new Address('hola@frikiradar.com', 'frikiradar'))
                ->subject($language == 'es' ? 'Aviso de inicio de sesión desde un dispositivo desconocido' : 'Unknown device login warning')
                ->html($this->renderView(
                    "emails/unknown-device-" . $language . ".html.twig",
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

        return new JsonResponse($this->serializer->serialize($response, "json"), Response::HTTP_OK, [], true);
    }


    #[Route('/v1/device/{id}', name: 'delete_device', methods: ['DELETE'])]
    public function deleteAction(int $id)
    {
        try {
            /** @var \App\Entity\User $user */
            $user = $this->getUser();
            $device = $this->deviceRepository->findOneBy(array('user' => $user, 'id' => $id));
            $user->removeDevice($device);
            $this->userRepository->save($user);

            return new JsonResponse($this->serializer->serialize($user, "json", ['groups' => 'default']), Response::HTTP_OK, [], true);
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al eliminar el dispositivo - Error: {$ex->getMessage()}");
        }
    }


    #[Route('/v1/switch-device/{id}', name: 'switch_device', methods: ['GET'])]
    public function switchAction(int $id)
    {
        try {
            /**
             * @var Device
             */
            $device = $this->deviceRepository->findOneBy(array('user' => $this->getUser(), 'id' => $id));
            $device->setActive(!$device->isActive());
            $this->deviceRepository->save($device);

            return new JsonResponse($this->serializer->serialize($this->getUser(), "json", ['groups' => 'default']), Response::HTTP_OK, [], true);
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al acivar/desactivar el dispositivo - Error: {$ex->getMessage()}");
        }
    }


    #[Route('/v1/turnoff-device/{uuid}', name: 'turnoff_device', methods: ['GET'])]
    public function turnOffAction(string $uuid)
    {
        try {
            /**
             * @var Device
             */
            $device = $this->deviceRepository->findOneBy(array('user' => $this->getUser(), 'device_id' => $uuid));

            if (!empty($device)) {
                $device->setToken(null);
                $this->deviceRepository->save($device);

                return new JsonResponse($this->serializer->serialize($device, "json", ['groups' => 'default']), Response::HTTP_OK, [], true);
            } else {
                return new JsonResponse($this->serializer->serialize("Dispositivo no encontrado", "json"), Response::HTTP_OK, [], true);
            }
        } catch (Exception $ex) {
            throw new HttpException(400, "Error al desactivar el dispositivo - Error: {$ex->getMessage()}");
        }
    }
}
