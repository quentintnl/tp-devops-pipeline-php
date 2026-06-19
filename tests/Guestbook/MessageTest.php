<?php

declare(strict_types=1);

namespace Tests\Guestbook;

use App\Guestbook\InvalidMessageException;
use App\Guestbook\Message;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MessageTest extends TestCase
{
    #[Test]
    public function it_keeps_trimmed_values(): void
    {
        $message = Message::create('  Alice  ', '  Bonjour  ');

        self::assertSame('Alice', $message->author);
        self::assertSame('Bonjour', $message->body);
    }

    #[Test]
    public function it_rejects_empty_author(): void
    {
        $this->expectException(InvalidMessageException::class);
        Message::create('   ', 'Un message');
    }

    #[Test]
    public function it_rejects_empty_body(): void
    {
        $this->expectException(InvalidMessageException::class);
        Message::create('Alice', '   ');
    }

    #[Test]
    public function it_rejects_too_long_author(): void
    {
        $this->expectException(InvalidMessageException::class);
        Message::create(str_repeat('a', Message::MAX_AUTHOR_LENGTH + 1), 'Un message');
    }

    #[Test]
    public function it_rejects_too_long_body(): void
    {
        $this->expectException(InvalidMessageException::class);
        Message::create('Alice', str_repeat('b', Message::MAX_BODY_LENGTH + 1));
    }

    #[Test]
    public function it_accepts_values_at_the_max_length(): void
    {
        $author = str_repeat('a', Message::MAX_AUTHOR_LENGTH);
        $body = str_repeat('b', Message::MAX_BODY_LENGTH);

        $message = Message::create($author, $body);

        self::assertSame($author, $message->author);
        self::assertSame($body, $message->body);
    }

    #[Test]
    public function it_round_trips_through_array(): void
    {
        $createdAt = new \DateTimeImmutable('2026-06-19T10:00:00+00:00');
        $message = Message::create('Alice', 'Coucou', $createdAt);

        $restored = Message::fromArray($message->toArray());

        self::assertSame('Alice', $restored->author);
        self::assertSame('Coucou', $restored->body);
        self::assertSame(
            $createdAt->format(\DateTimeInterface::ATOM),
            $restored->createdAt->format(\DateTimeInterface::ATOM),
        );
    }
}
