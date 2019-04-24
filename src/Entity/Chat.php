<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation\Groups;
use JMS\Serializer\Annotation\MaxDepth;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ChatRepository")
 */
class Chat
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     * @Groups({"message"})
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User", inversedBy="chats")
     * @MaxDepth(1)
     * @Groups({"message"})
     */
    private $fromuser;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User", inversedBy="chats")
     * @MaxDepth(1)
     * @Groups({"message"})
     */
    private $touser;

    /**
     * @ORM\Column(type="text")
     * @Groups({"message"})
     */
    private $text;

    /**
     * @ORM\Column(type="datetime")
     * @Groups({"message"})
     */
    private $timeCreation;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @Groups({"message"})
     */
    private $timeRead;

    /**
     * @ORM\Column(type="string")
     */
    private $conversationId;

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

    public function getTimeCreation(): ?\DateTimeInterface
    {
        return $this->timeCreation;
    }

    public function setTimeCreation(\DateTimeInterface $timeCreation): self
    {
        $this->timeCreation = new \DateTime;

        return $this;
    }

    public function getTimeRead(): ?\DateTimeInterface
    {
        return $this->timeRead;
    }

    public function setTimeRead(\DateTimeInterface $timeRead): self
    {
        $this->timeRead = $timeRead;

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
}
