# 📋 Plan Migracji z Iframe na Video.js

## Faza 1: Dependencies & Setup (30 min)

### 1.1 Instalacja Video.js
```bash
cd portal-app
npm install --save video.js
```

**Wersja**: 8.23.4 (latest stable)
**Bundle size**: ~250KB (minified), ~80KB (gzipped)
**HLS Support**: ✅ Wbudowane (VHS/videojs-http-streaming)

### 1.2 Import w Vue
**Plik**: `portal-app/src/main.js`
```js
import 'video.js/dist/video-js.css'
import videojs from 'video.js'
```

---

## Faza 2: Backend Changes - PHP (45 min)

### 2.1 VideoPlayerRenderer.php - Dodać HLS URL do outputu

**Lokalizacja**: `app/Hooks/PortalIntegration/VideoPlayerRenderer.php`

**Zmiana**: Metoda `get_player_html()` linia 54-314

#### A. Cloudflare Stream (linia 64-287)

**Przed** (iframe):
```php
return sprintf(
  '<div class="fchub-stream-player-wrapper" data-video-id="%s" data-provider="cloudflare_stream">
    <iframe src="https://%s.cloudflarestream.com/%s/iframe?postMessage=true">
    </iframe>
  </div>',
  $video_id, $customer_subdomain, $video_id
);
```

**Po** (video.js):
```php
return sprintf(
  '<div class="fchub-stream-player-wrapper"
       data-video-id="%s"
       data-provider="cloudflare_stream"
       data-hls-url="https://%s.cloudflarestream.com/%s/manifest/video.m3u8"
       data-customer-subdomain="%s">
    <video class="video-js vjs-default-skin"
           controls
           preload="auto"
           data-setup=\'{"fluid": true, "aspectRatio": "16:9"}\'>
    </video>
  </div>',
  $video_id, $customer_subdomain, $video_id, $customer_subdomain
);
```

#### B. Bunny Stream (linia 288-307)

**Przed** (iframe):
```php
return sprintf(
  '<div class="fchub-stream-player-wrapper" data-video-id="%s" data-provider="bunny_stream">
    <iframe src="https://iframe.mediadelivery.net/embed/%s/%s">
    </iframe>
  </div>',
  $video_id, $library_id, $video_id
);
```

**Po** (video.js):
```php
// STEP 1: Get Pull Zone URL from Video Library
$bunny_api = new BunnyApiService(
  $config['account_api_key'] ?? '',
  $config['api_key'] ?? '',
  $library_id
);

$library_info = $bunny_api->get_video_library( $library_id );
$pull_zone_url = '';

if ( ! is_wp_error( $library_info ) ) {
  $pull_zone_url = $library_info['library']['videoPlaybackHostname'] ?? '';
}

// STEP 2: Build HLS URL
$hls_url = '';
if ( ! empty( $pull_zone_url ) ) {
  $hls_url = "https://{$pull_zone_url}.b-cdn.net/{$video_id}/playlist.m3u8";
}

return sprintf(
  '<div class="fchub-stream-player-wrapper"
       data-video-id="%s"
       data-provider="bunny_stream"
       data-hls-url="%s"
       data-pull-zone="%s">
    <video class="video-js vjs-default-skin"
           controls
           preload="auto"
           data-setup=\'{"fluid": true, "aspectRatio": "16:9"}\'>
    </video>
  </div>',
  $video_id, $hls_url, $pull_zone_url
);
```

**Data attributes używane przez JS**:
- `data-hls-url` - manifest URL dla Video.js
- `data-video-id` - existing (dla floating player)
- `data-customer-subdomain` / `data-pull-zone` - optional metadata

### 2.2 VideoUploadService.php - Update response format

**Lokalizacja**: `app/Services/VideoUploadService.php`

**Metody do zmiany**:
1. `format_cloudflare_response()` - linia 689-758
2. `format_bunny_response()` - linia 771-804
3. `generate_player_html()` - linia 908-949

**A. Cloudflare - Dodać HLS URL**:
```php
// W format_cloudflare_response()
$hls_url = $result['playback']['hls'] ?? '';

return array(
  // ... existing fields
  'hls_url' => $hls_url, // DODAĆ to
  'customer_subdomain' => $customer_subdomain,
);
```

