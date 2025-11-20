=== FCHub Stream ===

Contributors: vcode-sh
Tags: video, streaming, cloudflare, bunny.net, fluentcommunity, upload, media
Requires at least: 6.7
Tested up to: 6.9.0
Requires PHP: 8.3
Stable tag: 0.9.6
License: Proprietary
License URI: https://github.com/vcode-sh/fchub-stream-public/blob/main/LICENSE

Direct video uploads for FluentCommunity. Built because WordPress media library and video don't mix. Direct uploads to Cloudflare Stream. No media library gymnastics. No manual conversion. Upload. Done.

== Description ==

FCHub Stream. Built out of media library trauma. WordPress media library + video = suffering. fchub-stream + streaming providers = sanity.

Direct uploads to Cloudflare Stream or Bunny.net from your FluentCommunity. Zero WordPress media library involvement. Because creators deserve better than converting videos manually.

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

1. Download the plugin from [fchub.co](https://fchub.co)
2. Upload the plugin to your WordPress plugins directory
3. Activate the plugin
4. Get your license at fchub.co
5. Configure your streaming provider credentials in the admin panel
6. Start uploading videos!

== Frequently Asked Questions ==

= Which streaming providers are supported? =

Cloudflare Stream and Bunny.net Stream

= Do videos go through WordPress media library? =

Hell no. Videos go direct to streaming provider. No media library, that's the whole point.

= What happens when I delete a post with videos? =

Post gone? Video gone too. Automatic cleanup. No manual video management. You're welcome.

= Why did you build this instead of using WordPress media library? =

I did use it. Until video 47. Then I snapped. Built this instead. My therapist approved.

= Is this enterprise-ready? =

If your enterprise can handle video streaming built by one dev who hates media libraries, absolutely.

== Changelog ==

Full changelog: [CHANGELOG.md](https://github.com/vcode-sh/fchub-stream-public/blob/main/CHANGELOG.md)

= 0.9.6 - 2025-11-20 =
* Critical fix: Fixed fatal error when SDK package is missing - Plugin now gracefully handles missing SDK package instead of crashing. Added stub class for StreamLicenseManager when SDK is unavailable. Fixed readlink() warnings. Plugin works even if SDK package is missing (license features disabled).

= 0.9.5 - 2025-11-20 =
* Fixed broken symlinks in release builds - Build process now converts SDK symlinks to real copies. Users get fully functional plugins without needing composer. No more "file not found" errors. Because broken symlinks aren't a feature, they're broken.

= 0.9.4 - 2025-11-20 =
* License validation actually works now - Fixed license validation sometimes saying "all good!" when your license was actually deleted. Now when you delete a license, the plugin actually notices. Revolutionary? No. Expected? Yes. Fixed? Finally.

= 0.9.3 - 2025-11-19 =
* Videos no longer disappear when editing posts - Used `array_key_exists()` instead of `isset()` to differentiate between "user didn't touch media" and "user explicitly deleted it".
* Video deletion actually works now - Two hooks were fighting over the same video. Added global flag for hooks to communicate.
* Upload modal now inherits FluentCommunity's dark mode - Because uploading videos at 2am in blinding white wasn't the vibe.
* Code quality improvements - Fixed WordPress Coding Standards violations.

= 0.9.2 - 2025-11-18 =
* Disabled autoplay for Bunny.net video players - Videos now require user interaction to start. Because surprise audio is annoying.
* Fixed missing vendor/ directory in release ZIPs - Users don't need composer. Plugin just works. As intended.
* GitHub Actions now properly packages all dependencies - No more "Class not found" errors.
* Analytics performance improvements - Reduced unnecessary API calls and optimized data processing.


= 0.9.1 - 2025-11-10 =
* Fixed WordPress compatibility warning - Updated "Tested up to" from 6.7 to 6.9. WordPress 6.8.3 works. WordPress 6.9 beta works. Warning gone. Simple fix.

= 0.9.0 - 2025-11-10 =
* License system added - Because "free forever" wasn't sustainable. Servers cost money. Who knew?
* Configuration simplified - Admin settings got a makeover. Less clicks, more streaming. Revolutionary? No. Less annoying? Yes.
* Performance improvements - Reduced API calls by ~30%. Made things faster. Because waiting is for 2015.
* Documentation fixes - Fixed typos, clarified confusing parts. Because "figure it out yourself" isn't documentation.
* General improvements - Fixed bugs, improved error messages, fixed UI glitches. Made things work better.
* Why 0.2.0 → 0.9.0? 73 internal beta builds happened. I counted them. I fixed them. Versions 0.3.0-0.8.9 exist, just not in public. I'm calling this 0.9.0 because I'm done with internal chaos.

= 0.2.0 - 2025-11-06 =
* Floating video player - Playing video follows you when scrolling. Like YouTube but WordPress.
* Only one video plays at a time. Starting second video auto-pauses first one.
* Drag & drop - Grab top bar, drag anywhere. Resize from corners (16px handles, maintains 16:9).
* Persistent memory - Position/size saved to localStorage. Reappears exactly where you left it.
* Fixed videos appearing zoomed in with play/pause buttons cut off (40px overflow removed).

= 0.1.1 - 2025-11-06 =
* Stopped spamming Sentry with expected Cloudflare encoding progress (pctComplete < 100% is normal, not an error. We were being dramatic.)

= 0.1.0 - 2025-11-05 =
* Fixed videos showing "not found" after encoding finished
* Fixed encoding screen flashing on page refresh
* Cloudflare said "ready!" at 40%. We believed them. Mistake. Now verify 100% completion.
* Videos work automatically after encoding. Zero refresh. Zero 404 errors.
 
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

= 0.1.0 =
Major fix: Videos now work immediately after encoding. No more "Video not found" errors. No page refresh needed. Cloudflare's definition of "ready" and ours are now aligned (took 6 hours).

= 0.0.1 =
Initial release. Beta testing phase. Video streaming for people done with media library BS.
