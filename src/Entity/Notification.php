<?php

namespace App\Entity;

use App\Repository\NotificationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
class Notification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups('notification')]
    private $id;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'notifications', cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups('notification')]
    private $user;

    #[ORM\Column(type: 'datetime')]
    #[Groups('notification')]
    private $date;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups('notification')]
    private $title;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups('notification')]
    private $body;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups('notification')]
    private $time_read;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups('notification')]
    private $url;

    #[ORM\ManyToOne(targetEntity: User::class, cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    #[Groups('notification')]
    private $fromuser;

    #[ORM\Column(type: 'string', length: 70)]
    #[Groups('notification')]
    private $type;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(): self
    {
        $this->date = new \DateTime;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function setBody(string $body): self
    {
        $this->body = $body;

        return $this;
    }

    public function getTimeRead(): ?\DateTimeInterface
    {
        return $this->time_read;
    }

    public function setTimeRead(?\DateTimeInterface $time_read): self
    {
        $this->time_read = $time_read;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function getFromuser(): ?User
    {
        return $this->fromuser;
    }

    public function setFromuser(?User $fromuser): self
    {
        $this->fromuser = $fromuser;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }
}
