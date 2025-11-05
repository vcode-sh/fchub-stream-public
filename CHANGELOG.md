# Changelog

All notable changes to FCHub Stream. Built out of media library trauma. Documented out of necessity.

---

## [0.0.2] - 2025-11-05

### Fixed

**Upload Settings Save Issue**
- Fixed browser warning when saving upload settings after setting Maximum File Size
- Fixed race condition where success message showed before API call completed
- Fixed false negative error when saving comment video settings (when value hadn't changed)
- Improved error handling: errors now display in UI instead of just browser alerts
- Reduced Sentry noise: 400-level validation errors no longer trigger Sentry alerts

**Duration Settings UI**
- Fixed duration slider: changed step from 60 seconds to 15 seconds (allows setting durations like 30 seconds, 45 seconds, etc.)
- Reduced maximum duration limit from 24 hours to 6 hours (21600 seconds) - more reasonable for most use cases
- Added note that duration validation is not yet implemented (coming soon)

**Backend Fixes**
- Fixed `StreamConfig::save()` false negative: now correctly distinguishes between "no change" and actual save failures
- Properly handles WordPress `update_option()` returning false when value hasn't changed
- Updated duration validation: maximum changed from 86400 seconds (24h) to 21600 seconds (6h)

**Frontend Improvements**
- Upload settings now wait for actual API response before showing success/error
- Error messages display inline in the UI instead of browser alerts
- Better error propagation from parent to child components
- Success/error states properly managed via props

### Notes

**Duration Validation Not Yet Implemented**
- The "Maximum Video Duration" setting is saved and can be configured, but it is **not currently enforced** during video uploads
- Videos longer than the configured limit will still be accepted
- Duration validation will be implemented in a future release
- This is indicated in the UI with a warning note

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

**Analytics (Optional)**
- PostHog integration for tracking video uploads and usage
- Configurable in admin settings
- GDPR-compliant, anonymous for non-logged users
- Disabled by default

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

[0.0.2]: https://github.com/vcode-sh/fchub-stream-public/releases/tag/v0.0.2
[0.0.1]: https://github.com/vcode-sh/fchub-stream-public/releases/tag/v0.0.1
