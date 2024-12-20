<?php

namespace ArchLinux\RedirectLL\Test\Middleware;

use ArchLinux\RedirectLL\Middleware\LLRedirect;
use Flarum\Database\AbstractModel;
use Flarum\Discussion\Discussion;
use Flarum\Discussion\DiscussionRepository;
use Flarum\Discussion\IdWithTransliteratedSlugDriver;
use Flarum\Http\RouteCollectionUrlGenerator;
use Flarum\Http\SlugManager;
use Flarum\Http\UrlGenerator;
use Flarum\Tags\TagRepository;
use Flarum\User\User;
use Flarum\User\UsernameSlugDriver;
use Flarum\User\UserRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;

class LLRedirectTest extends TestCase
{
    private RouteCollectionUrlGenerator&MockObject $routeCollectionUrlGenerator;

    private UriInterface&MockObject $requestUri;

    private LLRedirect $llRedirect;

    private ServerRequestInterface&MockObject $request;

    private RequestHandlerInterface&MockObject $requestHandler;

    public function setUp(): void
    {
        $urlGenerator = $this->createMock(UrlGenerator::class);
        $tagRepository = $this->createMock(TagRepository::class);
        $userRepository = $this->createMock(UserRepository::class);
        $discussionRepository = $this->createMock(DiscussionRepository::class);
        $this->request = $this->createMock(ServerRequestInterface::class);
        $this->requestHandler = $this->createMock(RequestHandlerInterface::class);
        $this->requestUri = $this->createMock(UriInterface::class);
        $this->routeCollectionUrlGenerator = $this->createMock(RouteCollectionUrlGenerator::class);
        $slugManager = $this->createMock(SlugManager::class);

        $discussionRepository
            ->expects($this->any())
            ->method('findOrFail')
            ->willReturn(
                new class extends AbstractModel {
                    public int $id = 123;
                    public string $slug = 'foo';
                }
            );

        $tagRepository
            ->expects($this->any())
            ->method('findOrFail')
            ->willReturn(
                new class extends AbstractModel {
                    public string $slug = 'foo-tag';
                }
            );

        $userRepository
            ->expects($this->any())
            ->method('findOrFail')
            ->willReturn(
                new class extends AbstractModel {
                    public string $username = 'foo-username';
                }
            );

        $urlGenerator
            ->expects($this->any())
            ->method('to')
            ->willReturn($this->routeCollectionUrlGenerator);

        $this->requestUri
            ->expects($this->atLeastOnce())
            ->method('getPath')
            ->willReturn('/');

        $this->request
            ->expects($this->atLeastOnce())
            ->method('getMethod')
            ->willReturn('GET');

        $this->request
            ->expects($this->atLeastOnce())
            ->method('getUri')
            ->willReturn($this->requestUri);

        $slugManager
            ->expects($this->any())
            ->method('forResource')
            ->willReturnMap([
                [Discussion::class, new IdWithTransliteratedSlugDriver($discussionRepository)],
                [User::class, new UsernameSlugDriver($userRepository)]
            ]);

        $this->llRedirect = new LLRedirect(
            $urlGenerator,
            $tagRepository,
            $userRepository,
            $discussionRepository,
            $slugManager
        );
    }

    public function testRedirectPostings(): void
    {
        $this->routeCollectionUrlGenerator
            ->expects($this->exactly(2))
            ->method('route')
            ->willReturnMap([
                ['default', '/'],
                ['discussion', ['id' => '123-foo', 'near' => '3'], '/new-url']
            ]);

        $this->requestUri
            ->expects($this->atLeastOnce())
            ->method('getQuery')
            ->willReturn('page=Postings;thread=123;post=2');

        $response = $this->llRedirect->process($this->request, $this->requestHandler);
        $this->assertRedirect($response, '/new-url');
    }

    private function assertRedirect(ResponseInterface $response, string $expectedUrl, int $code = 301): void
    {
        $this->assertEquals($code, $response->getStatusCode());
        $this->assertCount(1, $response->getHeader('Location'));
        $this->assertEquals($expectedUrl, $response->getHeader('Location')[0]);
    }

    public function testRedirectPostingsDoubleEncoded(): void
    {
        $this->routeCollectionUrlGenerator
            ->expects($this->exactly(2))
            ->method('route')
            ->willReturnMap([
                ['default', '/'],
                ['discussion', ['id' => '123-foo', 'near' => '3'], '/new-url']
            ]);

        $this->requestUri
            ->expects($this->atLeastOnce())
            ->method('getQuery')
            ->willReturn(urlencode('page=Postings;thread=123;post=2'));

        $response = $this->llRedirect->process($this->request, $this->requestHandler);
        $this->assertRedirect($response, '/new-url');
    }

    public function testRedirectThreads(): void
    {
        $this->routeCollectionUrlGenerator
            ->expects($this->exactly(2))
            ->method('route')
            ->willReturnMap([
                ['default', '/'],
                ['tag', ['slug' => 'foo-tag'], '/new-url']
            ]);

        $this->requestUri
            ->expects($this->atLeastOnce())
            ->method('getQuery')
            ->willReturn('page=Threads;forum=456');

        $response = $this->llRedirect->process($this->request, $this->requestHandler);
        $this->assertRedirect($response, '/new-url');
    }

    public function testRedirectUsers(): void
    {
        $this->routeCollectionUrlGenerator
            ->expects($this->exactly(2))
            ->method('route')
            ->willReturnMap([
                ['default', '/'],
                ['user', ['username' => 'foo-username'], '/new-url']
            ]);

        $this->requestUri
            ->expects($this->atLeastOnce())
            ->method('getQuery')
            ->willReturn('page=ShowUser;user=789');

        $response = $this->llRedirect->process($this->request, $this->requestHandler);
        $this->assertRedirect($response, '/new-url');
    }

    public function testRedirectFallback(): void
    {
        $this->routeCollectionUrlGenerator
            ->expects($this->exactly(1))
            ->method('route')
            ->with('default')
            ->willReturn('/new-url');

        $this->requestUri
            ->expects($this->atLeastOnce())
            ->method('getQuery')
            ->willReturn('page=FOO');

        $response = $this->llRedirect->process($this->request, $this->requestHandler);
        $this->assertRedirect($response, '/new-url', 302);
    }

    public function testIgnoreUnknownRequests(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $this->requestHandler
            ->expects($this->once())
            ->method('handle')
            ->with($this->request)
            ->willReturn($response);

        $this->requestUri
            ->expects($this->atLeastOnce())
            ->method('getPath')
            ->willReturn('/foo');
        $handledResponse = $this->llRedirect->process($this->request, $this->requestHandler);

        $this->assertSame($response, $handledResponse);
    }

    public function testSendNotFoundForFeeds(): void
    {
        $this->requestUri
            ->expects($this->atLeastOnce())
            ->method('getQuery')
            ->willReturn('page=GetRecent');
        $response = $this->llRedirect->process($this->request, $this->requestHandler);
        $this->assertEquals(404, $response->getStatusCode());
    }
}
