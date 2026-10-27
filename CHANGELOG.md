# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project
adheres to [Semantic Versioning](https://semver.org/).

## [0.1.0] - 2026-10-27

### Added

- The token layer of `contenir/contenir-preview`, for every protocol between the CMS and a site:
  `SiteKeys`, `TokenType`, `TokenSigner` and `TokenVerifier` for compact HS256 tokens with key
  rotation, a bounded lifetime and single use, refusing each failure with a `Refusal` and its
  status.
- `FileReplayGuard`, `CacheReplayGuard` (any PSR-16 cache, 1.x to 3.x) and
  `InMemoryReplayGuard`, each with a prefix so protocols keep apart.
