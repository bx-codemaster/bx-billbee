<?php
/**
 * This file is part of the Billbee Custom Shop API package.
 *
 * Copyright 2019-2022 by Billbee GmbH
 *
 * For the full copyright and license information, please read the LICENSE
 * file that was distributed with this source code.
 *
 * Created by Julian Finkler <julian@mintware.de>
 */

namespace Billbee\CustomShopApi\Http;

use Psr\Http\Message\UriInterface;

class Uri implements UriInterface
{
    /** @var array<int|string, bool|int|string> */
    protected array $uri;
    protected string $scheme = '';
    protected string $authority = '';
    protected string $userInfo = '';
    protected string $host = '';
    protected ?int $port = null;
    protected string $path = '';
    protected string $query = '';
    protected string $fragment = '';

    public function __construct(string $url)
    {
        $this->uri = (array)parse_url($url);
        $this->scheme = $this->uri['scheme'] ?? '';
        $this->host = $this->uri['host'] ?? '';
        $this->port = $this->uri['port'] ?? null;
        $this->path = $this->uri['path'] ?? '';
        $this->query = $this->uri['query'] ?? '';
        $this->fragment = $this->uri['fragment'] ?? '';


        $this->userInfo = implode(':', array_filter([
            $this->uri['user'] ?? '',
            $this->uri['pass'] ?? ''
        ]));

        $this->authority = !empty(trim($this->userInfo)) ? $this->userInfo . '@' : '';
        $this->authority .= $this->host;
        // Only add port if it's not the default port for the scheme
        $isDefaultPort = ($this->scheme === 'http' && $this->port === 80) || 
                         ($this->scheme === 'https' && $this->port === 443);
        if ($this->port !== null && !empty(trim((string)$this->port)) && !$isDefaultPort) {
            $this->authority .= ':' . $this->port;
        }
    }


    /** @inheritDoc */
    public function getScheme(): string
    {
        return $this->scheme;
    }

    /** @inheritDoc */
    public function getAuthority(): string
    {
        return $this->authority;
    }

    /** @inheritDoc */
    public function getUserInfo(): string
    {
        return $this->userInfo;
    }

    /** @inheritDoc */
    public function getHost(): string
    {
        return $this->host;
    }

    /** @inheritDoc */
    public function getPort(): ?int
    {
        return $this->port;
    }

    /** @inheritDoc */
    public function getPath(): string
    {
        return $this->path;
    }

    /** @inheritDoc */
    public function getQuery(): string
    {
        return $this->query;
    }

    /** @inheritDoc */
    public function getFragment(): string
    {
        return $this->fragment;
    }

    /** @inheritDoc */
    public function withScheme(string $scheme): UriInterface
    {
        $uri = clone $this;
        $uri->scheme = $scheme;
        return $uri;
    }

    /** @inheritDoc */
    public function withUserInfo(string $user, ?string $password = null): UriInterface
    {
        $uri = clone $this;
        $uri->userInfo = implode(':', array_filter([$user, $password]));
        return $uri;
    }

    /** @inheritDoc */
    public function withHost(string $host): UriInterface
    {
        $uri = clone $this;
        $uri->host = $host;
        return $uri;
    }

    /** @inheritDoc */
    public function withPort(?int $port): UriInterface
    {
        $uri = clone $this;
        $uri->port = $port;
        return $uri;
    }

    /** @inheritDoc */
    public function withPath(string $path): UriInterface
    {
        $uri = clone $this;
        $uri->path = $path;
        return $uri;
    }

    /** @inheritDoc */
    public function withQuery(string $query): UriInterface
    {
        $uri = clone $this;
        $uri->query = $query;
        return $uri;
    }

    /** @inheritDoc */
    public function withFragment(string $fragment): UriInterface
    {
        $uri = clone $this;
        $uri->fragment = $fragment;
        return $uri;
    }

    /** @inheritDoc */
    public function __toString(): string
    {
        $url = '';
        if (!empty($this->scheme)) {
            $url .= $this->scheme;
            $url .= ':';
        }

        if (!empty($this->authority)) {
            $url .= '//';
            $url .= $this->authority;
        }

        if (!empty($this->path)) {
            $url .= $this->path;
        }

        if (!empty($this->query)) {
            $url .= '?';
            $url .= $this->query;
        }

        if (!empty($this->fragment)) {
            $url .= '#';
            $url .= $this->fragment;
        }

        return $url;
    }
}
