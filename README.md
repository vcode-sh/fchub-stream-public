# FCHub Stream

Direct video upload plugin for FluentCommunity. Enables users to upload videos directly to your community feed instead of only sharing YouTube links. Powered by Cloudflare Stream and Bunny.net Stream.

## Features

- **Direct Video Uploads** - Users can upload videos directly from their devices
- **Multiple Providers** - Choose between Cloudflare Stream or Bunny.net Stream
- **Real-time Progress** - Visual upload progress with speed and time estimates
- **Drag & Drop** - Intuitive drag-and-drop interface
- **Video Management** - Automatic cleanup when posts/comments are deleted
- **Flexible Configuration** - Control file size limits, formats, and encoding settings

## Requirements

- **WordPress**: 6.7 or higher
- **PHP**: 8.3 or higher
- **FluentCommunity**: Must be installed and active
- **Video Provider**: Active account with Cloudflare Stream or Bunny.net Stream

## Installation

### Step 1: Install the Plugin

1. Download the latest release ZIP file from the [Releases page](https://github.com/YOUR-USERNAME/fchub-stream-public/releases)
2. Go to WordPress Admin → Plugins → Add New → Upload Plugin
3. Upload the ZIP file and click "Install Now"
4. Click "Activate Plugin"

### Step 2: Install Dependencies

The plugin requires PHP dependencies installed via Composer:

```bash
cd wp-content/plugins/fchub-stream
composer install --no-dev
```

If you don't have Composer installed, [follow these instructions](https://getcomposer.org/doc/00-intro.md).

### Step 3: Configure Video Provider

#### Option A: Cloudflare Stream

1. Create a [Cloudflare Stream](https://www.cloudflare.com/products/cloudflare-stream/) account
2. Get your credentials from Cloudflare dashboard:
   - Account ID
   - API Token (with Stream permissions)
   - Customer Subdomain (optional, for custom branding)
3. Go to WordPress Admin → FluentCommunity → FCHub Stream
4. Navigate to "Cloudflare Stream" tab
5. Enter your credentials and save

#### Option B: Bunny.net Stream

1. Create a [Bunny.net](https://bunny.net/stream/) account
2. Create a Video Library in Bunny dashboard
3. Get your credentials:
   - Library ID
   - API Key
4. Go to WordPress Admin → FluentCommunity → FCHub Stream
5. Navigate to "Bunny.net Stream" tab
6. Enter your credentials and save

### Step 4: Configure Settings

Go to WordPress Admin → FluentCommunity → FCHub Stream:

**Stream Settings:**
- Choose your active provider (Cloudflare or Bunny.net)
- Configure auto-publish settings
- Set status polling interval

**Upload Settings:**
- Set maximum file size (1-10000 MB)
- Configure allowed video formats
- Set maximum video duration

## Usage

### For Community Members

1. Go to your FluentCommunity portal
2. Create a new post or comment
3. Click the video upload button (camera icon)
4. Drag & drop a video or click to browse
5. Wait for upload to complete
6. Your video will appear in the post preview

### For Administrators

**Monitor Uploads:**
- Check upload success rates
- Review encoding status
- Manage video storage

**Configure Limits:**
- Adjust file size limits based on your needs
- Enable/disable specific video formats
- Set encoding quality preferences

## Optional: Error Monitoring with Sentry

FCHub Stream includes optional Sentry integration for error monitoring and tracking.

1. Create a [Sentry](https://sentry.io) account
2. Create a new project
3. Copy your DSN
4. Go to WordPress Admin → FluentCommunity → FCHub Stream → Sentry
5. Enable Sentry and paste your DSN
6. Click "Test Connection" to verify

Sentry will automatically capture:
- Upload errors
- API failures
- Encoding issues
- Integration problems

## Troubleshooting

### Videos won't upload

1. Check that FluentCommunity is active
2. Verify provider credentials in admin settings
3. Check upload file size limits
4. Ensure your hosting supports large file uploads
5. Check browser console for JavaScript errors

### Videos stuck in "processing"

1. Check your video provider dashboard for encoding status
2. Verify webhook configuration (if using webhooks)
3. Large videos may take longer to encode

### Can't see video upload button

1. Ensure FluentCommunity is active
2. Clear browser cache
3. Check that you're logged in to the portal
4. Verify portal integration is enabled

## Support

For issues, questions, or feature requests:

- **GitHub Issues**: [Report a bug or request a feature](https://github.com/YOUR-USERNAME/fchub-stream-public/issues)
- **Documentation**: Check the [wiki](https://github.com/YOUR-USERNAME/fchub-stream-public/wiki)

## Credits

Developed by [Vibe Code](https://x.com/vcode_sh)

## License

GPL-2.0-or-later - see [LICENSE](LICENSE) file for details.

## Changelog

### 0.0.1 (Beta)
- Initial beta release
- Cloudflare Stream integration
- Bunny.net Stream integration
- Portal video upload interface
- Admin configuration panel
- Sentry error monitoring
- Automatic video cleanup on post/comment deletion
