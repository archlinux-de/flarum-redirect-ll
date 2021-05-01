<?php

namespace ArchLinux\RedirectLL\Test\Middleware;

use ArchLinux\RedirectLL\Middleware\LLRedirect;
use Flarum\Discussion\DiscussionRepository;
use Flarum\Http\RouteCollectionUrlGenerator;
use Flarum\Http\UrlGenerator;
use Flarum\Tags\TagRepository;
use Flarum\User\UserRepository;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;

class LLRedirectTest extends TestCase
{
    public function testRedirectPostings(): void
    {
        $urlGeneretor = $this->createMock(UrlGenerator::class);
        $tagRepository = $this->createMock(TagRepository::class);
        $userRepository = $this->createMock(UserRepository::class);
        $discussionRepository = $this->createMock(DiscussionRepository::class);
        $request = $this->createMock(ServerRequestInterface::class);
        $requestHandler = $this->createMock(RequestHandlerInterface::class);
        $requestUri = $this->createMock(UriInterface::class);
        $routeCollectionUrlGenerator = $this->createMock(RouteCollectionUrlGenerator::class);

        $discussionRepository
            ->expects($this->atLeastOnce())
            ->method('findOrFail')
            ->willReturn(
                new class {
                    public int $id = 1;
                }
            );

        $routeCollectionUrlGenerator
            ->expects($this->atLeastOnce())
            ->method('route')
            ->willReturn('/new-url');

        $urlGeneretor
            ->expects($this->atLeastOnce())
            ->method('to')
            ->willReturn($routeCollectionUrlGenerator);

        $requestUri
            ->expects($this->atLeastOnce())
            ->method('getPath')
            ->willReturn('/');
        $requestUri
            ->expects($this->atLeastOnce())
            ->method('getQuery')
            ->willReturn('page=Postings;thread=123;post=2');

        $request
            ->expects($this->atLeastOnce())
            ->method('getMethod')
            ->willReturn('GET');
        $request
            ->expects($this->atLeastOnce())
            ->method('getUri')
            ->willReturn($requestUri);

        $llRedirect = new LLRedirect($urlGeneretor, $tagRepository, $userRepository, $discussionRepository);
        $response = $llRedirect->process($request, $requestHandler);

        $this->assertEquals(301,$response->getStatusCode());
        $this->assertEquals('/new-url', $response->getHeader('Location')[0]);
    }
}
