<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ChatRepository")
 */
class Chat
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     * @MaxDepth(1)
     * @Groups({"message"})
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User")
     * @MaxDepth(1)
     * @Groups({"message"})
     */
    private $fromuser;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User")
     * @MaxDepth(1)
     * @Groups({"message"})
     */
    private $touser;

    /**
     * @ORM\Column(type="text", nullable=true)
     * @Groups({"message"})
     */
    private $text;

    /**
     * @ORM\Column(type="text", nullable=true)
     * @Groups({"message"})
     */
    private $image;

    /**
     * @ORM\Column(type="text", nullable=true)
     * @Groups({"message"})
     */
    private $audio;

    /**
     * @ORM\Column(type="datetime")
     * @Groups({"message"})
     */
    private $time_creation;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @Groups({"message"})
     */
    private $time_read;

    /**
     * @ORM\Column(type="string")
     */
    private $conversationId;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Chat", cascade={"persist"})
     * @MaxDepth(1)
     * @ORM\JoinColumn(nullable=true, referencedColumnName="id", onDelete="CASCADE")
     * @Groups({"message"})
     */
    private $reply_to;

    /**
     * @ORM\Column(type="boolean", options={"default" : 0})
     * @Groups({"message"})
     */
    private $edited;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getTouser(): ?User
    {
        return $this->touser;
    }

    public function setTouser(?User $touser): self
    {
        $this->touser = $touser;

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

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(string $image): self
    {
        $this->image = $image;

        return $this;
    }

    public function getAudio(): ?string
    {
        return $this->audio;
    }

    public function setAudio(string $audio): self
    {
        $this->audio = $audio;

        return $this;
    }

    public function getTimeCreation(): ?\DateTimeInterface
    {
        return $this->time_creation;
    }

    public function setTimeCreation(): self
    {
        $this->time_creation = new \DateTime;

        return $this;
    }

    public function getTimeRead(): ?\DateTimeInterface
    {
        return $this->time_read;
    }

    public function setTimeRead(\DateTimeInterface $time_read): self
    {
        $this->time_read = $time_read;

        return $this;
    }

    public function getConversationId(): ?string
    {
        return $this->conversationId;
    }

    public function setConversationId(string $conversationId): self
    {
        $this->conversationId = $conversationId;

        return $this;
    }

    public function getReplyTo(): ?Chat
    {
        return $this->reply_to;
    }

    public function setReplyTo(?Chat $reply_to): self
    {
        $this->reply_to = $reply_to;

        return $this;
    }

    public function getEdited(): ?bool
    {
        return $this->edited;
    }

    public function setEdited(bool $edited): self
    {
        $this->edited = $edited;

        return $this;
    }
}
