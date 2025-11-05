# Bug Fix: Video "Not Found" 404 After Encoding Screen

## Problem Description

Users (both uploader and viewers) experienced "Video not found" errors after upload:
1. ✅ Video uploads successfully
2. ✅ Encoding screen displays with spinner
3. ❌ After 30-60 seconds: Cloudflare iframe shows 404 errors
4. ✅ After manual page refresh: Video works perfectly

**Console errors:**
```
GET https://customer-xxx.cloudflarestream.com/{VIDEO_ID}/metadata/playerEnhancementInfo.json 404
GET https://customer-xxx.cloudflarestream.com/{VIDEO_ID}/manifest/video.mpd 404
```

## Root Cause Analysis

### Critical Discovery from Cloudflare Documentation

From [Cloudflare Stream Webhooks Docs](https://developers.cloudflare.com/stream/manage-video-library/using-webhooks):

> When a video is done processing and all quality levels are encoded, the `state` field returns a `ready` state. **If higher quality renditions are still processing, videos may sometimes return the `state` field as `ready` and an additional `pctComplete` state that is not `100`.** When `pctComplete` reaches `100`, all quality resolutions are available for the video.
>
> **When at least one quality level is encoded and ready to be streamed, the `readyToStream` value returns `true`.**

### The Issue

**Cloudflare's behavior:**
- `readyToStream: true` = ONE quality level ready (e.g., 360p) ✅
- `status.state: 'ready'` + `pctComplete: 39` = NOT all quality levels ready ⚠️
- `pctComplete: 100` = ALL quality levels ready, manifest fully available ✅

**Our code was:**
1. ❌ Webhook received with `readyToStream: true, pctComplete: 39`
2. ❌ We checked ONLY `readyToStream` and `playback.hls` existence
3. ❌ Immediately updated database to `status: 'ready'`
4. ❌ Frontend polling got `ready` status and rendered iframe
5. ❌ Cloudflare manifest NOT available yet (pctComplete < 100)
6. ❌ Iframe shows 404 errors

### Additional Issues Fixed

1. **Status polling checked API instead of database**
   - Webhook updates database
   - Polling called Cloudflare API (rate limits, delays)
   - Users saw inconsistent status

2. **Frontend didn't send video status to backend**
   - Backend couldn't render proper encoding overlay
   - No `customer_subdomain` passed for thumbnail

## Solution

### 1. Backend: Check `pctComplete >= 100` in webhook

**File:** `app/Http/Controllers/VideoUploadController.php`

```php
// BEFORE
if ( $ready_to_stream ) {
    $has_playback = isset( $data['playback']['hls'] ) && ! empty( $data['playback']['hls'] );
    if ( $has_playback ) {
        $this->update_video_status_in_db( $video_uid, 'ready', $video_uid );
    }
}

// AFTER
if ( $ready_to_stream ) {
    $has_playback = isset( $data['playback']['hls'] ) && ! empty( $data['playback']['hls'] );
    $pct_complete = floatval( $data['status']['pctComplete'] ?? 0 );
    
    // Only mark as ready when pctComplete reaches 100 (all quality levels encoded)
    if ( $has_playback && $pct_complete >= 100 ) {
        $this->update_video_status_in_db( $video_uid, 'ready', $video_uid );
    } else {
        // Log warning - video not fully encoded yet
        // Cloudflare will send another webhook when pctComplete reaches 100
    }
}
```

### 2. Backend: Check database FIRST in status polling

**File:** `app/Http/Controllers/VideoUploadController.php`

Added `get_video_status_from_db()` method that checks database before calling API.

```php
// BEFORE
public function check_status( WP_REST_Request $request ) {
    // Directly call API
    $result = VideoUploadService::get_video_status( $video_id, $provider );
}

// AFTER
public function check_status( WP_REST_Request $request ) {
    // Check database FIRST (updated by webhook)
    $db_status = $this->get_video_status_from_db( $video_id, $provider );
    
    // If found in DB with 'ready' status, return immediately (no API call)
    if ( $db_status && 'ready' === $db_status['status'] ) {
        return new WP_REST_Response( ['success' => true, 'data' => $db_status], 200 );
    }
    
    // Only call API if not in DB or status is pending
    $result = VideoUploadService::get_video_status( $video_id, $provider );
}
```

### 3. Backend: Check `pctComplete` in video status responses

**File:** `app/Services/VideoUploadService.php`

```php
// format_cloudflare_response()
$pct_complete = floatval( $result['status']['pctComplete'] ?? 0 );
$actual_ready = $ready && isset( $result['playback']['hls'] ) && ! empty( $result['playback']['hls'] ) && $pct_complete >= 100;
```

### 4. Frontend: Send video status and customer_subdomain

**File:** `portal-app/src/main.js`, `portal-app/src/components/VideoUploadDialog.vue`

```javascript
// BEFORE
body.media = {
  html: pendingVideoData.shortcode,
  video_id: pendingVideoData.video_id
}

// AFTER
body.media = {
  html: pendingVideoData.shortcode,
  video_id: pendingVideoData.video_id,
  status: pendingVideoData.status || 'pending',
  customer_subdomain: pendingVideoData.customer_subdomain || ''
}
```

### 5. Frontend: Remove artificial delay

**File:** `portal-app/src/main.js`

```javascript
// BEFORE - waited 5 seconds after first 'ready' status
if (!element.dataset.lastReadyCheck) {
  element.dataset.lastReadyCheck = now.toString()
  return false  // Continue polling
}
if (timeSinceReady < 5000) {
  return false  // Wait 5 seconds
}

// AFTER - render immediately when backend confirms pctComplete=100
element.outerHTML = playerHtml  // Immediate render
```

### 6. Backend: Use status from frontend in ShortcodeProcessor

**File:** `app/Hooks/PortalIntegration/ShortcodeProcessor.php`

```php
// Read status and customer_subdomain from frontend request
$status = $request_data['media']['status'] ?? 'pending';
$customer_subdomain = $request_data['media']['customer_subdomain'] ?? '';

// Pass to renderer (ensures encoding overlay when pending)
$player_html = $this->player_renderer->get_player_html( $video_id, $provider, $status, $customer_subdomain );
```

## Expected Behavior After Fix

1. **Upload:** Video uploads, backend returns `status: 'pending', customer_subdomain: 'customer-xxx'`
2. **Display:** Encoding overlay shows with thumbnail and spinner
3. **Webhook 1:** Cloudflare sends `readyToStream: true, pctComplete: 39` → **Ignored** (< 100%)
4. **Webhook 2:** Cloudflare sends `readyToStream: true, pctComplete: 100` → **Accepted**, database updated to `ready`
5. **Polling:** Frontend polls `/video-status` → backend checks database → returns `ready` with iframe HTML
6. **Render:** Frontend immediately replaces encoding overlay with iframe
7. **Playback:** Iframe loads manifest successfully (pctComplete=100 guarantees availability)

## Testing

Upload a new video and verify:
- ✅ Encoding screen appears immediately
- ✅ NO 404 errors in console
- ✅ Video player appears automatically after encoding completes
- ✅ NO page refresh needed
- ✅ Other users see encoding screen → player transition automatically

## Monitoring

Check Sentry for warnings:
- "Video readyToStream=true but not fully encoded (pctComplete=X% < 100%)" - Expected during encoding
- Should disappear when pctComplete reaches 100%

## Files Changed

**Backend:**
- `app/Http/Controllers/VideoUploadController.php` (webhook + status check)
- `app/Services/VideoUploadService.php` (format_cloudflare_response)
- `app/Hooks/PortalIntegration/ShortcodeProcessor.php` (read status from frontend)
- `app/Hooks/PortalIntegration/VideoPlayerRenderer.php` (accept customer_subdomain param)

**Frontend:**
- `portal-app/src/components/VideoUploadDialog.vue` (send customer_subdomain)
- `portal-app/src/main.js` (send status/customer_subdomain, remove delay)
- `portal-app/dist/fchub-stream-portal.js` (rebuilt)

## References

- [Cloudflare Stream Webhooks Documentation](https://developers.cloudflare.com/stream/manage-video-library/using-webhooks)
- Cloudflare `pctComplete` field indicates encoding progress (0-100%)
- `readyToStream: true` means at least ONE quality level is ready
- `pctComplete: 100` means ALL quality levels are ready and manifest is accessible

