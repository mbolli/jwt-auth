<?php

namespace Tuupola\Middleware;

use ArrayObject;
use Equip\Dispatch\MiddlewareCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Tuupola\Http\Factory\ResponseFactory;
use Tuupola\Http\Factory\ServerRequestFactory;
use Tuupola\Http\Factory\StreamFactory;
use Tuupola\Middleware\JwtAuthentication\RequestMethodRule;
use Tuupola\Middleware\JwtAuthentication\RequestPathRule;

/**
 * @internal
 */
#[CoversClass(JwtAuthentication::class)]
final class JwtAuthenticationTest extends TestCase
{
    /** @codingStandardsIgnoreStart */
    public static string $acmeToken = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiIsImtpZCI6ImFjbWUifQ.eyJpc3MiOiJBY21lIFRvb3RocGljcyBMdGQiLCJpYXQiOiIxNDI4ODE5OTQxIiwiZXhwIjoiMTc0NDM1Mjc0MSIsImF1ZCI6Ind3dy5leGFtcGxlLmNvbSIsInN1YiI6InNvbWVvbmVAZXhhbXBsZS5jb20iLCJzY29wZSI6WyJyZWFkIiwid3JpdGUiLCJkZWxldGUiXX0.yBhYlsMabKTh31taAiH8i2ScPMKm84jxIDNxft6EiTA';
    public static string $betaToken = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiIsImtpZCI6ImJldGEifQ.eyJraWQiOiJiZXRhIiwiaXNzIjoiQmV0YSBTcG9uc29yc2hpcCBMdGQiLCJpYXQiOiIxNDI4ODE5OTQxIiwiZXhwIjoiMTc0NDM1Mjc0MSIsImF1ZCI6Ind3dy5leGFtcGxlLmNvbSIsInN1YiI6InNvbWVvbmVAZXhhbXBsZS5jb20iLCJzY29wZSI6WyJyZWFkIl19.msxcBx4_ZQtCkkjHyTDWDC0mac4cFNSxLqkzNL30JB8';
    public static string $expired = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJBY21lIFRvb3RocGljcyBMdGQiLCJpYXQiOjE0Mjg4MTk5NDEsImV4cCI6MTQ4MDcyMzIwMCwiYXVkIjoid3d3LmV4YW1wbGUuY29tIiwic3ViIjoic29tZW9uZUBleGFtcGxlLmNvbSIsInNjb3BlIjpbInJlYWQiLCJ3cml0ZSIsImRlbGV0ZSJdfQ.ZydGEHVmca4ofQRCuMOfZrUXprAoe5GcySg4I-lwIjc';

    /** @codingStandardsIgnoreEnd */
    public static array $acmeTokenArray = [
        'iss' => 'Acme Toothpics Ltd',
        'iat' => '1428819941',
        'exp' => '1744352741',
        'aud' => 'www.example.com',
        'sub' => 'someone@example.com',
        'scope' => ['read', 'write', 'delete'],
    ];

    public static array $betaTokenArray = [
        'iss' => 'Beta Sponsorship Ltd',
        'iat' => '1428819941',
        'exp' => '1744352741',
        'aud' => 'www.example.com',
        'sub' => 'someone@example.com',
        'scope' => ['read'],
    ];

    public function testShouldReturn401WithoutToken(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/api');

        $default = static function (RequestInterface $request) {
            $response = (new ResponseFactory())->createResponse();
            $response->getBody()->write('Success');

            return $response;
        };

        $collection = new MiddlewareCollection([
            $auth = new JwtAuthentication([
                'secret' => 'supersecretkeyyoushouldnotcommittogithub',
            ]),
        ]);

        $response = $collection->dispatch($request, $default);

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('', (string) $response->getBody());
    }