**B. Bunny - Dodać Pull Zone URL + HLS URL**:
```php
// W format_bunny_response()
// STEP 1: Get Video Library details to extract Pull Zone URL
$bunny_api = new BunnyApiService(
  $bunny['account_api_key'] ?? '',
  $bunny['api_key'],
  $library_id
);

$library_info = $bunny_api->get_video_library( $library_id );
$pull_zone_url = '';

if ( ! is_wp_error( $library_info ) ) {
  // Extract pull zone hostname from library data
  // Field name: videoPlaybackHostname lub similar
  $pull_zone_url = $library_info['library']['videoPlaybackHostname'] ?? '';
}

// STEP 2: Build HLS manifest URL
$hls_url = '';
if ( ! empty( $pull_zone_url ) && ! empty( $video_id ) ) {
  $hls_url = "https://{$pull_zone_url}.b-cdn.net/{$video_id}/playlist.m3u8";
}

return array(
  // ... existing fields
  'hls_url' => $hls_url, // DODAĆ
  'pull_zone_url' => $pull_zone_url, // DODAĆ
);
```

---

## Faza 3: Frontend - Vue Components (90 min)

### 3.1 Nowy Composable: `useVideoPlayer.js`

**Lokalizacja**: `portal-app/src/composables/useVideoPlayer.js` (NOWY PLIK)

**Funkcjonalność**:
```js
import videojs from 'video.js'

export function useVideoPlayer() {
  // Initialize player instances Map
  const players = new Map()

  function initPlayer(videoElement, hlsUrl) {
    const player = videojs(videoElement, {
      fluid: true,
      aspectRatio: '16:9',
      controls: true,
      preload: 'auto',
      sources: [{
        src: hlsUrl,
        type: 'application/x-mpegURL'
      }],
      // Ukryj PIP button w kontrolkach
      controlBar: {
        pictureInPictureToggle: false
      }
    })

    players.set(videoElement.id, player)
    return player
  }

  function destroyPlayer(playerId) {
    const player = players.get(playerId)
    if (player) {
      player.dispose()
      players.delete(playerId)
    }
  }

  return {
    initPlayer,
    destroyPlayer,
    players
  }
}
```

### 3.2 Update VideoPlayer.vue Component

**Lokalizacja**: `portal-app/src/components/VideoPlayer.vue`

**Przed**: Używa `<iframe>` (linia 8-18)

**Po**:
```vue
<template>
  <div class="video-player-wrapper">
    <div v-if="loading" class="loading-overlay">
      <div class="spinner"></div>
      <span>Loading video...</span>
    </div>

    <video
      ref="videoElement"
      class="video-js vjs-default-skin"
      :id="`vjs-player-${videoId}`"
    ></video>

    <div v-if="error" class="error-overlay">
      <!-- existing error UI -->
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { useVideoPlayer } from '../composables/useVideoPlayer'

const props = defineProps({
  videoId: String,
  playerUrl: String, // deprecated
  hlsUrl: { type: String, required: true } // NOWY
})

const { initPlayer, destroyPlayer } = useVideoPlayer()
const videoElement = ref(null)
const loading = ref(true)
const error = ref(false)
let player = null

onMounted(() => {
  try {
    player = initPlayer(videoElement.value, props.hlsUrl)

    player.ready(() => {
      loading.value = false
    })

    player.on('error', () => {
      loading.value = false
      error.value = true
    })
  } catch (e) {
    loading.value = false
    error.value = true
  }
})

onBeforeUnmount(() => {
  if (player) {
    destroyPlayer(player.id())
  }
})
</script>
```

### 3.3 Update useFloatingPlayer.js

**Lokalizacja**: `portal-app/src/composables/useFloatingPlayer.js`

**Zmiany**:

1. **Linia 469-470** - Change selector:
```js
// PRZED
function setupVideoObserver(wrapper) {
  const iframe = wrapper.querySelector('iframe')

// PO
function setupVideoObserver(wrapper) {
  const video = wrapper.querySelector('video.video-js')
```

