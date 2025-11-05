![FCHub Stream Cover](https://files.vcode.sh/fchub/plugins/stream/fchub-stream-cover.webp)

# FCHub Stream

Video streaming for FluentCommunity. Built because WordPress media library and video don't mix.

Direct uploads to Cloudflare Stream. No media library gymnastics. No manual conversion. Upload. Done.

## ⚠️ Beta Testing - Phase 1

**This is beta software.** We're testing Cloudflare Stream integration only (Phase 1).

Bunny.net support exists but isn't tested yet. Stick to Cloudflare for now.

**Beta Testing Guide**: https://docs.fchub.co/docs/fchub-stream/beta-testing

Found bugs? Shit breaks? That's the point. Report it.

## What It Does

- **Direct Uploads** - Videos to Cloudflare Stream. Zero WordPress media library involvement.
- **Drag & Drop** - Upload interface that works.
- **Auto Cleanup** - Delete post? Video deletes too.

Two providers in code (Cloudflare + Bunny.net). Testing Cloudflare first. Bunny.net Phase 2.

## Part of FCHub Ecosystem

Independent plugin for FluentCommunity. Part of **[FCHub.co](https://fchub.co)**:

- **FCHub Stream** - Video streaming. Beta now.
- **FCHub Chat** - Real-time chat. Coming soon.
- **FCHub Mobile** - Unofficial mobile app. Coming soon.

## Requirements

- WordPress 6.7+
- PHP 8.3+
- FluentCommunity (active)
- Cloudflare Stream account

## Installation

**Download**: [fchub-stream.zip](https://github.com/vcode-sh/fchub-stream-public/releases/latest/download/fchub-stream.zip)

**Install**:
1. WordPress → Plugins → Upload
2. Activate
3. Done. No composer. Just works.

## Setup Cloudflare Stream

1. Get [Cloudflare Stream](https://www.cloudflare.com/products/cloudflare-stream/) account
2. Dashboard → grab:
   - Account ID
   - API Token (Stream permissions)
   - Customer Subdomain
3. WordPress → FluentCommunity → FCHub Stream → Cloudflare tab
4. Paste. Save. Works.

**Don't touch Bunny.net settings.** Phase 2. Not ready.

## Usage

Portal → Create post → Video button → Drag video → Upload → Done.

## Documentation

Everything: **https://docs.fchub.co/docs/fchub-stream**

Beta testing specific: **https://docs.fchub.co/docs/fchub-stream/beta-testing**

## Support

- **Bugs/Issues**: [GitHub Issues](https://github.com/vcode-sh/fchub-stream-public/issues)
- **Questions**: [Docs](https://docs.fchub.co/docs/fchub-stream) first

Beta means bugs exist. Report them. We fix them.

## Credits & Support

Built by [Vibe Code](https://x.com/vcode_sh). Independent dev. Zero corporate funding.

**Support development**:
- ☕ [Buy me a coffee](https://buymeacoffee.com/vcode)
- ⭐ [Star on GitHub](https://github.com/vcode-sh/fchub-stream-public)
- 🐦 [@vcode_sh](https://x.com/vcode_sh)

## License

MIT - Use it. Modify it. Credit appreciated.

---

Built out of media library trauma. Streams out of necessity.

**Part of [FCHub.co](https://fchub.co)** - FluentCommunity tools that work.
