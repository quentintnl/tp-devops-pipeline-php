<?php

declare(strict_types=1);

namespace Tests\Guestbook;

use App\Guestbook\GuestbookService;
use App\Guestbook\Message;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\InMemoryCache;
use Tests\Fakes\InMemoryMessageRepository;

final class GuestbookServiceTest extends TestCase
{
    #[Test]
    public function record_view_increments_the_counter(): void
    {
        $cache = new InMemoryCache();
        $service = new GuestbookService(new InMemoryMessageRepository(), $cache);

        self::assertSame(1, $service->recordView());
        self::assertSame(2, $service->recordView());
        self::assertSame(3, $service->recordView());
        self::assertSame(3, $cache->store[GuestbookService::VIEW_COUNTER_KEY]);
    }

    #[Test]
    public function recent_messages_serves_the_cache_when_present(): void
    {
        $repo = new InMemoryMessageRepository();
        $repo->add(Message::create('FromDb', 'devrait être ignoré'));

        $cache = new InMemoryCache();
        // Cache pré-rempli → doit primer sur le repo.
        $cache->store[GuestbookService::RECENT_LIST_KEY] = [
            Message::create('FromCache', 'servi depuis le cache')->toArray(),
        ];

        $service = new GuestbookService($repo, $cache);
        $messages = $service->recentMessages();

        self::assertCount(1, $messages);
        self::assertSame('FromCache', $messages[0]->author);
    }

    #[Test]
    public function recent_messages_reads_repo_then_populates_cache_on_miss(): void
    {
        $repo = new InMemoryMessageRepository();
        $repo->add(Message::create('Alice', 'premier'));
        $repo->add(Message::create('Bob', 'second'));

        $cache = new InMemoryCache();
        self::assertArrayNotHasKey(GuestbookService::RECENT_LIST_KEY, $cache->store);

        $service = new GuestbookService($repo, $cache);
        $messages = $service->recentMessages();

        // Lu depuis le repo (le plus récent d'abord).
        self::assertSame('Bob', $messages[0]->author);
        // Et le cache a bien été peuplé.
        self::assertArrayHasKey(GuestbookService::RECENT_LIST_KEY, $cache->store);
        self::assertCount(2, $cache->store[GuestbookService::RECENT_LIST_KEY]);
    }

    #[Test]
    public function recent_messages_is_limited_to_five(): void
    {
        $repo = new InMemoryMessageRepository();
        for ($i = 1; $i <= 7; $i++) {
            $repo->add(Message::create("Auteur{$i}", "message {$i}"));
        }

        $service = new GuestbookService($repo, new InMemoryCache());

        self::assertCount(GuestbookService::RECENT_LIMIT, $service->recentMessages());
    }

    #[Test]
    public function add_message_persists_and_invalidates_the_list_cache(): void
    {
        $repo = new InMemoryMessageRepository();
        $cache = new InMemoryCache();
        // On simule un cache liste « chaud » qui doit être invalidé.
        $cache->store[GuestbookService::RECENT_LIST_KEY] = [
            Message::create('Vieux', 'ancien cache')->toArray(),
        ];

        $service = new GuestbookService($repo, $cache);
        $message = $service->addMessage('Alice', 'Mon message');

        // Persisté dans le repo.
        self::assertCount(1, $repo->messages);
        self::assertSame('Alice', $repo->messages[0]->author);
        self::assertSame('Mon message', $message->body);

        // Cache liste invalidé.
        self::assertArrayNotHasKey(GuestbookService::RECENT_LIST_KEY, $cache->store);
        self::assertContains(GuestbookService::RECENT_LIST_KEY, $cache->deleted);
    }

    #[Test]
    public function add_then_recent_messages_reflects_the_new_message(): void
    {
        $repo = new InMemoryMessageRepository();
        $cache = new InMemoryCache();
        $service = new GuestbookService($repo, $cache);

        $service->addMessage('Alice', 'Bonjour');
        $messages = $service->recentMessages();

        self::assertCount(1, $messages);
        self::assertSame('Bonjour', $messages[0]->body);
    }
}