2. **Linia 291-376** - Update showFloatingPlayer():
```js
// Pause OTHER videos using Video.js API instead of iframe reload
allWrappers.forEach(otherWrapper => {
  if (otherWrapper !== wrapper && !otherWrapper.classList.contains('fchub-stream-encoding')) {
    const otherVideo = otherWrapper.querySelector('video.video-js')
    if (otherVideo && videojs.getPlayer(otherVideo.id)) {
      const player = videojs.getPlayer(otherVideo.id)
      player.pause()
    }
  }
})
```

3. **Linia 381-405** - Update closeFloatingPlayer():
```js
// Stop video using Video.js API
if (stopVideo) {
  const video = wrapper.querySelector('video.video-js')
  if (video && videojs.getPlayer(video.id)) {
    const player = videojs.getPlayer(video.id)
    player.pause()
    player.currentTime(0)
  }
}
```

### 3.4 Update main.js - Initialize players on page load AND after encoding

**Lokalizacja**: `portal-app/src/main.js`

**KRYTYCZNE**: Video.js wymaga inicjalizacji po każdej podmiane HTML (encoding→ready)!

**A. Dodać helper function** na początku:
```js
import { useVideoPlayer } from './composables/useVideoPlayer'

function initFCHubStreamPortal() {
  // ... existing code

  // Initialize Video.js players
  const { initPlayer } = useVideoPlayer()

  function initializeVideoPlayers() {
    document.querySelectorAll('.fchub-stream-player-wrapper video.video-js').forEach(videoEl => {
      if (!videoEl.id) {
        videoEl.id = `vjs-player-${Date.now()}-${Math.random()}`
      }

      const wrapper = videoEl.closest('.fchub-stream-player-wrapper')
      const hlsUrl = wrapper.dataset.hlsUrl

      if (hlsUrl && !videojs.getPlayer(videoEl.id)) {
        initPlayer(videoEl, hlsUrl)
      }
    })
  }

  // Run on load
  initializeVideoPlayers()

  // Watch for new videos (infinite scroll)
  const observer = new MutationObserver(initializeVideoPlayers)
  observer.observe(document.body, { childList: true, subtree: true })
}
```

**B. Update encoding→ready flow** (linia ~816):

**PRZED**:
```js
// Replace the entire encoding wrapper with ready player
element.outerHTML = playerHtml

// Remove from polling map
pollingElements.delete(element)
```

**PO**:
```js
// Replace the entire encoding wrapper with ready player
const videoId = element.dataset.videoId
element.outerHTML = playerHtml

// Remove from polling map
pollingElements.delete(element)

// CRITICAL: Initialize Video.js after DOM update
requestAnimationFrame(() => {
  const newWrapper = document.querySelector(`[data-video-id="${videoId}"]`)
  if (newWrapper) {
    const videoEl = newWrapper.querySelector('video.video-js')
    if (videoEl) {
      const hlsUrl = newWrapper.dataset.hlsUrl
      if (hlsUrl && !videojs.getPlayer(videoEl.id)) {
        initPlayer(videoEl, hlsUrl)
        console.log('[FCHub Stream] Video.js player initialized after encoding→ready for:', videoId)
      }
    }
  }
})
```

---

## Faza 4: CSS Styling (30 min)

### 4.1 Update video-player.css

**Lokalizacja**: `portal-app/src/assets/video-player.css`

**Dodać**:
```css
/* Video.js wrapper styles */
.fchub-stream-player-wrapper {
  position: relative;
  width: 100%;
  /* aspect-ratio handled by Video.js fluid mode */
}

.fchub-stream-player-wrapper .video-js {
  width: 100%;
  height: 100%;
}

/* Hide Video.js PIP button (we use custom floating player) */
.fchub-stream-player-wrapper .vjs-picture-in-picture-control {
  display: none !important;
}

/* Video.js in floating mode */
.fchub-stream-player-wrapper.fchub-stream-floating-mode .video-js {
  width: 100% !important;
  height: 100% !important;
}
```

---

## Faza 5: Migration Strategy (60 min)

