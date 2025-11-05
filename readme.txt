=== FCHub Stream ===

Contributors: vcode-sh
Tags: video, streaming, cloudflare, bunny.net, fluentcommunity, upload, media
Requires at least: 6.7
Tested up to: 6.7
Requires PHP: 8.3
Stable tag: 0.0.5
License: Proprietary
License URI: https://github.com/vcode-sh/fchub-stream-public/blob/main/LICENSE

Video streaming for FluentCommunity. Built because WordPress media library and video don't mix. Direct uploads to Cloudflare Stream. No media library gymnastics. No manual conversion. Upload. Done.

== Description ==

FCHub Stream. Built out of media library trauma. WordPress media library + video = suffering. fchub-stream + streaming providers = sanity.

Direct uploads to Cloudflare Stream or Bunny.net. Zero WordPress media library involvement. Because creators deserve better than converting videos manually.

**⚠️ Beta Testing - Phase 1**

This is beta software. We're testing Cloudflare Stream integration only (Phase 1). Bunny.net support exists but isn't tested yet. Stick to Cloudflare for now.

Found bugs? Shit breaks? That's the point. Report it.

**Features:**
* Direct uploads to Cloudflare Stream. Zero media library pain.
* Drag & drop upload interface that works.
* Real-time encoding status.
* Auto cleanup - delete post? Video deletes too.
* Two providers in code (Cloudflare + Bunny.net). Testing Cloudflare first.
* FluentCommunity portal integration.

**Requirements:**
* WordPress 6.7+
* PHP 8.3+
* FluentCommunity (active)
* Cloudflare Stream account (Bunny.net Phase 2)

== Installation ==

1. Download the plugin from [GitHub Releases](https://github.com/vcode-sh/fchub-stream-public/releases)
2. Upload the plugin to your WordPress plugins directory
3. Activate the plugin
4. Configure your streaming provider credentials in the admin panel
5. Start uploading videos!

== Frequently Asked Questions ==

= Which streaming providers are supported? =

Cloudflare Stream (tested, works). Bunny.net (in code, Phase 2, not tested yet). Pick Cloudflare for now.

= Do videos go through WordPress media library? =

Hell no. Videos go direct to streaming provider. No media library gymnastics. That's the whole point.

= What happens when I delete a post with videos? =

Post gone? Video gone too. Automatic cleanup. No manual video management. You're welcome.

= Why did you build this instead of using WordPress media library? =

I did use it. Until video 47. Then I snapped. Built this instead. My therapist approved.

= Is this enterprise-ready? =

If your enterprise can handle video streaming built by one dev who hates media libraries, absolutely.

== Changelog ==

Full changelog: [CHANGELOG.md](https://github.com/vcode-sh/fchub-stream-public/blob/main/CHANGELOG.md)

= 0.0.5 - 2025-11-05 =
* Fixed videos showing "Video not found" 404 errors after encoding screen
* Fixed Cloudflare webhook marking videos as ready before encoding actually finished
* Now waits for pctComplete: 100 before showing video player (not just readyToStream: true)
* Fixed page refresh showing encoding screen for already-ready videos
* Videos work immediately after encoding without manual page refresh
* Encoding screen → video works automatically. Zero refresh. Zero 404 errors.

= 0.0.4 - 2025-11-05 =
* Fixed feed reloads showing encoding overlay when video is already ready
* Fixed 500 error when switching providers in admin settings
* Fixed Bunny.net videos not displaying after posting
* Bunny.net Stream now enabled and ready for testing
* Videos show player immediately on reload instead of encoding screen
* Provider switching works without crashing admin panel

= 0.0.3 - 2025-11-05 =
* Fixed 401 Unauthorized errors during long video encoding (15+ minutes)
* Made nonce verification flexible for status checks during long polling
* Fixed "Already scheduled or no user id" PostHog error
* Frontend polling now continues after auth errors instead of stopping
* Improved error handling: stops polling only after 5 consecutive errors
* Nonce automatically refreshed from window settings on each API call

= 0.0.2 - 2025-11-05 =
* Fixed browser warning when saving upload settings
* Fixed race condition in upload settings save flow
* Fixed false negative error when saving unchanged comment video settings
* Improved error handling: inline error messages instead of alerts
* Fixed duration slider: allows 15-second increments (was 60 seconds)
* Reduced maximum duration limit from 24 hours to 6 hours
* Reduced Sentry noise: 400-level errors no longer trigger alerts

= 0.0.1 - 2025-11-05 =
* Initial beta release. Built out of media library trauma.
* Cloudflare Stream integration (tested, works)
* Bunny.net Stream integration (in code, Phase 2, untested)
* FluentCommunity portal integration
* Automatic video deletion on post removal
* Zero WordPress media library involvement. Revolutionary? No. Functional? Yes.

For detailed changelog with full feature list, technical details, and known issues, check [CHANGELOG.md](https://github.com/vcode-sh/fchub-stream-public/blob/main/CHANGELOG.md).

== Upgrade Notice ==

= 0.0.1 =
Initial release. Beta testing phase. Video streaming for people done with media library BS.