    public function testShouldReturn200WithTokenFromHeader(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/api')
            ->withHeader('X-Token', 'Bearer ' . self::$acmeToken);

        $default = static function (ServerRequestInterface $request) {
            $response = (new ResponseFactory())->createResponse();
            $response->getBody()->write('Success');

            return $response;
        };

        $collection = new MiddlewareCollection([
            new JwtAuthentication([
                'secret' => 'supersecretkeyyoushouldnotcommittogithub',
                'header' => 'X-Token',
            ]),
        ]);

        $response = $collection->dispatch($request, $default);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Success', (string) $response->getBody());
    }

    public function testShouldReturn200WithTokenFromHeaderWithCustomRegexp(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/api')
            ->withHeader('X-Token', self::$acmeToken);

        $default = static function (ServerRequestInterface $request) {
            $response = (new ResponseFactory())->createResponse();
            $response->getBody()->write('Success');

            return $response;
        };

        $collection = new MiddlewareCollection([
            new JwtAuthentication([
                'secret' => 'supersecretkeyyoushouldnotcommittogithub',
                'header' => 'X-Token',
                'regexp' => '/(.*)/',
            ]),
        ]);

        $response = $collection->dispatch($request, $default);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Success', (string) $response->getBody());
    }

    public function testShouldReturn200WithTokenFromCookie(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/api')
            ->withCookieParams(['nekot' => self::$acmeToken]);

        $default = static function (ServerRequestInterface $request) {
            $response = (new ResponseFactory())->createResponse();
            $response->getBody()->write('Success');

            return $response;
        };

        $collection = new MiddlewareCollection([
            new JwtAuthentication([
                'secret' => 'supersecretkeyyoushouldnotcommittogithub',
                'cookie' => 'nekot',
            ]),
        ]);

        $response = $collection->dispatch($request, $default);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Success', (string) $response->getBody());
    }

    public function testShouldReturn200WithTokenFromBearerCookie(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/api')
            ->withCookieParams(['nekot' => 'Bearer ' . self::$acmeToken]);

        $default = static function (ServerRequestInterface $request) {
            $response = (new ResponseFactory())->createResponse();
            $response->getBody()->write('Success');

            return $response;
        };

        $collection = new MiddlewareCollection([
            new JwtAuthentication([
                'secret' => 'supersecretkeyyoushouldnotcommittogithub',
                'cookie' => 'nekot',
            ]),
        ]);

        $response = $collection->dispatch($request, $default);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Success', (string) $response->getBody());
    }

    public function testShouldReturn200WithSecretArray(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/api')
            ->withHeader('Authorization', 'Bearer ' . self::$betaToken);

        $default = static function (ServerRequestInterface $request) {
            $response = (new ResponseFactory())->createResponse();
            $response->getBody()->write('Success');

            return $response;
        };

        $collection = new MiddlewareCollection([
            new JwtAuthentication([
                'secret' => [
                    'acme' => 'supersecretkeyyoushouldnotcommittogithub',
                    'beta' => 'anothersecretkeyfornevertocommittogithub',
                ],
            ]),
        ]);

        $response = $collection->dispatch($request, $default);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Success', (string) $response->getBody());
    }

    public function testShouldReturn200WithSecretArrayCheckKid(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/api')
            ->withHeader('Authorization', 'Bearer ' . self::$betaToken);

        $default = static function (ServerRequestInterface $request) {
            $response = (new ResponseFactory())->createResponse();
            $response->getBody()->write('Success');

            return $response;
        };

        $collection = new MiddlewareCollection([
            new JwtAuthentication([
                'algorithm' => ['acme' => 'HS256', 'beta' => 'HS256'],
                'secret' => [
                    'acme' => 'supersecretkeyyoushouldnotcommittogithub',
                    'beta' => 'anothersecretkeyfornevertocommittogithub',
                ],
            ]),
        ]);

        $response = $collection->dispatch($request, $default);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Success', (string) $response->getBody());
    }

