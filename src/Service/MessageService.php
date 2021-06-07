<?php
// src/Service/NotificationService.php
namespace App\Service;

use App\Entity\Chat;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

class MessageService extends AbstractController
{
    public function __construct(
        EntityManagerInterface $em,
        SerializerInterface $serializer,
        NotificationService $notification
    ) {
        $this->em = $em;
        $this->serializer = $serializer;
        $this->notification = $notification;
    }

    public function send(Chat $chat, $toUser, $sendNotification = false)
    {
        $message = $this->serializer->serialize($chat, "json", ['groups' => 'message', AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true]);

        if ($sendNotification) {
            $fromUser = $chat->getFromuser();
            $title = $fromUser->getName();
            $text = $chat->getText();
            $url = '/chat/' . $chat->getFromuser()->getId();
            $type = 'chat';
            $this->notification->set($fromUser, $toUser, $title, $text, $url, $type, $message);
        } else {
            $tokens = [];
            foreach ($toUser->getDevices() as $device) {
                if ($device->getActive() && !is_null($device->getToken())) {
                    $tokens[] = $device->getToken();
                }
            }

            if (count($tokens) > 0) {
                $data = [
                    'message' => $message
                ];

                $message = CloudMessage::new()
                    ->withData($data);

                try {
                    $messaging = (new Factory())->createMessaging();
                    $report = $messaging->sendMulticast($message, $tokens);
                    // echo 'Successful sends: ' . $report->successes()->count() . PHP_EOL;
                    // echo 'Failed sends: ' . $report->failures()->count() . PHP_EOL;

                    if ($report->hasFailures()) {
                        // echo "Error al enviar el mensaje";
                    }
                } catch (\Kreait\Firebase\Exception\Messaging\NotFound $e) {
                    // echo "Error al enviar el mensaje";
                }
            }
        }
    }
}
