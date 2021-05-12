<?php
// src/Service/NotificationService.php
namespace App\Service;

use App\Entity\Notification as EntityNotification;
use App\Entity\User;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\AndroidConfig;
use Doctrine\ORM\EntityManagerInterface;
use Kreait\Firebase\Messaging\ApnsConfig;
use Kreait\Firebase\Messaging\Notification;
use Swift_Mailer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class NotificationService extends AbstractController
{
    public function __construct(Swift_Mailer $mailer, EntityManagerInterface $em)
    {
        $this->mailer = $mailer;
        $this->em = $em;
    }

    public function set(User $fromUser, User $toUser, string $title, string $text, string $url, string $type)
    {
        if ($type !== 'chat') {
            /**
             * @var EntityNotification
             */
            $notification = new EntityNotification();
            $notification->setUser($toUser);
            $notification->setTitle($title);
            $notification->setBody($text);
            $notification->setUrl($url);
            $notification->setDate();
            $notification->setFromuser($fromUser);
            $notification->setType($type);
            $this->em->persist($notification);
            $this->em->flush();
        }

        $cache = new FilesystemAdapter();
        $cache->deleteItem('users.notifications.' . $toUser->getId());
        $cache->deleteItem('users.notifications-list.' . $toUser->getId());

        $this->push($fromUser, $toUser, $title, $text, $url, $type);
    }

    public function push(User $fromUser, User $toUser, string $title, string $text, string $url, string $type)
    {
        $tokens = [];
        foreach ($toUser->getDevices() as $device) {
            if ($device->getActive() && !is_null($device->getToken())) {
                $tokens[] = $device->getToken();
            }
        }

        $sendEmail = false;
        $today = new \DateTime;
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
                'icon' => $fromUser->getAvatar() ?: 'https://api.frikiradar.com/images/notification/logo_icon.png',
                'badge' => 'https://api.frikiradar.com/images/notification/notification_icon.png',
                'topic' => $type,
                // 'notification_foreground' => "true",
                'notification_body' => $text,
                'notification_title' => $title,
                'notification_image' => $fromUser->getAvatar(),
                'notification_android_icon' => 'https://api.frikiradar.com/images/notification/logo_icon.png'
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
                    'icon' => $fromUser->getAvatar() ?: 'https://api.frikiradar.com/images/notification/notification_icon.png',
                    'color' => '#e91e63'
                ],
                'collapse_key' => $tag
            ]);

            $apnsConfig = ApnsConfig::fromArray([
                'payload' => [
                    'aps' => [
                        'alert' => [
                            'title' => $title,
                            'body' => $text,
                        ]
                    ],
                ],
                'fcm_options' => [
                    'image' => $fromUser->getAvatar() ?: 'https://api.frikiradar.com/images/notification/logo_icon.png'
                ]
            ]);

            $message = CloudMessage::new()
                ->withAndroidConfig($androidConfig)
                ->withNotification($notification)
                ->withApnsConfig($apnsConfig)
                ->withData($data);

            try {
                $messaging = (new Factory())->createMessaging();
                $report = $messaging->sendMulticast($message, $tokens);
                // echo 'Successful sends: ' . $report->successes()->count() . PHP_EOL;
                // echo 'Failed sends: ' . $report->failures()->count() . PHP_EOL;

                if ($report->hasFailures()) {
                    foreach ($report->failures()->getItems() as $failure) {
                        // echo $failure->error()->getMessage() . PHP_EOL;
                    }

                    if ($report->failures()->count() >= count($tokens)) {
                        $sendEmail = true;
                        /*if ($today->diff($toUser->getLastLogin())->format('%a') >= 14) {
                            // $toUser->setActive(0);
                            // $this->em->persist($toUser);
                            // $this->em->flush();
                        }*/
                    }
                } /*elseif ($today->diff($toUser->getLastLogin())->format('%a') >= 30) {
                    $toUser->setActive(0);
                    $this->em->persist($toUser);
                    $this->em->flush();
                }*/
            } catch (\Kreait\Firebase\Exception\Messaging\NotFound $e) {
                // echo "Error al enviar la notificación";
            }
        } else {
            $sendEmail = true;
        }

        if ($sendEmail && $today->diff($toUser->getLastLogin())->format('%a') >= 1) {
            if ($toUser->getMailing()) {
                switch ($type) {
                    case 'chat':
                        $title = 'Nuevo mensaje de chat de ' . $fromUser->getName() . ' en FrikiRadar';
                        break;
                    case 'like':
                        $title = 'Nuevo kokoro recibido de ' . $fromUser->getName() . ' en FrikiRadar';
                        $text = $fromUser->getName() . ' te ha entregado su kokoro ❤';
                        break;
                    default:
                        $title = $title . ' en FrikiRadar';
                }

                //Enviar email en lugar de notificación
                $message = (new \Swift_Message($title))
                    ->setFrom(['hola@frikiradar.com' => 'FrikiRadar'])
                    ->setTo($toUser->getEmail())
                    ->setBody(
                        $this->renderView(
                            "emails/notification.html.twig",
                            [
                                'username' => $toUser->getUsername(),
                                'title' => $title,
                                'text' => $text,
                                'url' => 'https://frikiradar.app' . $url
                            ]
                        ),
                        'text/html'
                    );

                $this->mailer->send($message);
            }
        }
    }

    public function pushTopic(User $fromUser, string $topic, string $title, string $text, string $url = '/')
    {
        $notification = Notification::fromArray([
            'title' => $title,
            'body' => $text,
            'image' => $fromUser->getAvatar(),
            'tag' => $topic
        ]);

        $data = [
            'fromUser' => (string) $fromUser->getId(),
            'url' => $url,
            'icon' => $fromUser->getAvatar() ?: 'https://api.frikiradar.com/images/notification/logo_icon.png',
            'topic' => $topic,
            // 'notification_foreground' => "true"
        ];

        $androidConfig = AndroidConfig::fromArray([
            'ttl' => '3600s',
            'priority' => 'high',
            'notification' => [
                'title' => $title,
                'body' => $text,
                'sound' => "default",
                'tag' => $topic,
                'channel_id' => $topic,
                'icon' => $fromUser->getAvatar() ?: 'https://api.frikiradar.com/images/notification/logo_icon.png',
                'color' => '#e91e63'
            ],
            'collapse_key' => $topic
        ]);

        $message = CloudMessage::withTarget('topic', $topic)
            ->withAndroidConfig($androidConfig)
            ->withNotification($notification)
            ->withData($data);

        try {
            $messaging = (new Factory())->createMessaging();
            $messaging->send($message);
        } catch (\Kreait\Firebase\Exception\Messaging\NotFound $e) {
            // echo "Error al enviar la notificación";
        }
    }
}
