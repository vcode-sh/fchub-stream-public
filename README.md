# FCHub Stream

Video streaming for FluentCommunity. Built because WordPress media library and video don't mix.

Direct uploads to Cloudflare Stream or Bunny.net. No media library gymnastics. No manual video conversion. Pick your provider. Upload videos. Done.

## What It Does

- **Direct Uploads** - Videos go straight to streaming provider. Zero WordPress media library involvement.
- **Two Providers** - Cloudflare Stream or Bunny.net. Pick one. Both work.
- **Drag & Drop** - Upload interface that doesn't make you cry.
- **Auto Cleanup** - Delete post? Video gets deleted too. Revolutionary? No. Functional? Yes.

## Requirements

- WordPress 6.7+
- PHP 8.3+
- FluentCommunity (must be active)
- Cloudflare Stream OR Bunny.net account

## Installation

**Download:**
- [Latest version](https://github.com/YOUR-USERNAME/fchub-stream-public/releases/latest/download/fchub-stream.zip) (always newest)
- [Specific version](https://github.com/YOUR-USERNAME/fchub-stream-public/releases) (if you're picky)

**Install:**
1. WordPress Admin → Plugins → Add New → Upload Plugin
2. Upload ZIP → Install Now → Activate
3. Done. No composer. No npm. Just works.

**Updating:**
Upload new ZIP. WordPress updates the plugin automatically. Won't create duplicates. Promise.

## Setup

### Cloudflare Stream

1. Get [Cloudflare Stream](https://www.cloudflare.com/products/cloudflare-stream/) account
2. Grab from dashboard:
   - Account ID
   - API Token (needs Stream permissions)
   - Customer Subdomain (required)
3. WordPress Admin → FluentCommunity → FCHub Stream → Cloudflare Stream tab
4. Paste credentials. Save. Works.

### Bunny.net Stream

1. Get [Bunny.net](https://bunny.net/stream/) account
2. Create Video Library
3. Grab from dashboard:
   - Library ID
   - API Key
4. WordPress Admin → FluentCommunity → FCHub Stream → Bunny.net Stream tab
5. Paste credentials. Save. Works.

### Configure Limits

WordPress Admin → FluentCommunity → FCHub Stream:

- **Stream Settings**: Pick provider (Cloudflare or Bunny)
- **Upload Settings**: File size limits, allowed formats, max duration

Set limits that make sense for your community. Default: 1GB max, MP4/MOV/AVI allowed.

## Usage

### For Members

1. FluentCommunity portal → Create post
2. Click video upload button
3. Drag & drop video
4. Wait for upload
5. Video appears in post

### For Admins

Check upload stats. Adjust limits if needed. That's it.

## Troubleshooting

**Videos won't upload:**
- Check FluentCommunity is active
- Verify provider credentials
- Check file size limits
- Check browser console for errors

**Videos stuck processing:**
- Check provider dashboard
- Large videos take time
- Provider does encoding. We wait.

**No upload button:**
- FluentCommunity active?
- Logged in to portal?
- Clear browser cache

## Documentation

Full docs: **https://docs.fchub.co/docs/fchub-stream**

API guides, troubleshooting, video limits explained.

## Support

- **Issues/Features**: [GitHub Issues](https://github.com/YOUR-USERNAME/fchub-stream-public/issues)
- **Questions**: Check [docs](https://docs.fchub.co/docs/fchub-stream) first

## Credits

Built by [Vibe Code](https://x.com/vcode_sh) because WordPress media library + video = suffering.

## License

GPL-2.0-or-later

## Changelog

### 0.0.1 (Beta)
- Initial release
- Cloudflare Stream integration
- Bunny.net Stream integration
- Direct uploads
- Auto video cleanup
- Admin config panel
- Portal upload interface

Built out of media library trauma. Streams out of necessity.
