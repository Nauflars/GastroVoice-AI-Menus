<?php

declare(strict_types=1);

namespace App\VoiceAssistant\Domain\ValueObject;

final class ConversationTurn
{
    private function __construct(
        public readonly string $role,
        public readonly string $content,
        public readonly \DateTimeImmutable $timestamp,
    ) {}

    public static function user(string $content): self
    {
        return new self('user', $content, new \DateTimeImmutable());
    }

    public static function assistant(string $content): self
    {
        return new self('assistant', $content, new \DateTimeImmutable());
    }

    public function toArray(): array
    {
        return [
            'role'      => $this->role,
            'content'   => $this->content,
            'timestamp' => $this->timestamp->format(\DateTimeInterface::ATOM),
        ];
    }
}
