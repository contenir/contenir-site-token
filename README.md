# contenir-site-token

Signed one-time tokens between the Contenir CMS and a site. The CMS signs a token for one protocol,
such as a preview or a cache clear, and the site checks it before acting on it. A token is a
compact JSON Web Signature (RFC 7515) using HMAC-SHA256, lives a bounded time, and is accepted
once.

Each protocol keeps its own claims and its own endpoint; this package holds the parts they share:

- [`contenir/contenir-preview`](https://github.com/contenir/contenir-preview): the preview protocol,
  `contenir-preview+jwt`.
- `contenir/contenir-page-cache-mezzio` (planned): clearing a site's caches, `contenir-cache+jwt`.

## Shared settings

Both sides build the same `SiteKeys`: who issues tokens and for which site (both origins, with no
path), and the secrets that sign them by key id (at least 32 bytes each). A site may hold several
keys while rotating; the issuer signs with one.

```php
use Contenir\SiteToken\SiteKeys;

$keys = new SiteKeys(
    issuer:   'https://admin.example.com',
    audience: 'https://www.example.com',
    secrets:  ['2026-10' => $secret],
    signWith: '2026-10',
);
```

## A protocol's type

A `TokenType` names a protocol's tokens: the `typ` in their header, the `ver` in their payload,
how long one may live, and how large one may be (a megabyte unless told otherwise). A token signed
for one type is refused by every other, even under the same secret.

```php
use Contenir\SiteToken\TokenType;

$cache = new TokenType('contenir-cache+jwt', version: 1, maxLifetime: 60);
```

## Signing

The CMS signs for whoever asked. The shared claims (`iss`, `aud`, `iat`, `exp`, `jti`, `sub`,
`ver`) come first, then the protocol's own, which may not name a shared one:

```php
use Contenir\SiteToken\TokenSigner;

$token = (new TokenSigner($keys, $clock))->sign($cache, (string) $editorId, ['scope' => 'all']);
```

## Verifying

The site checks a token in a fixed order and stops at the first failure:

1. something was sent, no larger than the type allows;
2. three base64url segments, a JSON header and payload;
3. `alg` is HS256, `typ` is the type's, `kid` is a key the site holds;
4. the signature matches, compared in constant time;
5. `ver` is the type's, `iss` and `aud` are the site's, `iat` and `exp` are within bounds, with 30
   seconds' leeway for the clocks;
6. `jti` is 32 hex digits, not seen before.

```php
use Contenir\SiteToken\RefusedToken;
use Contenir\SiteToken\TokenVerifier;

try {
    $verified = (new TokenVerifier($keys, $clock, $replay))->verify($cache, $posted);
} catch (RefusedToken $refused) {
    return new TextResponse($refused->getMessage(), $refused->refusal->status());
}

$verified->claims['scope'];   // the protocol's own claims, for it to check
```

A `RefusedToken` carries a `Refusal`: its `status()` is 400 for what is not a token, 401 for one
that can't be trusted, and 409 for one already used. Its `reason()` never names the secret, the
token or the values expected. A protocol may answer with its own words for each case.

## Replay guards

A guard remembers each accepted token's id until the token could no longer be accepted:

- `FileReplayGuard`: files in a directory every process on one server shares;
- `CacheReplayGuard`: any PSR-16 cache (1.x to 3.x), which servers sharing the cache share too;
- `InMemoryReplayGuard`: one long-running process, or tests.

Each takes a prefix, so protocols sharing a directory or a cache keep apart.
