<?php

namespace App\Entity;

use App\Repository\ChatRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\MaxDepth;

#[ORM\Entity(repositoryClass: ChatRepository::class)]
class Chat
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[MaxDepth(1)]
    #[Groups('message')]
    private $id;

    #[ORM\ManyToOne(targetEntity: User::class, cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    #[MaxDepth(1)]
    #[Groups('message')]
    private $fromuser;

    #[ORM\ManyToOne(targetEntity: User::class, cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    #[MaxDepth(1)]
    #[Groups('message')]
    private $touser;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups('message')]
    private $text;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups('message')]
    private $image;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups('message')]
    private $audio;

    #[ORM\Column(type: 'datetime')]
    #[Groups('message')]
    private $time_creation;

    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups('message')]
    private $time_read;

    #[ORM\Column(type: 'string')]
    #[Groups('message')]
    private $conversationId;

    #[ORM\ManyToOne(targetEntity: Chat::class, cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: true, referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[MaxDepth(1)]
    #[Groups('message')]
    private $reply_to;

    #[ORM\Column(type: 'boolean', nullable: true, options: ['default' => 0])]
    #[Groups('message')]
    private $edited;

    #[Groups('message')]
    private $deleted = false;

    #[Groups('message')]
    private $writing = false;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups('message')]
    private $mentions = [];

    #[ORM\Column(type: "string", length: 70, nullable: true)]
    #[Groups('message')]
    private $tmp_id;

    #[ORM\OneToOne(targetEntity: Event::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    #[Groups('message')]
    private $event;

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

    public function setImage(?string $image): self
    {
        $this->image = $image;

        return $this;
    }

    public function getAudio(): ?string
    {
        return $this->audio;
    }

    public function setAudio(?string $audio): self
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

    public function isEdited(): ?bool
    {
        return $this->edited;
    }

    public function setEdited(?bool $edited): self
    {
        $this->edited = $edited ?: 0;

        return $this;
    }

    public function getDeleted(): ?bool
    {
        return $this->deleted;
    }

    public function setDeleted(bool $deleted): self
    {
        $this->deleted = $deleted;

        return $this;
    }

    public function getWriting(): ?bool
    {
        return $this->writing;
    }

    public function setWriting(bool $writing): self
    {
        $this->writing = $writing;

        return $this;
    }

    public function getMentions(): ?array
    {
        return $this->mentions;
    }

    public function setMentions(?array $mentions): self
    {
        $this->mentions = $mentions;

        return $this;
    }

    public function getTmpId(): ?string
    {
        return $this->tmp_id;
    }

    public function setTmpId(string $tmp_id): self
    {
        $this->tmp_id = $tmp_id;

        return $this;
    }

    public function getEvent(): ?Event
    {
        return $this->event;
    }

    public function setEvent(?Event $event): self
    {
        $this->event = $event;

        return $this;
    }
}
