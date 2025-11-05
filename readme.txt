=== FCHub Stream ===

Contributors: vcode-sh
Tags: video, streaming, cloudflare, bunny.net, fluentcommunity, upload, media
Requires at least: 6.7
Tested up to: 6.7
Requires PHP: 8.3
Stable tag: 0.0.1
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
