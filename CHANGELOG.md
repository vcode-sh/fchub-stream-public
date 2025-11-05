# Changelog

All notable changes to FCHub Stream. Built out of media library trauma. Documented out of necessity.

---

## [Unreleased]

Nothing yet. Check back when something breaks or improves.

---

## [0.0.1] - 2025-11-05

First release. Beta testing. Video streaming for FluentCommunity because WordPress media library and video don't mix.

### What Works

**Video Uploads**
- Drag & drop videos → upload to Cloudflare Stream → done
- No WordPress media library involved (that's the whole point)
- Real-time progress bar with speed estimates
- Delete post? Video deletes too. Automatic cleanup.
- Bunny.net option exists but Phase 2 (not tested yet)

**In Your FluentCommunity Portal**
- Upload button appears in post composer
- Videos show up in posts with embedded player
- Actually works. Revolutionary? No. Functional? Yes.

**Admin Settings**
- Connect Cloudflare Stream (Account ID, API Token, subdomain)
- Set upload limits (how big, which formats, how long)
- Credentials encrypted (because security matters)
- Bunny.net config there but don't touch it yet

**Auto-Updates Built In**
- WordPress notifies you about new versions
- Click update → installs from GitHub → done
- No manual downloads unless you want them

### What Doesn't Work Yet

- Bunny.net (in code, Phase 2, untested)
- Comment video uploads (coming)
- Video analytics (maybe later)
- Whatever else you'll ask for in GitHub issues

### Requirements

- WordPress 6.7+
- PHP 8.3+
- FluentCommunity (must be active)
- Cloudflare Stream account

Upload pretty much any video format. MP4, MOV, AVI, whatever. Cloudflare handles conversion.

### Known Issues

This is beta. Bugs exist. That's why you're testing.

Found something broken? [GitHub Issues](https://github.com/vcode-sh/fchub-stream-public/issues). I fix bugs faster than I create them. Usually.

### License & Credits

**Beta = Free.** After beta = commercial license (price TBA).

Built by [Vibe Code](https://x.com/vcode_sh). One dev. Zero corporate BS.

Part of [FCHub.co](https://fchub.co) - FluentCommunity tools that actually work.

---

## How This Works

**For users**: Check here before updating. See what's new, what's fixed, what broke.

**For me** (when releasing):
1. Move `[Unreleased]` stuff to new version section
2. Add date: `## [0.1.0] - 2025-12-01`
3. Keep it short and VOICE-TONE.md style (sarcastic, honest, no BS)
4. Group by: What Works, What's Fixed, What Broke, What's New
5. Update version links at bottom

**Versioning**:
- Big changes (1.0.0) - might break stuff
- New features (0.1.0) - backwards compatible
- Bug fixes (0.0.1) - no new features

**Example for next release**:

```markdown
## [0.1.0] - 2025-12-01

### What's New
- Bunny.net works now (tested, both providers functional)
- Comment video uploads (you asked, I delivered)

### Fixed
- Upload progress bar actually accurate now
- Cloudflare webhooks no longer randomly fail
- Video deletion doesn't explode when offline

### What Changed
- Settings panel reorganized (less cluttered)
- Upload UI slightly less ugly
```

Built out of media library trauma. Documented out of necessity.

---

[Unreleased]: https://github.com/vcode-sh/fchub-stream-public/compare/v0.0.1...HEAD
[0.0.1]: https://github.com/vcode-sh/fchub-stream-public/releases/tag/v0.0.1
