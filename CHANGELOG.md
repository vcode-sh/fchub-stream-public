# Changelog

All notable changes to FCHub Stream. Built out of media library trauma. Documented out of necessity.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
Version numbers try to follow [Semantic Versioning](https://semver.org/spec/v2.0.0.html). Sometimes.

---

## [Unreleased]

Nothing yet. Check back when something breaks or improves.

---

## [0.0.1] - 2025-11-05

### What is this?

First release. Beta testing phase. Video streaming for FluentCommunity because WordPress media library and video don't mix.

### Added - The Good Stuff

**Video Upload (The Main Thing)**
- Direct uploads to Cloudflare Stream - no WordPress media library involvement
- Direct uploads to Bunny.net Stream - because options matter
- Drag & drop upload interface - works like it should
- Real-time upload progress - with speed estimates and everything
- File validation before upload - saves you from format drama
- Auto-cleanup when posts deleted - videos go away too

**Portal Integration**
- Upload button in FluentCommunity post composer - right where you need it
- Video preview in posts - embedded player that actually works
- Shortcode support: `[fchub_stream:VIDEO_ID]` - processes automatically
- Comment video support - coming soon (Phase 2)

**Admin Settings Panel**
- Cloudflare Stream configuration - Account ID, API Token, Customer Subdomain
- Bunny.net configuration - Library ID, API Key (not tested yet, Phase 2)
- Upload limits - max file size (1-10000 MB), allowed formats, max duration
- Stream settings - active provider, auto-publish, polling intervals
- All credentials encrypted - using WordPress AUTH_KEY/AUTH_SALT

**Developer Features**
- Sentry error monitoring integration - optional, for tracking production bugs
- REST API endpoints - clean structure under `/fluent-community/v2/stream/*`
- Webhook support - Cloudflare webhooks with signature verification
- Service layer architecture - PSR-4 autoloading, dependency injection
- Vue 3 admin/portal apps - modern stack, built with Vite

**Updates & Distribution**
- Automatic updates via GitHub releases - WordPress native notifications
- Plugin Update Checker integration - users get update prompts in dashboard
- Versioned + latest ZIP files - both available in releases
- GitHub Actions workflow - automated release builds on tag push

### Technical Details

**Supported Providers** (Phase 1 - Cloudflare only tested):
- Cloudflare Stream - fully tested, production ready
- Bunny.net Stream - code exists, not tested yet (Phase 2)

**Requirements**:
- WordPress 6.7+
- PHP 8.3+
- FluentCommunity (active)
- Cloudflare Stream account (for Phase 1)

**Video Formats Supported**:
- MP4, MOV, AVI, WMV, FLV, MKV, WEBM, MPG, MPEG, M4V, 3GP

**Max Upload Size**: Configurable (1-10000 MB)

**Video Processing**:
- Handled by provider (Cloudflare/Bunny)
- Status polling every 2-5 seconds during encoding
- Automatic thumbnail generation
- Adaptive bitrate streaming

### What's NOT Here Yet

**Phase 2 Features** (coming):
- Bunny.net Stream testing and verification
- Comment video uploads
- Video analytics
- Thumbnail customization
- More provider integrations (maybe)

### Known Issues

**Beta Testing**:
- This is beta software - bugs exist, that's expected
- Bunny.net support untested - stick to Cloudflare for now
- No commercial use yet - testing only

### Security

- Credentials encrypted at rest (WordPress AUTH_KEY/AUTH_SALT)
- Webhook signature verification (Cloudflare)
- Permission checks on all endpoints (admin = manage_options, portal = logged in)
- File validation before upload (MIME type, extension, size)
- No WordPress media library involvement - less attack surface

### Credits

Built by [Vibe Code](https://x.com/vcode_sh). Independent dev. Zero corporate funding.

Part of [FCHub.co](https://fchub.co) ecosystem - FluentCommunity tools that work.

### License

**Beta = Free. After Beta = Pay.**

Free during beta testing. Commercial license after beta (pricing TBA).

See [LICENSE](LICENSE) for details.

---

## How This Changelog Works

### For Users

Check here before updating. See what's new, what's fixed, what might break.

### For Maintainers (Me)

Before each release:

1. **Move changes from `[Unreleased]` to new version section**
2. **Add release date**: `## [X.Y.Z] - YYYY-MM-DD`
3. **Categorize changes**:
   - **Added** - new features
   - **Changed** - changes to existing functionality
   - **Deprecated** - features being phased out
   - **Removed** - features removed
   - **Fixed** - bug fixes
   - **Security** - security improvements

4. **Write in VOICE-TONE.md style**:
   - Direct, no BS
   - "WordPress media library trauma" energy
   - Self-deprecating when appropriate
   - Honest about bugs and limitations

5. **Update version links at bottom** (when we have more versions)

### Versioning Guide

- **Major (1.0.0)**: Big changes, might break stuff
- **Minor (0.1.0)**: New features, backwards compatible
- **Patch (0.0.1)**: Bug fixes, no new features

### Example Entry Format

```markdown
## [0.1.0] - 2025-12-01

### Added
- Bunny.net testing complete - both providers work now
- Comment video uploads - requested feature, delivered
- Video analytics - view counts, because metrics matter

### Fixed
- Cloudflare webhook signature verification - was broken, now fixed
- Upload progress calculation - now shows actual speed
- Video deletion error handling - fails gracefully instead of exploding

### Changed
- Upload UI redesigned - less cluttered, more intuitive
- Settings panel reorganized - grouped by function

### Security
- Updated Sentry SDK - fixes CVE-whatever
- Enhanced credential encryption - now using sodium if available
```

---

## Notes

- This changelog started at v0.0.1 (first beta)
- Previous development history exists but wasn't versioned
- "Unreleased" section shows what's coming next
- Dates use ISO 8601 format (YYYY-MM-DD)
- Links to releases coming when we have more than one version

Built out of media library trauma. Documented out of necessity.

---

[Unreleased]: https://github.com/vcode-sh/fchub-stream-public/compare/v0.0.1...HEAD
[0.0.1]: https://github.com/vcode-sh/fchub-stream-public/releases/tag/v0.0.1
