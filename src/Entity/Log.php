<?php

namespace Gupalo\MonologDbalLogger\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gupalo\MonologDbalLogger\Entity\Traits\DbalLoggerVirtualFieldsEntityTrait;
use Gupalo\MonologDbalLogger\Repository\LogRepository;
use JsonException;

/**
 * Maps the `_log` table exactly as the README's CREATE TABLE defines it, which is the table
 * MonologDbalLogger / MyMonologDbalLogger write to. `doctrine:schema:update` against that table
 * must print nothing: see LogSchemaTest before changing any mapping here.
 */
#[ORM\Entity(repositoryClass: LogRepository::class)]
#[ORM\Table(name: '_log')]
#[ORM\Index(name: 'log_created_at_level', fields: ['createdAt', 'level'])]
class Log
{
    use DbalLoggerVirtualFieldsEntityTrait;

    public const array LEVEL_NAMES = ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT, options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::ENUM, nullable: true, options: ['values' => self::LEVEL_NAMES])]
    private ?string $levelName = null;

    #[ORM\Column(length: 1024, options: ['default' => ''])]
    private string $message = '';

    /** Raw JSON as the logger wrote it: possibly truncated, so not mapped as a JSON column. */
    #[ORM\Column(type: Types::TEXT, length: 65535, nullable: true)]
    private ?string $context = null;

    #[ORM\Column(length: 255, options: ['default' => ''])]
    private string $channel = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $cmd = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $method = null;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $uid = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $count = null;

    /** SQL FLOAT (single precision): Types::FLOAT would be DOUBLE PRECISION. */
    #[ORM\Column(type: Types::SMALLFLOAT, nullable: true)]
    private ?float $time = null;

    #[ORM\Column(type: Types::SMALLINT, options: ['default' => 0])]
    private int $level = 0;

    #[ORM\Column(length: 1024, nullable: true)]
    private ?string $exceptionClass = null;

    #[ORM\Column(length: 1024, nullable: true)]
    private ?string $exceptionMessage = null;

    #[ORM\Column(length: 1024, nullable: true)]
    private ?string $exceptionLine = null;

    #[ORM\Column(type: Types::TEXT, length: 65535, nullable: true)]
    private ?string $exceptionTrace = null;

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

    /**
     * The context decoded to an array. A context the logger had to truncate is no longer valid JSON;
     * it comes back as ['raw' => <stored text>] instead of failing the whole row.
     */
    public function getContext(): ?array
    {
        if ($this->context === null || $this->context === '') {
            return null;
        }

        try {
            $decoded = json_decode($this->context, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $decoded = null;
        }

        return is_array($decoded) ? $decoded : ['raw' => $this->context];
    }

    public function getContextRaw(): ?string
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
