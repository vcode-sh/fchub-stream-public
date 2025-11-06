# Changelog

All notable changes to FCHub Stream. Built out of media library trauma. Documented out of necessity.

---

## [0.2.0] - 2025-11-06

### Added

**Floating Video Player (Picture-in-Picture for People Who Scroll)**
- Playing video follows you when you scroll past it. Like YouTube but WordPress.
- Only one video plays at a time (starting second video auto-pauses first one. Revolutionary crowd control.)
- Close button actually works. Stops video too. No phantom floating players haunting your feed.
- **Drag & drop** - Grab the top bar (you'll see it), drag anywhere. Your video, your corner, your rules.
- **Resize from corners** - 4 invisible corner handles (16px). Drag any corner to resize. Always maintains 16:9 aspect ratio. Can't escape viewport boundaries.
- **Persistent memory** - Position and size saved to localStorage. Scroll away, scroll back, video reappears exactly where you put it.
- Built because watching 3 seconds of video then scrolling away is basically content consumption in 2025.

### Fixed

**Video Controls Actually Visible Now**
- Fixed videos appearing zoomed in with play/pause buttons cut off
- Was extending video 40px beyond container. FluentCommunity's overflow:hidden said no.
- Built because seeing half a play button isn't minimalist design. It's broken.

## [0.1.1] - 2025-11-06

### Fixed

**Sentry Spam Reduction**
- Stopped spamming Sentry with expected Cloudflare encoding progress (pctComplete < 100% is normal, not an error. We were being dramatic.)

## [0.1.0] - 2025-11-05

### Fixed

**Videos Actually Work After Encoding (No More 404 Mystery)**
- Fixed "Video not found" errors after encoding finished
- Fixed encoding screen flashing on every page refresh
- Cloudflare said "ready!" at 40% encoding. We believed them. Mistake.
- Now we verify videos are 100% done AND files accessible before showing player
- Encoding → video works automatically. Zero refresh. Zero 404 errors.

**What Was Broken:**
- Cloudflare webhook: `pctComplete: 40%` → we rendered iframe → manifest 404
- Webhooks "best effort" → many never arrived → database stuck at pending forever
- Every refresh: encoding flash → polling → iframe (annoying)

**The Fix:**
- Wait for `pctComplete: 100%` (not 40%)
- Probe manifest URLs before rendering (HEAD request, only render on HTTP 200)
- Update database when video ready (no more encoding flash on refresh)

Built because "just refresh the page" isn't user experience. It's surrender.

## [0.0.5] - 2025-11-05

### Fixed

**Videos Actually Work After Encoding (Without Refreshing Like It's 2010)**
- Fixed that annoying thing where encoding screen disappeared, "Video not found" appeared, you refreshed the page, and suddenly video worked
- Cloudflare was lying. Said video was ready. Wasn't ready. Classic.
- Now we wait for Cloudflare to actually finish (not just pretend to finish) before showing player
- Encoding screen → video works automatically. Zero refresh. Zero 404 errors. Zero trust issues with CDN providers.

**Page Refresh No Longer Shows Encoding Screen For Already-Ready Videos**
- Fixed that thing where you refresh and encoding screen flashes before video player appears
- Video is ready. Database knows it. Browser shows it immediately now.
- Like loading a webpage but without the unnecessary suspense.

### Notes

**What Actually Happened:**
- Cloudflare sends "ready!" webhook when video is 40% done encoding
- We believed it (mistake)
- Rendered video player immediately
- Player tried loading video that wasn't actually done encoding yet
- 404 errors. User confusion. Page refreshes. Sadness.

**What Happens Now:**
- We don't trust Cloudflare's "ready!" until it's actually 100% done
- Then we check if video files are actually accessible (not just "ready")
- Then we render player
- Video works. First try. No refresh needed.

Built because "just refresh the page" is not a feature. It's giving up.

## [0.0.4] - 2025-11-05

### Fixed

**Feed Reload No Longer Shows Encoding Screen When Video is Ready**
- Fixed feed reloads showing encoding overlay even when video is already ready
- Frontend now checks if video is ready before polling (no more unnecessary waiting)
- Videos show player immediately on reload instead of pretending to encode for 10-20 seconds

**Provider Switch Actually Works**
- Fixed 500 error when switching providers in admin settings (was throwing fatal error like it's 2015)
- Provider switching now works without crashing the admin panel

**Bunny.net Videos Actually Showing Up**
- Fixed videos going MIA after posting (was showing "Video player not available" like some kind of tease)
- Videos now display. Revolutionary? No. Functional? Finally.

### Added

**Bunny.net Stream Ready for Testing**
- Bunny.net Stream is now enabled. Two providers. That's basically an ecosystem, right?
- Upload videos → they show up → delete post → video deletes too. It works.
- Works in posts and comments because why not.

### Notes

**Bunny.net Testing**
- Bunny.net Stream is ready for testing. Everything works: upload, display, delete.
- Switch from Cloudflare to Bunny.net in admin settings if you want to test it.
- Built because two providers > one provider. Math checks out.

## [0.0.3] - 2025-11-05

### Fixed

**Long Polling Authentication Issues**
- Fixed 401 Unauthorized errors during long video encoding (15+ minutes)
- Made nonce verification flexible for status checks: invalid nonce no longer blocks polling if user is logged in
- Status check endpoint now allows requests with expired/invalid nonce during long polling sessions
- Logs warnings for monitoring instead of blocking requests

**PostHog Analytics Error**
- Fixed "Already scheduled or no user id" PostHog error during status checks
- Added validation to ensure `distinctId` exists before sending events to PostHog
- PostHog events now fail gracefully without breaking video status checks

**Frontend Polling Improvements**
- Polling now continues after 401/403 errors instead of stopping immediately
- Frontend automatically refreshes nonce from window settings on each API call
- Added retry logic: stops polling only after 5 consecutive errors (was stopping on first error)
- Improved error handling: logs errors but continues polling unless too many failures occur
- Better error messages for debugging authentication issues

### Notes

**Nonce Expiration During Long Polling**
- WordPress nonces can expire during long encoding sessions (15+ minutes)
- Plugin now handles this gracefully by allowing requests with expired nonce if user is still logged in
- Warnings are logged for monitoring but don't block functionality
- This ensures videos aren't stuck in "encoding" state when Cloudflare finishes encoding

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

[0.2.0]: https://github.com/vcode-sh/fchub-stream-public/releases/tag/v0.2.0
[0.1.1]: https://github.com/vcode-sh/fchub-stream-public/releases/tag/v0.1.1
[0.1.0]: https://github.com/vcode-sh/fchub-stream-public/releases/tag/v0.1.0
[0.0.5]: https://github.com/vcode-sh/fchub-stream-public/releases/tag/v0.0.5
[0.0.4]: https://github.com/vcode-sh/fchub-stream-public/releases/tag/v0.0.4
[0.0.3]: https://github.com/vcode-sh/fchub-stream-public/releases/tag/v0.0.3
[0.0.2]: https://github.com/vcode-sh/fchub-stream-public/releases/tag/v0.0.2
[0.0.1]: https://github.com/vcode-sh/fchub-stream-public/releases/tag/v0.0.1
