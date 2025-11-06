# Cloudflare Stream Player - Ideas for Enhancement

Ideas based on Cloudflare Stream Player API analysis.

Reference: https://developers.cloudflare.com/stream/viewing-videos/using-the-stream-player/using-the-player-api/

---

## 🔥 Priority Features (Quick Wins)

### 1. Keyboard Shortcuts
**Why:** Users expect YouTube/Netflix behavior. Instant familiarity.

**Implementation:**
- `Space` = play/pause
- `Arrow Left/Right` = seek backward/forward 10 seconds
- `Arrow Up/Down` = volume ±10%
- `Escape` = close floating player
- `F` = fullscreen toggle

**API Used:**
- `player.play()` / `player.pause()`
- `player.currentTime += 10`
- `player.volume += 0.1`

**Effort:** ~15 minutes
**Impact:** High - expected UX behavior

---

### 2. Resume from Last Position
**Why:** Users don't want to scrub back to where they left off.

**Implementation:**
- Listen to `timeupdate` event
- Save `player.currentTime` to localStorage every 5 seconds
- On player load: `player.currentTime = savedTime`
- Key: `fchub_stream_resume_{video_id}`

**API Used:**
- Event: `timeupdate`
- Property: `player.currentTime` (read/write)

**Effort:** ~20 minutes
**Impact:** High - quality of life improvement

---

### 3. Double-Click Floating = Exit
**Why:** Quick way to return to original video position. Intuitive gesture.

**Implementation:**
- Listen for `dblclick` on floating wrapper
- Scroll back to placeholder position
- Close floating player
- Resume playback in original position

**API Used:**
- Standard DOM events
- `scrollIntoView()` on placeholder

**Effort:** ~10 minutes
**Impact:** Medium - nice UX polish

---

## 💡 Future Enhancements

### 4. Mini Progress Bar on Floating Player
**Why:** Shows how much video remains. Subtle visual feedback.

**Implementation:**
- 2px height bar at bottom of floating player
- Updates via `timeupdate` event
- Width: `(currentTime / duration) * 100%`
- Color: accent color from theme

**API Used:**
- Event: `timeupdate`
- Properties: `player.currentTime`, `player.duration`

**Effort:** ~30 minutes
**Impact:** Medium - visual polish

---

### 5. Volume Control on Floating Player
**Why:** Quick volume adjustment without opening controls. YouTube PIP style.

**Implementation:**
- Mini vertical slider in bottom-left corner
- Shows on hover over floating player
- Controls `player.volume` (0.0 - 1.0)
- Mute button for `player.muted`

**API Used:**
- Property: `player.volume` (read/write)
- Property: `player.muted` (read/write)
- Event: `volumechange`

**Effort:** ~45 minutes
**Impact:** Medium - convenience feature

---

### 6. Smart Auto-Pause (Distance-Based)
**Why:** Save bandwidth. Current implementation pauses immediately when leaving viewport.

**Implementation:**
- Calculate distance from viewport
- Only pause when >2x viewport height away
- Allows scrolling slightly past without interruption
- Resume when scrolling back near

**API Used:**
- `player.play()` / `player.pause()`
- IntersectionObserver with rootMargin

**Effort:** ~20 minutes
**Impact:** Medium - bandwidth optimization + UX

---

### 7. Watch Time Analytics
**Why:** Track user engagement. Which videos get watched? How long?

**Implementation:**
- Track playback via `timeupdate` event
- Calculate watched percentage: `(currentTime / duration) * 100`
- Send to PostHog on intervals (every 25%, 50%, 75%, 100%)
- Event: `video_watched` with properties:
  - `video_id`
  - `watch_percentage`
  - `total_watch_time_seconds`
  - `completed` (boolean)

**API Used:**
- Event: `timeupdate`
- Properties: `player.currentTime`, `player.duration`
- Event: `ended` (for completion tracking)

**Effort:** ~30 minutes
**Impact:** High - valuable analytics data

---

## 🚫 Not Possible

### Native Picture-in-Picture API
**Why not:** Cloudflare Stream iframe is cross-origin. Browsers block `requestPictureInPicture()` calls from parent page to iframe video element.

**Alternative:** Current custom floating player provides better control and auto-floating behavior.

**Note:** Users can still click PIP icon in Cloudflare player controls for native browser PIP.

---

## Implementation Priority

1. **Keyboard shortcuts** - Most expected, easiest to implement
2. **Resume from last position** - High value, simple implementation
3. **Double-click exit** - Quick polish
4. **Watch time analytics** - Data-driven decisions
5. **Smart auto-pause** - Bandwidth optimization
6. **Mini progress bar** - Visual polish
7. **Volume control** - Advanced feature

---

## Notes

- All features use postMessage API to communicate with Cloudflare Stream iframe
- Player API reference: https://developers.cloudflare.com/stream/viewing-videos/using-the-stream-player/using-the-player-api/
- Current floating player implementation: `portal-app/src/composables/useFloatingPlayer.js`
