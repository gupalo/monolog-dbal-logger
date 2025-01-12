<?php

namespace Gupalo\MonologDbalLogger\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gupalo\MonologDbalLogger\Entity\Traits\VirtualFieldsEntityTrait;
use Gupalo\MonologDbalLogger\Repository\LogRepository;

#[ORM\Entity(repositoryClass: LogRepository::class)]
#[ORM\Table(name: '_log')]
#[ORM\Index(fields: ['createdAt', 'level'])]
class Log
{
    use VirtualFieldsEntityTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $levelName = '';

    #[ORM\Column(length: 1024, options: ['default' => ''])]
    private ?string $message = '';

    #[ORM\Column(nullable: true)]
    private ?array $context = [];

    #[ORM\Column(length: 255, options: ['default' => ''])]
    private ?string $channel = '';

    #[ORM\Column(length: 255, options: ['default' => ''])]
    private ?string $cmd = '';

    #[ORM\Column(length: 255, options: ['default' => ''])]
    private ?string $method = '';

    #[ORM\Column(length: 255, options: ['default' => ''])]
    private ?string $uid = '';

    #[ORM\Column(nullable: true)]
    private ?int $count = null;

    #[ORM\Column(nullable: true)]
    private ?float $time = null;

    #[ORM\Column(options: ['default' => 0])]
    private ?int $level = 0;

    #[ORM\Column(length: 1024)]
    private ?string $exceptionClass = '';

    #[ORM\Column(length: 1024)]
    private ?string $exceptionMessage = '';

    #[ORM\Column(length: 1024)]
    private ?string $exceptionLine = '';

    #[ORM\Column(type: Types::TEXT)]
    private ?string $exceptionTrace = '';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLevelName(): ?string
    {
        return $this->levelName;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getContext(): ?array
    {
        return $this->context;
    }

    public function getChannel(): ?string
    {
        return $this->channel;
    }

    public function getCmd(): ?string
    {
        return $this->cmd;
    }

    public function getMethod(): ?string
    {
        return $this->method;
    }

    public function getUid(): ?string
    {
        return $this->uid;
    }

    public function getCount(): ?int
    {
        return $this->count;
    }

    public function getTime(): ?float
    {
        return $this->time;
    }

    public function getLevel(): ?int
    {
        return $this->level;
    }

    public function getExceptionClass(): ?string
    {
        return $this->exceptionClass;
    }

    public function getExceptionMessage(): ?string
    {
        return $this->exceptionMessage;
    }

    public function getExceptionLine(): ?string
    {
        return $this->exceptionLine;
    }

    public function getExceptionTrace(): ?string
    {
        return $this->exceptionTrace;
    }
}