    public function testShouldReturn401WithSecretArray(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/api')
            ->withHeader('Authorization', 'Bearer ' . self::$betaToken);

        $default = static function (ServerRequestInterface $request) {
            $response = (new ResponseFactory())->createResponse();
            $response->getBody()->write('Success');

            return $response;
        };

        $collection = new MiddlewareCollection([
            new JwtAuthentication([
                'secret' => [
                    'xxxx' => 'supersecretkeyyoushouldnotcommittogithub',
                    'yyyy' => 'anothersecretkeyfornevertocommittogithub',
                ],
            ]),
        ]);

        $response = $collection->dispatch($request, $default);
        self::assertSame(401, $response->getStatusCode());
        self::assertSame('', (string) $response->getBody());
    }

    public function testShouldReturn200WithSecretArrayAccess(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/api')
            ->withHeader('Authorization', 'Bearer ' . self::$betaToken);

        $default = static function (ServerRequestInterface $request) {
            $response = (new ResponseFactory())->createResponse();
            $response->getBody()->write('Success');

            return $response;
        };

        $secret = new ArrayObject();
        $secret['acme'] = 'supersecretkeyyoushouldnotcommittogithub';
        $secret['beta'] = 'anothersecretkeyfornevertocommittogithub';

        $collection = new MiddlewareCollection([
            new JwtAuthentication([
                'secret' => $secret,
            ]),
        ]);

        $response = $collection->dispatch($request, $default);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Success', (string) $response->getBody());
    }

    public function testShouldReturn401WithSecretArrayAccess(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/api')
            ->withHeader('Authorization', 'Bearer ' . self::$betaToken);

        $default = static function (ServerRequestInterface $request) {
            $response = (new ResponseFactory())->createResponse();
            $response->getBody()->write('Success');

            return $response;
        };

        $secret = new ArrayObject();
        $secret['xxxx'] = 'supersecretkeyyoushouldnotcommittogithub';
        $secret['yyyy'] = 'anothersecretkeyfornevertocommittogithub';

        $collection = new MiddlewareCollection([
            new JwtAuthentication([
                'secret' => $secret,
            ]),
        ]);

        $response = $collection->dispatch($request, $default);
        self::assertSame(401, $response->getStatusCode());
        self::assertSame('', (string) $response->getBody());
    }

    public function testShouldAlterResponseWithAnonymousAfter(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/api')
            ->withHeader('Authorization', 'Bearer ' . self::$acmeToken);

        $default = static function (ServerRequestInterface $request) {
            $response = (new ResponseFactory())->createResponse();
            $response->getBody()->write('Success');

            return $response;
        };

        $collection = new MiddlewareCollection([
            new JwtAuthentication([
                'secret' => 'supersecretkeyyoushouldnotcommittogithub',
                'after' => function ($response, $arguments) {
                    return $response->withHeader('X-Brawndo', 'plants crave');
                },
            ]),
        ]);

        $response = $collection->dispatch($request, $default);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('plants crave', (string) $response->getHeaderLine('X-Brawndo'));
    }

    public function testShouldAlterResponseWithInvokableAfter(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/api')
            ->withHeader('Authorization', 'Bearer ' . self::$acmeToken);

        $default = static function (ServerRequestInterface $request) {
            $response = (new ResponseFactory())->createResponse();
            $response->getBody()->write('Success');

            return $response;
        };

        $collection = new MiddlewareCollection([
            new JwtAuthentication([
                'secret' => 'supersecretkeyyoushouldnotcommittogithub',
                'after' => new TestAfterHandler(),
            ]),
        ]);

        $response = $collection->dispatch($request, $default);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(
            'plants crave',
            (string) $response->getHeaderLine('X-Brawndo')
        );
    }

    public function testShouldAlterResponseWithArrayNotationAfter(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/api')
            ->withHeader('Authorization', 'Bearer ' . self::$acmeToken);

        $default = static function (ServerRequestInterface $request) {
            $response = (new ResponseFactory())->createResponse();
            $response->getBody()->write('Success');

            return $response;
        };

        $collection = new MiddlewareCollection([
            new JwtAuthentication([
                'secret' => 'supersecretkeyyoushouldnotcommittogithub',
                'after' => [TestAfterHandler::class, 'after'],
            ]),
        ]);

        $response = $collection->dispatch($request, $default);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(
            'like from toilet?',
            (string) $response->getHeaderLine('X-Water')
        );
    }

