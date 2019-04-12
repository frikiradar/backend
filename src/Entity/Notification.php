<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\Groups;
use JMS\Serializer\Annotation\MaxDepth;

/**
 * @ORM\Entity(repositoryClass="App\Repository\NotificationRepository")
 */
class Notification
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User", inversedBy="notifications")
     * @ORM\JoinColumn(nullable=false)
     * @Groups({"default"})
     * @MaxDepth(1)
     */
    private $toUser;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User")
     * @Groups({"default"})
     * @MaxDepth(1)
     */
    private $fromUser;

    /**
     * @ORM\Column(type="datetime")
     * @Groups({"default"})
     */
    private $timeCreation;

    /**
     * @ORM\Column(type="string", length=255)
     * @Groups({"default"})
     */
    private $title;

    /**
     * @ORM\Column(type="string", length=255)
     * @Groups({"default"})
     */
    private $text;

    /**
     * @ORM\Column(type="string", length=255)
     * @Groups({"default"})
     */
    private $url;

    /**
     * @ORM\Column(type="string", length=70)
     * @Groups({"default"})
     */
    private $type;

    /**
     * @ORM\Column(type="boolean")
     * @Groups({"default"})
     */
    private $viewed;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getToUser(): ?User
    {
        return $this->toUser;
    }

    public function setToUser(?User $toUser): self
    {
        $this->toUser = $toUser;

        return $this;
    }

    public function getFromUser(): ?User
    {
        return $this->fromUser;
    }

    public function setFromUser(?User $fromUser): self
    {
        $this->fromUser = $fromUser;

        return $this;
    }

    public function getTimeCreation(): ?\DateTimeInterface
    {
        return $this->timeCreation;
    }

    public function setTimeCreation(\DateTimeInterface $timeCreation): self
    {
        $this->timeCreation = $timeCreation;

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

    public function getText(): ?string
    {
        return $this->text;
    }

    public function setText(string $text): self
    {
        $this->text = $text;

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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getViewed(): ?bool
    {
        return $this->viewed;
    }

    public function setViewed(bool $viewed): self
    {
        $this->viewed = $viewed;

        return $this;
    }
}
