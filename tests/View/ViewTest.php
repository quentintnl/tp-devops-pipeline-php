<?php

declare(strict_types=1);

namespace Tests\View;

use App\Guestbook\Message;
use App\View\View;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ViewTest extends TestCase
{
    #[Test]
    public function escape_neutralises_html_special_chars(): void
    {
        $escaped = View::escape('<script>alert(1)</script>');

        self::assertStringNotContainsString('<script>', $escaped);
        self::assertStringContainsString('&lt;script&gt;', $escaped);
    }

    #[Test]
    public function home_escapes_a_malicious_message_body(): void
    {
        $message = Message::create('Mallory', '<script>alert("xss")</script>');

        $html = View::home(42, [$message]);

        // Le payload brut ne doit PAS apparaître tel quel…
        self::assertStringNotContainsString('<script>alert', $html);
        // …mais sous forme échappée.
        self::assertStringContainsString('&lt;script&gt;', $html);
    }

    #[Test]
    public function home_escapes_a_malicious_author(): void
    {
        $message = Message::create('<img src=x onerror=alert(1)>', 'coucou');

        $html = View::home(1, [$message]);

        self::assertStringNotContainsString('<img src=x', $html);
        self::assertStringContainsString('&lt;img', $html);
    }

    #[Test]
    public function home_shows_the_view_counter_and_empty_state(): void
    {
        $html = View::home(7, []);

        self::assertStringContainsString('Page vue <strong>7</strong> fois', $html);
        self::assertStringContainsString('Aucun message', $html);
    }
}