    public function testShouldReturn401WithInvalidAlgorithm(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/api')
            ->withHeader('Authorization', 'Bearer ' . self::$acmeToken);

        $default = static function (ServerRequestInterface $request) {
            $response = (new ResponseFactory())->createResponse();
            $response->getBody()->write('Success');

            return $response;
        };

        $collection = new MiddlewareCollection([
            new JwtAuthentication([
                'secret' => 'supersecretkeyyoushouldnotcommittogithub',
                'algorithm' => 'nosuch',
            ]),
        ]);

        $response = $collection->dispatch($request, $default);

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('', (string) $response->getBody());
    }

    public function testShouldReturn200WithOptions(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/api')
            ->withMethod('OPTIONS');

        $default = static function (ServerRequestInterface $request) {
            $response = (new ResponseFactory())->createResponse();
            $response->getBody()->write('Success');

            return $response;
        };

        $collection = new MiddlewareCollection([
            new JwtAuthentication([
                'secret' => 'supersecretkeyyoushouldnotcommittogithub',
            ]),
        ]);

        $response = $collection->dispatch($request, $default);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Success', (string) $response->getBody());
    }

    public function testShouldReturn400WithInvalidToken(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/api')
            ->withHeader('Authorization', 'Bearer invalid' . self::$acmeToken);

        $default = static function (ServerRequestInterface $request) {
            $response = (new ResponseFactory())->createResponse();
            $response->getBody()->write('Success');

            return $response;
        };

        $collection = new MiddlewareCollection([
            new JwtAuthentication([
                'secret' => 'supersecretkeyyoushouldnotcommittogithub',
            ]),
        ]);

        $response = $collection->dispatch($request, $default);

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('', (string) $response->getBody());
    }

    public function testShouldReturn400WithExpiredToken(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/api')
            ->withHeader('Authorization', 'Bearer ' . self::$expired);

        $default = static function (ServerRequestInterface $request) {
            $response = (new ResponseFactory())->createResponse();
            $response->getBody()->write('Success');

            return $response;
        };

        $collection = new MiddlewareCollection([
            new JwtAuthentication([
                'secret' => 'supersecretkeyyoushouldnotcommittogithub',
            ]),
        ]);

        $response = $collection->dispatch($request, $default);

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('', (string) $response->getBody());
    }

    public function testShouldReturn200WithoutTokenWithPath(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/public');

        $default = static function (ServerRequestInterface $request) {
            $response = (new ResponseFactory())->createResponse();
            $response->getBody()->write('Success');

            return $response;
        };

        $collection = new MiddlewareCollection([
            new JwtAuthentication([
                'path' => ['/api', '/foo'],
                'secret' => 'supersecretkeyyoushouldnotcommittogithub',
            ]),
        ]);

        $response = $collection->dispatch($request, $default);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Success', (string) $response->getBody());
    }

    public function testShouldReturn200WithoutTokenWithIgnore(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/api/ping');

        $default = static function (ServerRequestInterface $request) {
            $response = (new ResponseFactory())->createResponse();
            $response->getBody()->write('Success');

            return $response;
        };

        $collection = new MiddlewareCollection([
            new JwtAuthentication([
                'path' => ['/api', '/foo'],
                'ignore' => ['/api/ping'],
                'secret' => 'supersecretkeyyoushouldnotcommittogithub',
            ]),
        ]);

        $response = $collection->dispatch($request, $default);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Success', (string) $response->getBody());
    }

    public function testShouldNotAllowInsecure(): void
    {
        $this->expectException('RuntimeException');

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'http://example.com/api')
            ->withHeader('Authorization', 'Bearer ' . self::$acmeToken);

        $default = static function (ServerRequestInterface $request) {
            $response = (new ResponseFactory())->createResponse();
            $response->getBody()->write('Success');

            return $response;
        };

