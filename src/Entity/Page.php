<?php

namespace App\Entity;

use App\Repository\PageRepository;
use Symfony\Component\Serializer\Annotation\Groups;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=PageRepository::class)
 */
class Page
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     * @Groups({"default"})
     */
    private $name;

    /**
     * @ORM\Column(type="text", nullable=true)
     * @Groups({"default"})
     */
    private $description;

    /**
     * @ORM\Column(type="datetime")
     */
    private $time_creation;

    /**
     * @ORM\Column(type="datetime")
     */
    private $last_update;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Groups({"default"})
     */
    private $cover;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Groups({"default"})
     */
    private $artwork;

    /**
     * @ORM\Column(type="float", nullable=true)
     * @Groups({"default"})
     */
    private $rating;

    /**
     * @ORM\Column(type="string", length=70, nullable=true)
     * @Groups({"default"})
     */
    private $game_mode;

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     * @Groups({"default"})
     */
    private $slug;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @Groups({"default"})
     */
    private $release_date;

    /**
     * @ORM\Column(type="string", length=70)
     * @Groups({"default"})
     */
    private $category;

    /**
     * @Groups({"default"})
     */
    private $room;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Groups({"default"})
     */
    private $developer;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

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

    public function getLastUpdate(): ?\DateTimeInterface
    {
        return $this->last_update;
    }

    public function setLastUpdate(): self
    {
        $this->last_update = new \DateTime;

        return $this;
    }

    public function getCover(): ?string
    {
        $cover = $this->cover;
        if (!$cover) {
            $cover = "https://api.frikiradar.com/images/pages/" . $this->getCategory() . ".jpg";
        }
        $this->cover = $cover;

        return $this->cover;
    }

    public function setCover(?string $cover): self
    {
        $this->cover = $cover;

        return $this;
    }

    public function getArtwork(): ?string
    {
        $artwork = $this->artwork;
        if (!$artwork) {
            $artwork = "https://api.frikiradar.com/images/pages/" . $this->getCategory() . ".jpg";
        }
        $this->artwork = $artwork;

        return $this->artwork;
    }

    public function setArtwork(?string $artwork): self
    {
        $this->artwork = $artwork;

        return $this;
    }

    public function getRating(): ?float
    {
        return $this->rating;
    }

    public function setRating(?float $rating): self
    {
        $this->rating = $rating;

        return $this;
    }

    public function getGameMode(): ?string
    {
        return $this->game_mode;
    }

    public function setGameMode(?string $game_mode): self
    {
        $this->game_mode = $game_mode;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function getReleaseDate(): ?\DateTimeInterface
    {
        return $this->release_date;
    }

    public function setReleaseDate(?\DateTimeInterface $release_date): self
    {
        $this->release_date = $release_date;

        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(string $category): self
    {
        $this->category = $category;

        return $this;
    }

    public function getRoom(): ?Room
    {
        return $this->room;
    }

    public function setRoom(?Room $room): self
    {
        $this->room = $room;

        return $this;
    }

    public function getDeveloper(): ?string
    {
        return $this->developer;
    }

    public function setDeveloper(?string $developer): self
    {
        $this->developer = $developer;

        return $this;
    }
}
