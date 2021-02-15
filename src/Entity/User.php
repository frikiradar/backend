<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\Ignore;
use CrEOF\Spatial\PHP\Types\Geometry\Point;
use ApiPlatform\Core\Annotation\ApiResource;


/**
 * @ApiResource()
 * @ORM\Entity(repositoryClass="App\Repository\UserRepository")
 */
class User implements UserInterface
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     * @Groups({"default", "message", "like"})
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=180, unique=true)
     * @Assert\Regex(
     *     pattern="/^[a-zA-Z0-9._-]+$/",
     *     match=true,
     *     message="Solo se permiten caracteres alfanuméricos, puntos y/o guiones; ni letras con tildes, ni caracteres especiales",
     *     payload = {"severity" = "error"}
     * )
     * @Groups({"default", "message"})
     */
    private $username;

    /**
     * @var array
     * @ORM\Column(type="json")
     * @Groups({"default", "message"})
     */
    private $roles = [];

    /**
     * @var string The hashed password
     * @ORM\Column(type="string")
     * @Groups({"private"})
     * @Ignore()
     */
    private $password;

    /**
     * @ORM\Column(type="string", length=255, unique=true)
     * @Groups({"default"})
     */
    private $email;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @Ignore()
     * @Groups({"default"})
     */
    private $register_date;

    /**
     * @ORM\Column(type="text", nullable=true)
     * @Groups({"default"})
     */
    private $description;

    /**
     * @ORM\Column(type="string", length=70, nullable=true)
     * @Groups({"default"})
     */
    private $gender;

    /**
     * @ORM\Column(type="string", length=70, nullable=true)
     * @Groups({"default"})
     */
    private $orientation;

    /**
     * @ORM\Column(type="string", length=70, nullable=true)
     * @Groups({"default"})
     */
    private $relationship;

    /**
     * @ORM\Column(type="string", length=70, nullable=true)
     * @Groups({"default"})
     */
    private $pronoun;

    /**
     * @ORM\Column(type="string", length=70, nullable=true)
     * @Groups({"default"})
     */
    private $status;

    /**
     * @ORM\Column(type="string", length=70, nullable=true)
     * @Ignore()
     */
    private $register_ip;

    /**
     * @ORM\Column(type="string", length=70, nullable=true)
     * @Ignore()
     */
    private $last_ip;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @Groups({"default", "message"})
     */
    private $last_login;

    /**
     * @ORM\Column(type="json_array", nullable=true)
     * @Groups({"default"})
     */
    private ?array $lovegender = [];

    /**
     * @ORM\Column(type="integer", nullable=true)
     * @Groups({"default"})
     */
    private $minage;

    /**
     * @ORM\Column(type="integer", nullable=true)
     * @Groups({"default"})
     */
    private $maxage;

    /**
     * @ORM\Column(type="json_array", nullable=true)
     * @Groups({"default"})
     */
    private $connection;

    /**
     * @ORM\Column(type="point", nullable=true)
     * @Ignore()
     */
    private $coordinates;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Tag", mappedBy="user", orphanRemoval=true, cascade={"persist","merge"})
     * @Groups({"tags"})
     */
    private $tags;

    /**
     * @ORM\Column(type="date", nullable=true)
     * @Groups({"default"})
     */
    private $birthday;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Groups({"default", "message"})
     */
    private $avatar;

    /**
     * @Groups({"default"})
     */
    private array $images = [];

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Groups({"default"})
     */
    private $location;

    /**
     * @ORM\Column(type="string", length=2, nullable=true)
     * @Groups({"default"})
     */
    private $country;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Device", mappedBy="user", orphanRemoval=true)
     * @Groups({"default"})
     */
    private $devices;

    /**
     * @ORM\Column(type="string", length=6, nullable=true)
     */
    private $verificationCode;

    /**
     * @ORM\Column(type="boolean")
     * @Groups({"default", "message"})
     */
    private bool $active = false;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     * @Groups({"default"})
     */
    private ?bool $hide_location = false;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     * @Groups({"default"})
     */
    private ?bool $hide_connection = false;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     * @Groups({"default"})
     */
    private ?bool $block_messages = false;

    /**
     * @ORM\Column(type="boolean")
     * @Groups({"default"})
     */
    private ?bool $two_step = false;

    /**
     * @ORM\Column(type="integer", nullable=true)
     * @Groups({"default"})
     */
    private ?int $num_logins = 0;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Radar", mappedBy="toUser", orphanRemoval=true)
     */
    private $radars;

    /**
     * @ORM\Column(type="boolean")
     * @Groups({"default"})
     */
    private bool $mailing;

    /**
     * @ORM\Column(type="string", length=20, nullable=true)
     * @Groups({"default"})
     */
    private ?string $meet;

    /**
     * @ORM\Column(type="string", length=70, nullable=true)
     * @Groups({"default"})
     */
    private ?string $referral;

    /**
     * @ORM\Column(type="boolean", options={"default" : 0})
     * @Groups({"default", "message"})
     */
    private bool $verified;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Groups({"default", "message"})
     */
    private ?string $name;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\BlockUser", mappedBy="from_user", orphanRemoval=true)
     */
    private $blockUsers;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\HideUser", mappedBy="from_user", orphanRemoval=true)
     */
    private $hideUsers;

    /**
     * @ORM\OneToMany(targetEntity=ViewUser::class, mappedBy="from_user", orphanRemoval=true)
     */
    private $viewUsers;

    public function __construct()
    {
        $this->tags = new ArrayCollection();
        $this->chats = new ArrayCollection();
        $this->devices = new ArrayCollection();
        $this->blockUsers = new ArrayCollection();
        $this->radars = new ArrayCollection();
        $this->blockUsers = new ArrayCollection();
        $this->hideUsers = new ArrayCollection();
        $this->viewUsers = new ArrayCollection();
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUsername(): string
    {
        return (string) $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        if (in_array('ROLE_ADMIN', $roles)) {
            $roles[] = 'ROLE_MASTER';
        }

        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function getPassword(): string
    {
        return (string) $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function getSalt()
    {
        // not needed when using the "bcrypt" algorithm in security.yaml
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials()
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getRegisterDate(): ?\DateTimeInterface
    {
        return $this->register_date;
    }

    public function setRegisterDate(): self
    {
        $this->register_date = new \DateTime;

        return $this;
    }

    public function getGender(): ?string
    {
        return $this->gender;
    }

    public function setGender(?string $gender): self
    {
        $this->gender = $gender;

        return $this;
    }

    public function getRegisterIp(): ?string
    {
        return $this->register_ip;
    }

    public function setRegisterIp(): self
    {
        $this->register_ip = $this->getIP();

        return $this;
    }

    public function getLastIp(): ?string
    {
        return $this->last_ip;
    }

    public function setLastIp(): self
    {
        $this->last_ip = $this->getIP();

        return $this;
    }

    public function getLastLogin(): ?\DateTimeInterface
    {
        return $this->last_login;
    }

    public function setLastLogin($last_login = false): self
    {
        if ($last_login === false) {
            $this->last_login = new \DateTime();
        } else {
            $this->last_login = $last_login;
        }

        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): self
    {
        $this->location = $location;

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

    public function getRelationship(): ?string
    {
        return $this->relationship;
    }

    public function setRelationship(?string $relationship): self
    {
        $this->relationship = $relationship;

        return $this;
    }

    public function getOrientation(): ?string
    {
        return $this->orientation;
    }

    public function setOrientation(?string $orientation): self
    {
        $this->orientation = $orientation;

        return $this;
    }

    public function getPronoun(): ?string
    {
        return $this->pronoun;
    }

    public function setPronoun(?string $pronoun): self
    {
        $this->pronoun = $pronoun;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getLovegender(): ?array
    {
        return $this->lovegender;
    }

    public function setLovegender(?array $lovegender): self
    {
        $this->lovegender = $lovegender;

        return $this;
    }

    public function getMinage(): ?int
    {
        return $this->minage;
    }

    public function setMinage(?int $minage): self
    {
        $this->minage = $minage;

        return $this;
    }

    public function getMaxage(): ?int
    {
        return $this->maxage;
    }

    public function setMaxage(?int $maxage): self
    {
        $this->maxage = $maxage;

        return $this;
    }

    public function getConnection()
    {
        return $this->connection;
    }

    public function setConnection($connection): self
    {
        $this->connection = $connection;

        return $this;
    }

    /**
     * @return Point
     */
    public function getCoordinates()
    {
        return $this->coordinates;
    }

    /**
     * @param Point $coordinates
     */
    public function setCoordinates(Point $coordinates): self
    {
        $this->coordinates = $coordinates;

        return $this;
    }

    public function setTags($tags)
    {
        if (count($tags) > 0) {
            foreach ($tags as $tag) {
                $this->addTag($tag);
            }
        }

        return $this;
    }


    /**
     * @return Collection|Tag[]
     */
    public function getTags()
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): self
    {
        if (!$this->tags->contains($tag)) {
            $this->tags[] = $tag;
            $tag->setUser($this);
        }

        return $this;
    }

    public function removeTag(Tag $tag): self
    {
        if ($this->tags->contains($tag)) {
            $this->tags->removeElement($tag);
            // set the owning side to null (unless already changed)
            if ($tag->getUser() === $this) {
                $tag->setUser(null);
            }
        }

        return $this;
    }

    public function getBirthday(): ?\DateTimeInterface
    {
        return $this->birthday;
    }

    public function setBirthday(?\DateTimeInterface $birthday): self
    {
        $this->birthday = $birthday;

        return $this;
    }

    public function getAvatar()
    {
        return $this->avatar ?: "https://app.frikiradar.com/images/layout/default.jpg";
    }

    public function setAvatar(?string $avatar): self
    {
        $this->avatar = $avatar;

        return $this;
    }

    public function getImages()
    {
        $files = glob("/var/www/vhosts/frikiradar.com/app.frikiradar.com/images/avatar/" . $this->getId() . "/*.jpg");
        usort($files, function ($a, $b) {
            return basename($b) <=> basename($a);
        });

        foreach ($files as $file) {
            if (isset($file)) {
                // $server = "https://$_SERVER[HTTP_HOST]";
                $server = "https://app.frikiradar.com";
                $image = str_replace("/var/www/vhosts/frikiradar.com/app.frikiradar.com", $server, $file);

                if ($this->avatar !== $image) {
                    $this->images[] = $image;
                }
            }
        }

        return $this->images;
    }

    public function setImages($images): self
    {
        $this->images = $images;

        return $this;
    }

    public function getIP()
    {
        if (isset($_SERVER["HTTP_CLIENT_IP"])) {
            return $_SERVER["HTTP_CLIENT_IP"];
        } elseif (isset($_SERVER["HTTP_X_FORWARDED_FOR"])) {
            return $_SERVER["HTTP_X_FORWARDED_FOR"];
        } elseif (isset($_SERVER["HTTP_X_FORWARDED"])) {
            return $_SERVER["HTTP_X_FORWARDED"];
        } elseif (isset($_SERVER["HTTP_FORWARDED_FOR"])) {
            return $_SERVER["HTTP_FORWARDED_FOR"];
        } elseif (isset($_SERVER["HTTP_FORWARDED"])) {
            return $_SERVER["HTTP_FORWARDED"];
        } else {
            return $_SERVER["REMOTE_ADDR"];
        }
    }

    public function addChat(Chat $chat): self
    {
        if (!$this->chats->contains($chat)) {
            $this->chats[] = $chat;
            $chat->setFromuser($this);
        }

        return $this;
    }

    public function removeChat(Chat $chat): self
    {
        if ($this->chats->contains($chat)) {
            $this->chats->removeElement($chat);
            // set the owning side to null (unless already changed)
            if ($chat->getFromuser() === $this) {
                $chat->setFromuser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Device[]
     */
    public function getDevices()
    {
        return $this->devices;
    }

    public function addDevice(Device $device): self
    {
        if (!$this->devices->contains($device)) {
            $this->devices[] = $device;
            $device->setUser($this);
        }

        return $this;
    }

    public function removeDevice(Device $device): self
    {
        if ($this->devices->contains($device)) {
            $this->devices->removeElement($device);
            // set the owning side to null (unless already changed)
            if ($device->getUser() === $this) {
                $device->setUser(null);
            }
        }

        return $this;
    }

    public function removeDevices(): self
    {
        foreach ($this->devices as $device) {
            $this->removeDevice($device);
        }

        return $this;
    }

    public function getVerificationCode()
    {
        return $this->verificationCode;
    }

    public function setVerificationCode($code = true)
    {
        if ($code === true) {
            $key = '';
            $pattern = '123456789ABCDEFGHJKMNPRSTUVWXYZ';
            $max = strlen($pattern) - 1;
            for ($i = 0; $i < 6; $i++) $key .= $pattern[mt_rand(0, $max)];

            $this->verificationCode = $key;
        } else {
            $this->verificationCode = $code;
        }

        return $this;
    }

    public function getActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;

        return $this;
    }

    public function getHideLocation(): ?bool
    {
        return $this->hide_location;
    }

    public function setHideLocation(?bool $hide_location): self
    {
        $this->hide_location = $hide_location;

        return $this;
    }

    public function getBlockMessages(): ?bool
    {
        return $this->block_messages;
    }

    public function setBlockMessages(?bool $block_messages): self
    {
        $this->block_messages = $block_messages;

        return $this;
    }

    public function getTwoStep(): ?bool
    {
        return $this->two_step;
    }

    public function setTwoStep(bool $two_step): self
    {
        $this->two_step = $two_step;

        return $this;
    }

    public function getNumLogins(): ?int
    {
        return $this->num_logins;
    }

    public function setNumLogins(int $num_logins): self
    {
        $this->num_logins = $num_logins;

        return $this;
    }

    /**
     * @return Collection|Radar[]
     */
    public function getRadars(): Collection
    {
        return $this->radars;
    }

    public function addRadar(Radar $radar): self
    {
        if (!$this->radars->contains($radar)) {
            $this->radars[] = $radar;
            $radar->setFromUser($this);
        }

        return $this;
    }

    public function removeRadar(Radar $radar): self
    {
        if ($this->radars->contains($radar)) {
            $this->radars->removeElement($radar);
            // set the owning side to null (unless already changed)
            if ($radar->getFromUser() === $this) {
                $radar->setFromUser(null);
            }
        }

        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): self
    {
        $this->country = $country;

        return $this;
    }

    public function getHideConnection(): ?bool
    {
        return $this->hide_connection;
    }

    public function setHideConnection(?bool $hide_connection): self
    {
        $this->hide_connection = $hide_connection;

        return $this;
    }

    public function getMailing(): ?bool
    {
        return $this->mailing;
    }

    public function setMailing(bool $mailing): self
    {
        $this->mailing = $mailing;

        return $this;
    }

    public function getMeet(): ?string
    {
        return $this->meet;
    }

    public function setMeet(?string $meet): self
    {
        $this->meet = $meet;

        return $this;
    }

    public function getReferral(): ?string
    {
        return $this->referral;
    }

    public function setReferral(?string $referral): self
    {
        $this->referral = $referral;

        return $this;
    }

    public function getVerified(): ?bool
    {
        return $this->verified;
    }

    public function setVerified(bool $verified): self
    {
        $this->verified = $verified;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return Collection|BlockUser[]
     */
    public function getBlockUsers(): Collection
    {
        return $this->blockUsers;
    }

    public function addBlockUser(BlockUser $blockUser): self
    {
        if (!$this->blockUsers->contains($blockUser)) {
            $this->blockUsers[] = $blockUser;
            $blockUser->setFromUser($this);
        }

        return $this;
    }

    /**
     * @return Collection|HideUser[]
     */
    public function getHideUsers(): Collection
    {
        return $this->hideUsers;
    }

    public function addHideUser(HideUser $hideUser): self
    {
        if (!$this->hideUsers->contains($hideUser)) {
            $this->hideUsers[] = $hideUser;
            $hideUser->setFromUser($this);
        }

        return $this;
    }

    public function removeHideUser(HideUser $hideUser): self
    {
        if ($this->hideUsers->contains($hideUser)) {
            $this->hideUsers->removeElement($hideUser);
            // set the owning side to null (unless already changed)
            if ($hideUser->getFromUser() === $this) {
                $hideUser->setFromUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|ViewUser[]
     */
    public function getViewUsers(): Collection
    {
        return $this->viewUsers;
    }

    public function addViewUser(ViewUser $viewUser): self
    {
        if (!$this->viewUsers->contains($viewUser)) {
            $this->viewUsers[] = $viewUser;
            $viewUser->setFromUser($this);
        }

        return $this;
    }

    public function removeViewUser(ViewUser $viewUser): self
    {
        if ($this->viewUsers->contains($viewUser)) {
            $this->viewUsers->removeElement($viewUser);
            // set the owning side to null (unless already changed)
            if ($viewUser->getFromUser() === $this) {
                $viewUser->setFromUser(null);
            }
        }

        return $this;
    }
}