### 5.1 Feature Flag Approach

**Dodać** do `portal-app/src/utils/constants.js`:
```js
export const USE_VIDEO_JS = true // Toggle between iframe/video.js
```

**Update** VideoPlayerRenderer.php:
```php
$use_videojs = apply_filters('fchub_stream_use_videojs', true);

if ($use_videojs) {
  // Return Video.js HTML
} else {
  // Return iframe HTML (fallback)
}
```

### 5.2 Testing Checklist

✅ Video playback on desktop
✅ Video playback on mobile
✅ HLS streaming works (check Network tab)
✅ Floating player integration
✅ Auto-pause other videos
✅ Controls visibility
✅ Fullscreen mode
✅ Volume control
✅ Keyboard shortcuts (space, arrows)
✅ Multiple videos on same page
✅ Infinite scroll new videos

---

## Podsumowanie Zmian

### Dependencies Dodane:
```json
{
  "dependencies": {
    "video.js": "^8.23.4"
  }
}
```

### Pliki Zmodyfikowane:

**Backend (PHP)**:
1. `app/Hooks/PortalIntegration/VideoPlayerRenderer.php` - Change iframe → video.js
2. `app/Services/VideoUploadService.php` - Add hls_url to response

**Frontend (Vue/JS)**:
3. `portal-app/package.json` - Add video.js dependency
4. `portal-app/src/main.js` - Import video.js, initialize players
5. `portal-app/src/composables/useVideoPlayer.js` - **NOWY** - Video.js wrapper
6. `portal-app/src/composables/useFloatingPlayer.js` - Update selectors (iframe → video)
7. `portal-app/src/components/VideoPlayer.vue` - Replace iframe with video.js
8. `portal-app/src/assets/video-player.css` - Add Video.js styles

### Pliki Nowe:
- `portal-app/src/composables/useVideoPlayer.js`

---

## Szacowany Czas: ~4.5 godziny

- Faza 1: 30 min (Dependencies & Setup)
- Faza 2: 45 min (Backend PHP)
- Faza 3: 90 min (Frontend Vue)
- Faza 4: 30 min (CSS)
- Faza 5: 60 min (Migration Strategy)
- **Buffer**: 30 min

**Total**: ~5h z bufferem

---

## Korzyści:

✅ **Brak przycisku PIP Cloudflare** - ukryty poprzez `pictureInPictureToggle: false`
✅ **Custom Floating Player on Scroll** - działa automatycznie (native PIP NIE może auto-trigger)
✅ **Drag & Drop + Resize** - pełna kontrola pozycji i rozmiaru
✅ **Persistent Position** - localStorage zachowuje preferencje user
✅ **Lżejszy** - direct `<video>` tag vs iframe overhead
✅ **Lepsze API** - Video.js eventy, metody, pluginy
✅ **Customization** - pełna kontrola nad kontrolkami i stylem

## Ryzyka:

⚠️ **Browser compatibility** - Safari/iOS native HLS, inne przez VHS
⚠️ **Bundle size** - +250KB (80KB gzipped)
⚠️ **Testing effort** - więcej edge cases
⚠️ **Migration** - istniejące posty trzeba zaktualizować (lub dual-mode)

---

## Dlaczego Video.js?

### Problem z Iframe:
- ❌ Cross-origin security - brak dostępu do `<video>` elementu wewnątrz iframe
- ❌ Nie można programowo wywołać PIP: `iframe.contentWindow.document.querySelector('video').requestPictureInPicture()` = Security Error
- ❌ Brak kontroli nad przyciskami - Cloudflare nie oferuje parametru do ukrycia PIP
- ❌ Cloudflare Stream API nie ma metody postMessage do triggerowania PIP

### Rozwiązanie Video.js:
- ✅ Direct access do `<video>` elementu - pełna kontrola
- ✅ Możemy ukryć PIP button: `controlBar: { pictureInPictureToggle: false }`
- ✅ HLS manifest dostępny: `https://customer-xxx.cloudflarestream.com/VIDEO_ID/manifest/video.m3u8`
- ✅ Wbudowane HLS support (VHS) od v7+
- ✅ Bogate API: eventy, metody, pluginy
- ✅ Works z custom floating player (useFloatingPlayer.js)

