<?php
declare(strict_types=1);

namespace App\Entity;

use App\Entity\Traits\CreatedAtTrait;
use App\Entity\Traits\UpdatedAtTrait;
use Doctrine\ORM\Mapping;

/**
 * @author Mikhail Kamorin aka raptor_MVK
 *
 * @copyright 2020, raptor_MVK
 *
 * @Mapping\Table(name="tweet")
 * @Mapping\Entity
 * @Mapping\Entity(repositoryClass="App\Repository\TweetRepository")
 * @Mapping\HasLifecycleCallbacks
 */
class Tweet
{
    use CreatedAtTrait;
    use UpdatedAtTrait;

    /**
     * @Mapping\Column(name="id", type="bigint", unique=true)
     * @Mapping\Id
     * @Mapping\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var User
     *
     * @Mapping\ManyToOne(targetEntity="User")
     * @Mapping\JoinColumns({
     *   @Mapping\JoinColumn(name="author_id", referencedColumnName="id")
     * })
     */
    private $author;

    /**
     * @var string
     *
     * @Mapping\Column(type="string", length=140, nullable=false)
     */
    private $text;

    public function getId(): int
    {
        return (int)$this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getAuthor(): User
    {
        return $this->author;
    }

    public function setAuthor(User $author): void
    {
        $this->author = $author;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function setText(string $text): void
    {
        $this->text = $text;
    }

    public function toFeed(): array
    {
        return [
            'id' => $this->id,
            'author' => $this->author !== null ? $this->author->getLogin() : null,
            'text' => $this->text,
            'createdAt' => $this->createdAt !== null ? $this->createdAt->format('Y-m-d h:i:s') : '',
        ];
    }

    public function toAMPQMessage(): string
    {
        return json_encode(['tweetId' => (int)$this->id], JSON_THROW_ON_ERROR, 512);
    }
}