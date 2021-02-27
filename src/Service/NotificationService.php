<?php
// src/Service/NotificationService.php
namespace App\Service;

use App\Entity\User;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\AndroidConfig;
use Doctrine\ORM\EntityManagerInterface;
use Kreait\Firebase\Messaging\RawMessageFromArray;

class NotificationService
{
    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function push(User $fromUser, User $toUser, string $title, string $text, string $url, string $type)
    {
        $tokens = [];
        foreach ($toUser->getDevices() as $device) {
            if ($device->getActive() && !is_null($device->getToken())) {
                $tokens[] = $device->getToken();
            }
        }

        if (count($tokens) > 0) {
            $tag = $type . '_' . $title;

            $notification = Notification::fromArray([
                'title' => $title,
                'body' => $text,
                'image' => $fromUser->getAvatar()
            ]);

            $data = [
                'fromUser' => (string) $fromUser->getId(),
                'toUser' => (string) $toUser->getId(),
                'url' => $url,
                'icon' => $fromUser->getAvatar(),
                'topic' => $type
            ];

            $androidConfig = AndroidConfig::fromArray([
                'ttl' => '3600s',
                'priority' => 'high',
                'notification' => [
                    'title' => $title,
                    'body' => $text,
                    'sound' => "default",
                    'tag' => $tag,
                    'channel_id' => $type,
                    'icon' => $fromUser->getAvatar(),
                    'color' => '#e91e63'
                ],
                'collapse_key' => $tag
            ]);

            $message = CloudMessage::new()
                ->withAndroidConfig($androidConfig)
                ->withNotification($notification)
                ->withData($data);

            try {
                $messaging = (new Factory())->createMessaging();
                $report = $messaging->sendMulticast($message, $tokens);
                // TODO: Token caducado o erróneo, desactivar. Si la cuenta no tiene tokens activos entonces no aparecer en resultados de radar.
                // echo 'Successful sends: ' . $report->successes()->count() . PHP_EOL;
                // echo 'Failed sends: ' . $report->failures()->count() . PHP_EOL;

                if ($report->hasFailures()) {
                    foreach ($report->failures()->getItems() as $failure) {
                        // echo $failure->error()->getMessage() . PHP_EOL;
                    }

                    $today = new \DateTime;
                    if ($report->failures()->count() >= count($tokens) && $today->diff($toUser->getLastLogin())->format('%a') >= 14) {
                        $toUser->setActive(0);
                        $this->em->persist($toUser);
                        $this->em->flush();
                    }
                }
            } catch (\Kreait\Firebase\Exception\Messaging\NotFound $e) {
                // echo "Error al enviar la notificación";
            }
        } else {
            // TODO: Cuenta no activa, desactivar. Revisar porque version web no activa tokens
        }
    }

    public function pushTopic(User $fromUser, string $topic, string $title, string $text, string $url = '/')
    {
        //fromUser debe ser frikiradar, el user 1 y el toUser un string con el 'topic'
        /*$message = new RawMessageFromArray([
            'topic' => $topic,
            'notification' => [
                'title' => $title,
                'body' => $text,
                'image' => $fromUser->getAvatar(),
            ],
            'data' => [
                'fromUser' => (string) $fromUser->getId(),
                'url' => $url,
                'icon' => $fromUser->getAvatar(),
                'topic' => $topic
            ],
            'android' => [
                'ttl' => '3600s',
                'priority' => 'high',
                'notification' => [
                    'title' => $title,
                    'body' => $text,
                    'sound' => "default",
                    'tag' => $topic,
                    'channel_id' => $topic,
                    'icon' => $fromUser->getAvatar(),
                    'color' => '#e91e63'
                ],
                'collapse_key' => $topic
            ]
        ]);*/

        $message = CloudMessage::fromArray([
            'topic' => $topic,
            'notification' => [
                'title' => $title,
                'body' => $text,
                'sound' => 'default',
                'channel_id' => $topic,
                'tag' => $topic
            ],
            'data' => [
                'fromUser' => (string) $fromUser->getId(),
                'url' => $url,
                'topic' => $topic,
                'icon' => $fromUser->getAvatar()
            ],
            'collapse_key' => $topic
        ]);

        $androidConfig = AndroidConfig::fromArray([
            'ttl' => '3600s',
            'priority' => 'high',
            'notification' => [
                'title' => $title,
                'body' => $text,
                'sound' => 'default',
                'channel_id' => $topic,
                'icon' => $fromUser->getAvatar()
            ],
            'data' => [
                'url' => $url,
                'topic' => $topic,
                'icon' => $fromUser->getAvatar()
            ],
            'collapse_key' => $topic
        ]);

        $message = $message->withAndroidConfig($androidConfig);*/

        try {
            $messaging = (new Factory())->createMessaging();
            $messaging->send($message);
        } catch (\Kreait\Firebase\Exception\Messaging\NotFound $e) {
            // echo "Error al enviar la notificación";
        }
    }
}