---

## ✅ Rekomendowana Implementacja: Custom Floating ZAMIAST Native PIP

### Dlaczego ukrywamy PIP button i używamy tylko custom floating?

#### Problem z Native PIP API:
```
❌ NIEMOŻLIWE auto-trigger on scroll
```

**Przeglądarki wymagają "user-trusted event"** dla `requestPictureInPicture()`:
- ✅ Działa: `click`, `tap`, `keypress` (user initiated)
- ❌ NIE działa: `scroll`, `timer`, `automated trigger`

**Security Error**:
```
NotAllowedError: Must be handling a user gesture
if there isn't already an element in Picture-in-Picture
```

**Źródło**: W3C Picture-in-Picture API specification + Chrome/Safari security policy

### Problem dotyczy OBYDWU providerów:

#### Cloudflare Stream:
- ❌ Iframe URL: `https://customer-xxx.cloudflarestream.com/VIDEO_ID/iframe`
- ❌ Brak parametru do ukrycia PIP button
- ✅ HLS manifest: `https://customer-xxx.cloudflarestream.com/VIDEO_ID/manifest/video.m3u8`

#### Bunny Stream:
- ❌ Iframe URL: `https://iframe.mediadelivery.net/embed/LIBRARY_ID/VIDEO_ID`
- ❌ Brak parametru do ukrycia PIP button
- ✅ HLS manifest: `https://PULL_ZONE_URL.b-cdn.net/VIDEO_ID/playlist.m3u8`
- ⚠️ **Wymaga**: Pull Zone URL (CDN hostname) z Video Library API

#### Porównanie Funkcjonalności:

| Feature | Native PIP (Video.js button) | Custom Floating (useFloatingPlayer) |
|---------|------------------------------|--------------------------------------|
| **Auto-trigger on scroll** | ❌ NIEMOŻLIWE (security) | ✅ **DZIAŁA** |
| User gesture required | ✅ Tak (manual click) | ❌ Nie (automatic) |
| **Drag & drop** | ❌ Nie (OS controls) | ✅ **TAK** |
| **Resize** | ❌ Nie (OS controls) | ✅ **TAK** |
| **Persistent position** | ❌ Nie | ✅ **TAK** (localStorage) |
| **Mobile support** | ⚠️ Limited (iOS Safari) | ✅ **Wszędzie** |
| Integration z feed | ❌ Separate window | ✅ **Same page** |

#### Nasza Implementacja:

**Config Video.js** (Faza 3.1):
```js
controlBar: {
  pictureInPictureToggle: false  // ← Ukryj native PIP button
}
```

**Custom Floating Player** (useFloatingPlayer.js):
- ✅ Auto-trigger when video scrolls out of viewport
- ✅ Works after 3+ seconds viewing (user intent detection)
- ✅ Drag to reposition
- ✅ Resize with corner handles
- ✅ Position saved in localStorage
- ✅ Close button to dismiss
- ✅ Returns to original position on scroll back

#### UX Flow:

1. **User scrolls feed** → Video playing
2. **Video leaves viewport** → Auto floating (after 3s threshold)
3. **User continues scrolling** → Floating player follows
4. **User drags/resizes** → Custom position saved
5. **User scrolls back** → Returns to inline position

**Konkluzja**: Custom floating player daje **lepszy UX** niż native PIP + działa on scroll.

---

## Alternatywy Rozważane:

### 1. CSS do ukrycia PIP buttona
❌ Cross-origin iframe blokuje dostęp do styli wewnątrz

### 2. Parametr URL `controls=false`
❌ Ukrywa WSZYSTKIE kontrolki, trzeba robić custom UI od zera

### 3. Cloudflare parametr do ukrycia PIP
❌ Nie istnieje w dokumentacji

### 4. Native PIP API z iframe
❌ Cross-origin security blokuje

### 5. Zaakceptować przycisk PIP
✅ Opcja jeśli nie chcemy refactoru - users mają wybór między custom floating a native PIP

---