        $collection = new MiddlewareCollection([
            new JwtAuthentication([
                'secret' => 'supersecretkeyyoushouldnotcommittogithub',
            ]),
        ]);

        $response = $collection->dispatch($request, $default);
    }

    public function testShouldAllowInsecure(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'http://example.com/api')
            ->withHeader('Authorization', 'Bearer ' . self::$acmeToken);

        $default = static function (ServerRequestInterface $request) {
            $response = (new ResponseFactory())->createResponse();
            $response->getBody()->write('Success');

            return $response;
        };

        $collection = new MiddlewareCollection([
            new JwtAuthentication([
                'secret' => 'supersecretkeyyoushouldnotcommittogithub',
                'secure' => false,
            ]),
        ]);

        $response = $collection->dispatch($request, $default);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Success', (string) $response->getBody());
    }

    public function testShouldRelaxInsecureInLocalhost(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'http://localhost/api')
            ->withHeader('Authorization', 'Bearer ' . self::$acmeToken);

        $default = static function (ServerRequestInterface $request) {
            $response = (new ResponseFactory())->createResponse();
            $response->getBody()->write('Success');

            return $response;
        };

        $collection = new MiddlewareCollection([
            new JwtAuthentication([
                'secret' => 'supersecretkeyyoushouldnotcommittogithub',
            ]),
        ]);

        $response = $collection->dispatch($request, $default);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Success', (string) $response->getBody());
    }

    public function testShouldRelaxInsecureInExampleCom(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'http://example.com/api')
            ->withHeader('Authorization', 'Bearer ' . self::$acmeToken);

        $default = static function (ServerRequestInterface $request) {
            $response = (new ResponseFactory())->createResponse();
            $response->getBody()->write('Success');

            return $response;
        };

        $collection = new MiddlewareCollection([
            new JwtAuthentication([
                'secret' => 'supersecretkeyyoushouldnotcommittogithub',
                'relaxed' => ['example.com'],
            ]),
        ]);

        $response = $collection->dispatch($request, $default);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Success', (string) $response->getBody());
    }

    public function testShouldAttachToken(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/api')
            ->withHeader('Authorization', 'Bearer ' . self::$acmeToken);

        $default = static function (ServerRequestInterface $request) {
            $acmeToken = $request->getAttribute('token');

            $response = (new ResponseFactory())->createResponse();
            $response->getBody()->write($acmeToken['iss']);

            return $response;
        };

        $collection = new MiddlewareCollection([
            new JwtAuthentication([
                'secret' => 'supersecretkeyyoushouldnotcommittogithub',
            ]),
        ]);

        $response = $collection->dispatch($request, $default);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Acme Toothpics Ltd', (string) $response->getBody());
    }

    public function testShouldAttachCustomToken(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/api')
            ->withHeader('Authorization', 'Bearer ' . self::$acmeToken);

        $default = static function (ServerRequestInterface $request) {
            $acmeToken = $request->getAttribute('nekot');

            $response = (new ResponseFactory())->createResponse();
            $response->getBody()->write($acmeToken['iss']);

            return $response;
        };

        $collection = new MiddlewareCollection([
            new JwtAuthentication([
                'secret' => 'supersecretkeyyoushouldnotcommittogithub',
                'attribute' => 'nekot',
            ]),
        ]);

        $response = $collection->dispatch($request, $default);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Acme Toothpics Ltd', (string) $response->getBody());
    }

    public function testShouldCallAfterWithProperArguments(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/api')
            ->withHeader('Authorization', 'Bearer ' . self::$acmeToken);

        $decoded = null;
        $token = null;

        $default = static function (ServerRequestInterface $request) {
            $response = (new ResponseFactory())->createResponse();
            $response->getBody()->write('Success');

            return $response;
        };

        $collection = new MiddlewareCollection([
            new JwtAuthentication([
                'secret' => 'supersecretkeyyoushouldnotcommittogithub',
                'after' => function ($response, $arguments) use (&$decoded, &$token) {
                    $decoded = $arguments['decoded'];
                    $token = $arguments['token'];
                },
            ]),
        ]);

        $response = $collection->dispatch($request, $default);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Success', (string) $response->getBody());
        self::assertSame(self::$acmeTokenArray, (array) $decoded);
        self::assertSame(self::$acmeToken, $token);
    }

    public function testShouldCallBeforeWithProperArguments(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/api')
            ->withHeader('Authorization', 'Bearer ' . self::$acmeToken);

        $decoded = null;
        $token = null;

        $default = static function (ServerRequestInterface $request) {
            $response = (new ResponseFactory())->createResponse();
            $response->getBody()->write('Success');

            return $response;
        };

        $collection = new MiddlewareCollection([
            new JwtAuthentication([
                'secret' => 'supersecretkeyyoushouldnotcommittogithub',
                'before' => function ($response, $arguments) use (&$decoded, &$token) {
                    $decoded = $arguments['decoded'];
                    $token = $arguments['token'];
                },
            ]),
        ]);

        $response = $collection->dispatch($request, $default);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Success', (string) $response->getBody());
        self::assertSame(self::$acmeTokenArray, (array) $decoded);
        self::assertSame(self::$acmeToken, $token);
    }

    public function testShouldCallAnonymousErrorFunction(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/api');

        $default = static function (ServerRequestInterface $request) {
            $response = (new ResponseFactory())->createResponse();
            $response->getBody()->write('Success');

            return $response;
        };

        $collection = new MiddlewareCollection([
            new JwtAuthentication([
                'secret' => 'supersecretkeyyoushouldnotcommit',
                'error' => function (ResponseInterface $response, $arguments) use (&$dummy) {
                    $response->getBody()->write('error');

                    return $response
                        ->withHeader('X-Electrolytes', 'Plants');
                },
            ]),
        ]);

        $response = $collection->dispatch($request, $default);

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('Plants', $response->getHeaderLine('X-Electrolytes'));
        self::assertSame('error', (string) $response->getBody());
    }

    public function testShouldCallInvokableErrorClass(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/api');

        $dummy = null;

        $default = static function (ServerRequestInterface $request) {
            $response = (new ResponseFactory())->createResponse();
            $response->getBody()->write('Success');

            return $response;
        };

        $collection = new MiddlewareCollection([
            new JwtAuthentication([
                'secret' => 'supersecretkeyyoushouldnotcommit',
                'error' => new TestErrorHandler(),
            ]),
        ]);

        $response = $collection->dispatch($request, $default);

        self::assertSame(402, $response->getStatusCode());
        self::assertSame('Bar', $response->getHeaderLine('X-Foo'));
        self::assertSame(TestErrorHandler::class, (string) $response->getBody());
    }

    public function testShouldCallArrayNotationError(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/api');

        $dummy = null;

        $default = static function (ServerRequestInterface $request) {
            $response = (new ResponseFactory())->createResponse();
            $response->getBody()->write('Success');

            return $response;
        };

        $collection = new MiddlewareCollection([
            new JwtAuthentication([
                'secret' => 'supersecretkeyyoushouldnotcommit',
                'error' => [TestErrorHandler::class, 'error'],
            ]),
        ]);

        $response = $collection->dispatch($request, $default);

        self::assertSame(418, $response->getStatusCode());
        self::assertSame('Foo', $response->getHeaderLine('X-Bar'));
        self::assertSame(TestErrorHandler::class, (string) $response->getBody());
    }

    public function testShouldCallErrorAndModifyBody(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/api');

        $dummy = null;

        $default = static function (ServerRequestInterface $request) {
            $response = (new ResponseFactory())->createResponse();
            $response->getBody()->write('Success');

            return $response;
        };

        $collection = new MiddlewareCollection([
            new JwtAuthentication([
                'secret' => 'supersecretkeyyoushouldnotcommittogithub',
                'error' => function (ResponseInterface $response, $arguments) use (&$dummy) {
                    $dummy = true;
                    $response->getBody()->write('Error');

                    return $response;
                },
            ]),
        ]);

        $response = $collection->dispatch($request, $default);

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('Error', (string) $response->getBody());
        self::assertTrue($dummy);
    }

    public function testShouldAllowUnauthenticatedHttp(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/public/foo');

        $default = static function (ServerRequestInterface $request) {
            $response = (new ResponseFactory())->createResponse();
            $response->getBody()->write('Success');

            return $response;
        };

        $collection = new MiddlewareCollection([
            new JwtAuthentication([
                'secret' => 'supersecretkeyyoushouldnotcommittogithub',
                'path' => ['/api', '/bar'],
            ]),
        ]);

        $response = $collection->dispatch($request, $default);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Success', (string) $response->getBody());
    }

    public function testShouldReturn401FromAfter(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/api')
            ->withHeader('Authorization', 'Bearer ' . self::$acmeToken);

        $default = static function (ServerRequestInterface $request) {
            $response = (new ResponseFactory())->createResponse();
            $response->getBody()->write('Success');

            return $response;
        };

        $collection = new MiddlewareCollection([
            new JwtAuthentication([
                'secret' => 'supersecretkeyyoushouldnotcommittogithub',
                'after' => function ($response, $arguments) {
                    return $response
                        ->withBody((new StreamFactory())->createStream())
                        ->withStatus(401);
                },
            ]),
        ]);

        $response = $collection->dispatch($request, $default);

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('', (string) $response->getBody());
    }

    public function testShouldModifyRequestUsingAnonymousBefore(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/')
            ->withHeader('Authorization', 'Bearer ' . self::$acmeToken);

        $default = function (ServerRequestInterface $request) {
            $response = (new ResponseFactory())->createResponse();
            $test = $request->getAttribute('test');
            $response->getBody()->write($test);

            return $response;
        };

        $collection = new MiddlewareCollection([
            new JwtAuthentication([
                'secret' => 'supersecretkeyyoushouldnotcommittogithub',
                'before' => function ($request, $arguments) {
                    return $request->withAttribute('test', 'test');
                },
            ]),
        ]);

        $response = $collection->dispatch($request, $default);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('test', (string) $response->getBody());
    }

    public function testShouldModifyRequestUsingInvokableBefore(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/')
            ->withHeader('Authorization', 'Bearer ' . self::$acmeToken);

        $default = static function (ServerRequestInterface $request) {
            $response = (new ResponseFactory())->createResponse();
            $test = $request->getAttribute('test');
            $response->getBody()->write($test);

            return $response;
        };

        $collection = new MiddlewareCollection([
            new JwtAuthentication([
                'secret' => 'supersecretkeyyoushouldnotcommittogithub',
                'before' => new TestBeforeHandler(),
            ]),
        ]);

        $response = $collection->dispatch($request, $default);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('invoke', (string) $response->getBody());
    }

    public function testShouldModifyRequestUsingArrayNotationBefore(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/')
            ->withHeader('Authorization', 'Bearer ' . self::$acmeToken);

        $default = static function (ServerRequestInterface $request) {
            $response = (new ResponseFactory())->createResponse();
            $test = $request->getAttribute('test');
            $response->getBody()->write($test);

            return $response;
        };

        $collection = new MiddlewareCollection([
            new JwtAuthentication([
                'secret' => 'supersecretkeyyoushouldnotcommittogithub',
                'before' => [TestBeforeHandler::class, 'before'],
            ]),
        ]);

        $response = $collection->dispatch($request, $default);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('function', (string) $response->getBody());
    }

    public function testShouldHandleRulesArrayBug84(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/api');

        $default = static function (ServerRequestInterface $request) {
            $response = (new ResponseFactory())->createResponse();
            $response->getBody()->write('Success');

            return $response;
        };

        $collection = new MiddlewareCollection([
            new JwtAuthentication([
                'secret' => 'supersecretkeyyoushouldnotcommittogithub',
                'rules' => [
                    new RequestPathRule(['/api'], ['/api/login']),
                    new RequestMethodRule(),
                ],
            ]),
        ]);

        $response = $collection->dispatch($request, $default);

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('', (string) $response->getBody());

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/api/login');

        $response = $collection->dispatch($request, $default);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Success', (string) $response->getBody());
    }

    public function testShouldHandleDefaultPathBug118(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/api');

        $default = static function (ServerRequestInterface $request) {
            $response = (new ResponseFactory())->createResponse();
            $response->getBody()->write('Success');

            return $response;
        };

        $collection = new MiddlewareCollection([
            new JwtAuthentication([
                'secret' => 'supersecretkeyyoushouldnotcommittogithub',
                'ignore' => '/api/login',
            ]),
        ]);

        $response = $collection->dispatch($request, $default);

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('', (string) $response->getBody());

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/api/login');

        $response = $collection->dispatch($request, $default);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Success', (string) $response->getBody());
    }

    public function testShouldBindToMiddleware(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/')
            ->withHeader('Authorization', 'Bearer ' . self::$acmeToken);

        $default = static function (ServerRequestInterface $request) {
            $response = (new ResponseFactory())->createResponse();
            $before = $request->getAttribute('before');
            $response->getBody()->write($before);

            return $response;
        };

        $collection = new MiddlewareCollection([
            new JwtAuthentication([
                'secret' => 'supersecretkeyyoushouldnotcommittogithub',
                'before' => function ($request, $arguments) {
                    $before = get_class($this);

                    return $request->withAttribute('before', $before);
                },
                'after' => function ($response, $arguments) {
                    $after = get_class($this);
                    $response->getBody()->write($after);

                    return $response;
                },
            ]),
        ]);

        $response = $collection->dispatch($request, $default);
        $expected = str_repeat('Tuupola\\Middleware\\JwtAuthentication', 2);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame($expected, (string) $response->getBody());
    }

    public function testShouldHandlePsr7(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/api')
            ->withHeader('X-Token', 'Bearer ' . self::$acmeToken);

        $response = (new ResponseFactory())->createResponse();

        $auth = new JwtAuthentication([
            'secret' => 'supersecretkeyyoushouldnotcommittogithub',
            'header' => 'X-Token',
        ]);

        $next = static function (ServerRequestInterface $request, ResponseInterface $response) {
            $response->getBody()->write('Success');

            return $response;
        };

        $response = $auth($request, $response, $next);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Success', (string) $response->getBody());
    }

    public function testShouldHaveUriInErrorHandlerIssue96(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/api/foo?bar=pop');

        $dummy = null;

        $default = static function (ServerRequestInterface $request) {
            $response = (new ResponseFactory())->createResponse();
            $response->getBody()->write('Success');

            return $response;
        };

        $collection = new MiddlewareCollection([
            new JwtAuthentication([
                'secret' => 'supersecretkeyyoushouldnotcommittogithub',
                'error' => function (ResponseInterface $response, $arguments) use (&$dummy) {
                    $dummy = $arguments['uri'];
                },
            ]),
        ]);

        $response = $collection->dispatch($request, $default);

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('', (string) $response->getBody());
        self::assertSame('https://example.com/api/foo?bar=pop', $dummy);
    }

    public function testShouldUseCookieIfHeaderMissingIssue156(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', 'https://example.com/api')
            ->withCookieParams(['token' => self::$acmeToken]);

        $default = static function (ServerRequestInterface $request) {
            $response = (new ResponseFactory())->createResponse();
            $response->getBody()->write('Success');

            return $response;
        };

        $collection = new MiddlewareCollection([
            new JwtAuthentication([
                'secret' => 'supersecretkeyyoushouldnotcommittogithub',
                'header' => 'X-Token',
                'regexp' => '/(.*)/',
            ]),
        ]);

        $response = $collection->dispatch($request, $default);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Success', (string) $response->getBody());
    }
}
