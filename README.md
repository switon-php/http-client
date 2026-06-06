# Switon HTTP Client Package

[![CI](https://img.shields.io/github/actions/workflow/status/switon-php/http-client/ci.yml?branch=main&label=CI)](https://github.com/switon-php/http-client/actions/workflows/ci.yml) [![PHP 8.3+](https://img.shields.io/badge/PHP-8.3%2B-777BB4)](https://www.php.net/)

Switon's JSON-first HTTP client for application services that need typed failures, lifecycle events, pooled host reuse,
and response helpers.

## Highlights

- **Pooled host reuse:** repeated calls to the same host reuse the same pooled engine setup.
- **Single client entrypoint:** `HttpClientInterface` gives app services one injectable HTTP client.
- **JSON-friendly defaults:** shortcut methods and response parsing fit API-style calls.
- **Raw request support:** non-JSON requests can still use the same client path.
- **Layered failures:** transport, status, and parsing errors are separated.
- **Request tuning:** timeout, proxy, TLS, CA, and pool size settings are centralized.
- **Lifecycle visibility:** request activity is exposed through client events.

## Installation

```bash
composer require switon/http-client
```

## Quick Start

```php
use Switon\Core\Attribute\Autowired;
use Switon\HttpClient\HttpClientInterface;

class UserService
{
    #[Autowired] protected HttpClientInterface $httpClient;

    public function createUser(array $data): array
    {
        return $this->httpClient
            ->post('https://api.example.com/users', $data)
            ->json();
    }
}
```

Docs: https://docs.switon.dev/latest/http-client

## License

MIT.