## Wpływ na Webhook i Encoding Flow

### Obecny Flow (iframe) - działa idealnie:

1. **Upload** → Backend zwraca encoding overlay HTML:
```html
<div class="fchub-stream-encoding" data-video-id="...">
  <img src="thumbnail.jpg" />
  <div>Encoding video...</div>
</div>
```

2. **Webhook** → Cloudflare notyfikuje backend o `status: ready`

3. **Polling** → Frontend (`useVideoStatus.js`) odpytuje backend co X sekund

4. **Backend response** → Zwraca `{ readyToStream: true, html: "<iframe...>" }`

5. **Frontend podmiana** (`main.js:816`):
```js
element.outerHTML = playerHtml  // Encoding → Iframe (działa natychmiast)
```

6. **Database update** (`main.js:801`) → Zapisuje iframe HTML do bazy

### Nowy Flow (Video.js) - wymaga dodatkowej inicjalizacji:

1. **Upload** → Backend zwraca encoding overlay (bez zmian)

2. **Webhook** → Backend notyfikacja (bez zmian)

3. **Polling** → Frontend polling (bez zmian)

4. **Backend response** → Zwraca `{ readyToStream: true, html: "<video class='video-js'>..." }`

5. **Frontend podmiana**:
```js
const videoId = element.dataset.videoId
element.outerHTML = playerHtml  // Encoding → <video> tag

// ⚠️ PROBLEM: <video> tag bez inicjalizacji NIE zadziała!
```

6. **Frontend init Video.js** (NOWE):
```js
requestAnimationFrame(() => {
  const newWrapper = document.querySelector(`[data-video-id="${videoId}"]`)
  const videoEl = newWrapper.querySelector('video.video-js')
  const hlsUrl = newWrapper.dataset.hlsUrl

  initPlayer(videoEl, hlsUrl)  // ✅ Teraz działa!
})
```

7. **Database update** → Zapisuje `<video>` HTML do bazy

### Kluczowe różnice:

| Aspekt | Iframe (teraz) | Video.js (po migracji) |
|--------|----------------|------------------------|
| Podmiana HTML | `element.outerHTML = iframe` ✅ | `element.outerHTML = video` ✅ |
| Działa natychmiast? | ✅ TAK | ❌ NIE - wymaga init |
| Wymaga JS init? | ❌ NIE | ✅ TAK - `videojs()` |
| Webhook flow | ✅ Działa | ✅ Działa |
| Polling flow | ✅ Działa | ✅ Działa |
| Database update | ✅ Działa | ✅ Działa |

### ✅ Podsumowanie:

**Webhook i encoding flow BĘDĄ DZIAŁAĆ**, ale wymagają **jednej dodatkowej linijki**:

```js
// Po: element.outerHTML = playerHtml
// Dodać: initPlayer(videoEl, hlsUrl)
```

Całość opisana w **Faza 3.4.B** powyżej.

---

**Gotowy do implementacji?** 🚀

---

## ⚠️ TODO: Weryfikacja Bunny.net API Response

**KRYTYCZNE** - Przed implementacją sprawdzić:

1. **Pull Zone URL field name** w GET `/videolibrary/{libraryId}` response:
   - Założenie: `videoPlaybackHostname`
   - Może być: `pullZoneUrl`, `cdnHostname`, `videoLibraryHostname`
   - **Action**: Call Bunny API lub sprawdzić docs.bunny.net/reference

2. **Testowanie HLS URL**:
   ```
   Format założony: https://{pull_zone_url}.b-cdn.net/{video_id}/playlist.m3u8
   ```
   - Zweryfikować czy działa z Video.js
   - Sprawdzić CORS headers (Bunny CDN powinno obsługiwać)

3. **Alternative**: Jeśli pull_zone_url nie jest dostępny w library response:
   - Opcja A: Zapisać w config (user podaje pull zone domain)
   - Opcja B: Wyciągnąć z pierwszego uploaded video response (może zawierać full URL)
   - Opcja C: Zbudować z library_id (pattern do odkrycia)

**Rekomendacja**: Przed implementacją PHP zrobić quick test Bunny API call.
