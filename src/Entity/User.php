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


/**
 * @ORM\Entity(repositoryClass="App\Repository\UserRepository")
 */
class User implements UserInterface
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     * @Groups({"default", "message", "like", "story", "notification"})
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
     * @Groups({"default", "message", "story", "notification"})
     */
    private $username;

    /**
     * @var array
     * @ORM\Column(type="json")
     * @Groups({"default", "message", "story"})
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
     * @Groups({"default", "message", "story"})
     */
    private $avatar;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Groups({"default", "message", "story", "notification"})
     */
    private $thumbnail;

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
     * @ORM\Column(type="string", length=70, nullable=true)
     * @Groups({"default"})
     */
    private $country;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Groups({"default"})
     */
    private $city;

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
     * @Groups({"default", "message"})
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
     * @Groups({"default", "message", "story", "notification"})
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

    /**
     * @ORM\Column(type="boolean", options={"default" : 0})
     * @Groups({"default", "message"})
     */
    private $banned;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Groups({"default"})
     */
    private $ban_reason;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @Groups({"default"})
     */
    private $ban_end;

    /**
     * @ORM\OneToMany(targetEntity=Room::class, mappedBy="creator")
     */
    private $rooms;

    /**
     * @ORM\OneToMany(targetEntity=Story::class, mappedBy="user", orphanRemoval=true)
     * @ORM\OrderBy({"time_creation" = "ASC"})
     */
    private $stories;

    /**
     * @ORM\OneToMany(targetEntity=LikeStory::class, mappedBy="user", orphanRemoval=true)
     */
    private $likeStories;

    /**
     * @ORM\OneToMany(targetEntity=ViewStory::class, mappedBy="user", orphanRemoval=true)
     */
    private $viewStories;

    /**
     * @ORM\Column(type="boolean", options={"default" : 1})
     * @Groups({"default"})
     */
    private $public;

    /**
     * @ORM\Column(type="boolean", options={"default" : 1})
     * @Groups({"default"})
     */
    private $hide_likes;

    /**
     * @ORM\Column(type="json", nullable=true)
     * @Groups({"default"})
     */
    private $config = [];

    /**
     * @ORM\OneToMany(targetEntity=Notification::class, mappedBy="user", orphanRemoval=true)
     */
    private $notifications;

    /**
     * @ORM\OneToMany(targetEntity=Event::class, mappedBy="creator", orphanRemoval=true)
     */
    private $created_events;

    /**
     * @ORM\Column(type="json", length=255, nullable=true)
     * @Groups({"default"})
     */
    private $patreon;

    /**
     * @ORM\ManyToMany(targetEntity=Event::class, mappedBy="participants")
     */
    private $events;

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
        $this->rooms = new ArrayCollection();
        $this->stories = new ArrayCollection();
        $this->likeStories = new ArrayCollection();
        $this->viewStories = new ArrayCollection();
        $this->notifications = new ArrayCollection();
        $this->events = new ArrayCollection();
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
            $roles[] = 'ROLE_PATREON';
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

    public function addRol(string $rol): self
    {
        $roles = $this->roles;
        if (!in_array($rol, $roles)) {
            $roles[] = $rol;
        }

        $this->roles = array_unique($roles);

        return $this;
    }

    public function removeRol(string $rol): self
    {
        $roles = $this->roles;
        if (in_array($rol, $roles)) {
            $roles = array_diff($roles, [$rol]);
        }

        $this->roles = array_unique($roles);

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
        if ($this->getHideConnection()) {
            return null;
        }

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
        if ($this->avatar) {
            return $this->avatar;
        } else {
            $letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'Ñ', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
            $letter = null;
            if (isset($this->getUsername()[0])) {
                $letter = strtoupper($this->getUsername()[0]);
            }
            if (in_array($letter, $letters)) {
                return "https://api.frikiradar.com/images/avatar/" . $letter . ".png";
            } else {
                return "https://api.frikiradar.com/images/avatar/default.png";
            }
        }
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

                if ($this->avatar !== $image && !strpos($file, '-128px')) {
                    $this->images[] = $image;
                }
            }
        }
        $this->images = array_unique($this->images);

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

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): self
    {
        $this->city = $city;

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

    public function getBanned(): ?bool
    {
        return $this->banned;
    }

    public function setBanned(bool $banned): self
    {
        $this->banned = $banned;

        return $this;
    }

    public function getBanReason(): ?string
    {
        return $this->ban_reason;
    }

    public function setBanReason(?string $ban_reason): self
    {
        $this->ban_reason = $ban_reason;

        return $this;
    }

    public function getBanEnd(): ?\DateTimeInterface
    {
        return $this->ban_end;
    }

    public function setBanEnd(?\DateTimeInterface $ban_end): self
    {
        $this->ban_end = $ban_end;

        return $this;
    }

    public function getThumbnail(): ?string
    {
        if ($this->thumbnail) {
            return $this->thumbnail;
        } else {
            $letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'Ñ', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
            $letter = null;
            if (isset($this->getUsername()[0])) {
                $letter = strtoupper($this->getUsername()[0]);
            }
            if (in_array($letter, $letters)) {
                return "https://api.frikiradar.com/images/avatar/thumbnail/" . $letter . ".png";
            } else {
                return "https://api.frikiradar.com/images/avatar/thumbnail/default.png";
            }
        }
    }

    public function setThumbnail(?string $thumbnail): self
    {
        $this->thumbnail = $thumbnail;

        return $this;
    }

    /**
     * @return Collection|Room[]
     */
    public function getRooms(): Collection
    {
        return $this->rooms;
    }

    public function addRoom(Room $room): self
    {
        if (!$this->rooms->contains($room)) {
            $this->rooms[] = $room;
            $room->setCreator($this);
        }

        return $this;
    }

    public function removeRoom(Room $room): self
    {
        if ($this->rooms->removeElement($room)) {
            // set the owning side to null (unless already changed)
            if ($room->getCreator() === $this) {
                $room->setCreator(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Story[]
     */
    public function getStories(): Collection
    {
        return $this->stories;
    }

    public function addStory(Story $story): self
    {
        if (!$this->stories->contains($story)) {
            $this->stories[] = $story;
            $story->setUser($this);
        }

        return $this;
    }

    public function removeStory(Story $story): self
    {
        if ($this->stories->removeElement($story)) {
            // set the owning side to null (unless already changed)
            if ($story->getUser() === $this) {
                $story->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|LikeStory[]
     */
    public function getLikeStories(): Collection
    {
        return $this->likeStories;
    }

    public function addLikeStory(LikeStory $likeStory): self
    {
        if (!$this->likeStories->contains($likeStory)) {
            $this->likeStories[] = $likeStory;
            $likeStory->setUser($this);
        }

        return $this;
    }

    public function removeLikeStory(LikeStory $likeStory): self
    {
        if ($this->likeStories->removeElement($likeStory)) {
            // set the owning side to null (unless already changed)
            if ($likeStory->getUser() === $this) {
                $likeStory->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|ViewStory[]
     */
    public function getViewStories(): Collection
    {
        return $this->viewStories;
    }

    public function addViewStory(ViewStory $viewStory): self
    {
        if (!$this->viewStories->contains($viewStory)) {
            $this->viewStories[] = $viewStory;
            $viewStory->setUser($this);
        }

        return $this;
    }

    public function removeViewStory(ViewStory $viewStory): self
    {
        if ($this->viewStories->removeElement($viewStory)) {
            // set the owning side to null (unless already changed)
            if ($viewStory->getUser() === $this) {
                $viewStory->setUser(null);
            }
        }

        return $this;
    }

    public function getPublic(): ?bool
    {
        return $this->public;
    }

    public function setPublic(bool $public): self
    {
        $this->public = $public;

        return $this;
    }

    public function getHideLikes(): ?bool
    {
        return $this->hide_likes;
    }

    public function setHideLikes(?bool $hide_likes): self
    {
        $this->hide_likes = $hide_likes;

        return $this;
    }

    public function getConfig(): ?array
    {
        return $this->config;
    }

    public function setConfig(?array $config): self
    {
        $this->config = $config;

        return $this;
    }

    /**
     * @return Collection|Notification[]
     */
    public function getNotifications(): ?Collection
    {
        return $this->notifications;
    }

    public function addNotification(Notification $notification): self
    {
        if (!$this->notifications->contains($notification)) {
            $this->notifications[] = $notification;
            $notification->setUser($this);
        }

        return $this;
    }

    public function removeNotification(Notification $notification): self
    {
        if ($this->notifications->removeElement($notification)) {
            // set the owning side to null (unless already changed)
            if ($notification->getUser() === $this) {
                $notification->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|Event[]
     */
    public function getCreatedEvents(): ?Collection
    {
        return $this->created_events;
    }

    public function createEvent(Event $event): self
    {
        if (!$this->created_events->contains($event)) {
            $this->created_events[] = $event;
            $event->setCreator($this);
        }

        return $this;
    }

    public function removeEvent(Event $event): self
    {
        if ($this->events->removeElement($event)) {
            // set the owning side to null (unless already changed)
            if ($event->getCreator() === $this) {
                $event->setCreator(null);
            }
        }

        return $this;
    }

    public function getPatreon(): ?array
    {
        return $this->patreon;
    }

    public function setPatreon(?array $patreon): self
    {
        $this->patreon = $patreon;

        return $this;
    }

    /**
     * @return Collection|Event[]
     */
    public function getEvents(): Collection
    {
        return $this->events;
    }

    public function addEvent(Event $event): self
    {
        if (!$this->events->contains($event)) {
            $this->events[] = $event;
            $event->addParticipant($this);
        }

        return $this;
    }
}
