# Camera UI Redesign: Modern Native-Feel Photo Capture

**Date Added:** 2026-03-03
**Feature:** Redesigned camera interface with fullscreen mobile experience, circular shutter button, camera flip, and capture feedback animations

---

## Overview

The Patient Documentation and Endorsement Attachment camera interfaces have been completely redesigned to provide:

1. **Modern corporate desktop experience** - Dark-themed camera card with professional top/bottom control bars
2. **Fullscreen mobile experience** - Camera opens as a fullscreen overlay on mobile devices (like native camera apps)
3. **Camera flip** - Toggle between front and back cameras
4. **Capture feedback** - White flash overlay and shutter button animation on photo capture
5. **Smooth animations** - Slide-up opening and slide-down closing on mobile with CSS transitions

All existing functionality is preserved: GPS metadata stamping, file upload, image preview, modal zoom, and error handling.

---

## Before & After

### Before (Old Design)
- Inline camera viewport constrained to 280-350px height
- Simple green "Capture" pill button + gray "Close" pill button
- White background with light border
- Same cramped layout on both desktop and mobile
- No camera flip capability

### After (New Design)
- **Desktop:** Dark-themed inline camera card with top bar (close + label) and bottom bar (shutter + flip)
- **Mobile:** Fullscreen fixed overlay covering entire screen with slide-up animation
- Large circular shutter button (64px desktop / 72px mobile) mimicking native camera apps
- Camera flip button to toggle front/back camera
- Flash feedback on capture + shutter pulse animation
- Safe area support for notched phones (iPhone, etc.)

---

## Files Modified

### Frontend

| File | Changes |
|------|---------|
| `public/css/prehospital-form.css` | Replaced camera container styles (lines 3090-3160) with new dark-themed UI, added mobile fullscreen overlay at 768px breakpoint, removed old `max-height: 280px` from 768px media query |
| `public/js/prehospital-form.js` | Added `isMobileDevice()` helper, facing mode state variables, camera flip functions, flash feedback, mobile fullscreen open/close logic, extended Escape key handler |
| `public/prehospital_form.php` | Replaced camera HTML at 2 locations (patient camera + endorsement camera) with new top-bar/viewport/bottom-bar structure |
| `public/edit_record.php` | Replaced camera HTML at 2 locations (patient camera + endorsement camera) with same new structure |

### No Backend Changes

This feature is purely frontend. No API, database, or PHP logic changes were required.

---

## Technical Implementation

### HTML Structure (New Camera Container)

The old camera container had a simple structure:
```
camera-container > camera-viewport > video
                 > camera-controls > capture-btn + close-btn
```

The new structure is:
```
camera-container > camera-top-bar > close-btn + label + spacer
                 > camera-viewport > video + flash-overlay
                 > camera-bottom-bar > spacer + shutter-btn + flip-btn
```

#### Patient Camera Example

```html
<div id="patientCameraContainer" class="patient-camera-container">
    <!-- Top bar with close button and label -->
    <div class="camera-top-bar">
        <button type="button" class="camera-close-btn" onclick="closePatientCamera()">
            <i class="bi bi-x-lg"></i>
        </button>
        <span class="camera-top-label">Patient Photo</span>
        <div class="camera-top-spacer"></div>
    </div>

    <!-- Camera viewfinder with flash overlay -->
    <div class="camera-viewport">
        <video id="patientCameraVideo" autoplay playsinline></video>
        <div class="camera-flash-overlay" id="patientFlashOverlay"></div>
    </div>

    <!-- Bottom controls: shutter + flip -->
    <div class="camera-bottom-bar">
        <div class="camera-bottom-spacer"></div>
        <button type="button" class="camera-shutter-btn" id="capturePatientBtn"
                onclick="capturePatientPhoto()">
            <span class="shutter-ring"><span class="shutter-inner"></span></span>
        </button>
        <button type="button" class="camera-flip-btn" id="flipPatientCameraBtn"
                onclick="flipPatientCamera()">
            <i class="bi bi-arrow-repeat"></i>
        </button>
    </div>
</div>
```

#### Endorsement Camera

Same structure with different IDs and labels:
- Container: `id="cameraContainer"`, `class="endorsement-camera-container"`
- Video: `id="cameraVideo"`
- Flash: `id="endorsementFlashOverlay"`
- Shutter: `id="captureBtn"`, `onclick="capturePhoto()"`
- Flip: `id="flipEndorsementCameraBtn"`, `onclick="flipEndorsementCamera()"`
- Label: "Endorsement Document"

### Four HTML Locations Updated

| File | Section | Container ID |
|------|---------|-------------|
| `prehospital_form.php` ~line 1203 | Patient Documentation | `patientCameraContainer` |
| `prehospital_form.php` ~line 1983 | Endorsement Attachment | `cameraContainer` |
| `edit_record.php` ~line 1147 | Patient Documentation | `patientCameraContainer` |
| `edit_record.php` ~line 2010 | Endorsement Attachment | `cameraContainer` |

---

## CSS Architecture

### New CSS Classes

| Class | Purpose |
|-------|---------|
| `.camera-top-bar` | Dark gradient header bar with close button and label |
| `.camera-top-label` | White text label ("Patient Photo" / "Endorsement Document") |
| `.camera-top-spacer` / `.camera-bottom-spacer` | 44px spacers for balanced flexbox layout |
| `.camera-close-btn` | 36px circular close button with semi-transparent white background |
| `.camera-bottom-bar` | Dark gradient footer bar with shutter and flip controls |
| `.camera-shutter-btn` | Transparent button wrapper for the shutter circle |
| `.shutter-ring` | 64px white-bordered circle (outer ring) |
| `.shutter-inner` | 52px white-filled circle (inner button) |
| `.camera-flip-btn` | 44px circular camera flip button |
| `.camera-flash-overlay` | Absolute-positioned white overlay for flash effect |
| `.camera-active` | Class added on mobile to trigger slide-up animation |
| `.flash-active` | Triggers flash opacity to 0.85 |
| `.capturing` | Triggers shutter pulse animation |
| `.flipping` | Triggers flip button rotation animation |

### Removed/Replaced CSS Classes

| Old Class | Status |
|-----------|--------|
| `.camera-controls` | **Replaced** by `.camera-top-bar` + `.camera-bottom-bar` |
| `.camera-btn` | **Replaced** by `.camera-shutter-btn` + `.camera-close-btn` |
| `.camera-btn.capture` | **Replaced** by `.camera-shutter-btn` |
| `.camera-btn.close-cam` | **Replaced** by `.camera-close-btn` |

### Color Palette

| Element | Value | Description |
|---------|-------|-------------|
| Top/Bottom bars | `linear-gradient(135deg, #1a1a2e, #16213e)` | Deep navy gradient |
| Camera viewport | `#000` | Pure black background |
| Shutter button | `#ffffff` | White ring and inner circle |
| Close/Flip buttons | `rgba(255,255,255,0.12)` | Semi-transparent white |
| Close/Flip hover | `rgba(255,255,255,0.25)` | Brighter on hover |
| Flash overlay | `#ffffff` at `opacity: 0.85` | Bright white flash |
| Top label text | `rgba(255,255,255,0.9)` | Near-white text |

### Animations

| Animation | Duration | Easing | Description |
|-----------|----------|--------|-------------|
| Flash overlay | 0.08s | ease-out | `opacity: 0 -> 0.85 -> 0` |
| Shutter pulse | 0.3s | ease | `scale(1) -> scale(0.85) -> scale(1)` |
| Shutter press | 0.15s | ease | `scale(0.92)` on `:active` |
| Flip rotation | 0.4s | ease | `rotate(0deg) -> rotate(180deg)` |
| Mobile slide-up | 0.35s | cubic-bezier(0.4,0,0.2,1) | `translateY(100%) -> translateY(0)` |
| Mobile slide-down | 0.35s | (reverse of above) | `translateY(0) -> translateY(100%)` |

### Mobile Fullscreen Override (max-width: 768px)

On screens 768px or narrower, the camera container transforms into a fullscreen overlay:

```css
.patient-camera-container,
.endorsement-camera-container {
    position: fixed !important;
    top: 0; left: 0; right: 0; bottom: 0;
    width: 100%; height: 100%;
    margin: 0; border: none; border-radius: 0;
    z-index: 99999;
    flex-direction: column;
    transform: translateY(100%);        /* Start off-screen */
    transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1);
}

.camera-active {
    display: flex !important;
    flex-direction: column;
    transform: translateY(0);           /* Slide into view */
}
```

Key mobile enhancements:
- **Safe area insets**: `padding-top: max(0.5rem, env(safe-area-inset-top))` for notched phones
- **Video fills screen**: `flex: 1` on viewport, `max-height: none; height: 100%` on video
- **Larger controls**: Shutter 72px (vs 64px desktop), flip 48px (vs 44px), close 40px (vs 36px)
- **Body scroll lock**: `document.body.style.overflow = 'hidden'` when camera is open

---

## JavaScript Architecture

### New Functions

| Function | Purpose |
|----------|---------|
| `isMobileDevice()` | Returns `true` if `window.innerWidth <= 768` |
| `flipPatientCamera()` | Toggles patient camera between front/back facing |
| `flipEndorsementCamera()` | Toggles endorsement camera between front/back facing |

### New State Variables

| Variable | Type | Default | Purpose |
|----------|------|---------|---------|
| `currentFacingMode` | `string` | `'environment'` | Tracks endorsement camera facing direction |
| `patientCurrentFacingMode` | `string` | `'environment'` | Tracks patient camera facing direction |

### Modified Functions

#### `openPatientCamera()` / `openCamera()`

**Changes:**
- Uses `patientCurrentFacingMode` / `currentFacingMode` instead of hardcoded `'environment'`
- On mobile (`isMobileDevice()`):
  - Sets `display: flex` (instead of `block`)
  - Locks body scroll with `overflow: hidden`
  - Forces reflow with `offsetHeight`
  - Adds `camera-active` class to trigger slide-up animation
- On desktop: unchanged behavior (`display: block`)

#### `closePatientCamera()` / `closeCamera()`

**Changes:**
- On mobile (when `camera-active` class is present):
  - Removes `camera-active` class (triggers slide-down animation)
  - Waits 350ms for animation to complete
  - Then hides container and restores body scroll
- On desktop: unchanged behavior (immediate hide)
- Resets facing mode to `'environment'`

#### `capturePatientPhoto()` / `capturePhoto()`

**Changes (prepended before existing capture logic):**
1. **Flash feedback**: Adds `flash-active` class to flash overlay, removes after 150ms
2. **Shutter animation**: Adds `capturing` class to shutter button, removes after 300ms
3. All existing canvas capture, GPS stamping, and preview logic remains unchanged

#### Escape Key Handler

**Extended to also close active cameras:**
```javascript
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closePatientImageModal();
        closeEndorsementImageModal();
        if (patientCameraStream) closePatientCamera();
        if (cameraStream) closeCamera();
    }
});
```

### Camera Flip Logic

The flip functions follow this flow:

1. Add rotation animation class to flip button
2. Toggle facing mode (`'environment'` <-> `'user'`)
3. Stop current camera stream (all tracks)
4. Re-acquire camera with new `facingMode` constraint
5. Assign new stream to video element
6. On error: show notification and revert facing mode

```javascript
function flipPatientCamera() {
    // Animate button
    flipBtn.classList.add('flipping');
    setTimeout(() => flipBtn.classList.remove('flipping'), 400);

    // Toggle mode
    patientCurrentFacingMode = (patientCurrentFacingMode === 'environment')
        ? 'user' : 'environment';

    // Stop current stream
    patientCameraStream.getTracks().forEach(track => track.stop());

    // Re-acquire with new facing mode
    navigator.mediaDevices.getUserMedia({
        video: { facingMode: patientCurrentFacingMode, ... }
    })
    .then(stream => { patientCameraStream = stream; video.srcObject = stream; })
    .catch(() => {
        Notiflix.Notify.failure('Unable to switch camera...');
        // Revert facing mode
        patientCurrentFacingMode = (patientCurrentFacingMode === 'environment')
            ? 'user' : 'environment';
    });
}
```

---

## Z-Index Hierarchy

| Element | z-index | Context |
|---------|---------|---------|
| Mobile camera overlay | `99999` | Highest - covers everything including modals |
| Image preview modal | `9999` | Below camera, above navigation |
| Navigation sticky bar | `1000` | Below modals |
| Save Draft button | `1001` | Above navigation |

---

## Browser Compatibility

| Feature | Support |
|---------|---------|
| `getUserMedia` API | Chrome 47+, Firefox 36+, Safari 11+, Edge 12+ |
| `facingMode` constraint | Chrome 49+, Firefox 50+, Safari 11.1+, Edge 79+ |
| `env(safe-area-inset-*)` | iOS Safari 11.2+, Chrome 69+ |
| CSS `position: fixed` | All modern browsers |
| CSS `cubic-bezier` transitions | All modern browsers |
| `DataTransfer` API | Chrome 62+, Firefox 52+, Safari 14.1+ |

### Graceful Degradation

- **Single camera devices** (most desktop PCs): Flip button is visible but shows an error notification if only one camera exists. The camera continues to work with the available device.
- **Browsers without `getUserMedia`**: Shows an error notification suggesting file upload instead.
- **Browsers without `facingMode` support**: Falls back to default camera (usually front-facing).

---

## Design Decisions

### Why Fullscreen on Mobile Only?

On desktop, an inline camera card is appropriate because:
- Users can see the form context around the camera
- Desktop screens have plenty of space
- The form layout isn't disrupted

On mobile, fullscreen is essential because:
- The 280px viewport was too small to see subjects clearly
- Touch targets for capture/close were too small
- The experience felt nothing like using a camera
- Users expect fullscreen camera behavior from native apps

### Why a Circular Shutter Button?

The circular shutter button is universally recognized from native camera apps on iOS and Android. Users instinctively know to tap the large white circle to take a photo, reducing the learning curve to zero.

### Why the Flash Effect?

Without feedback, users can't be sure a photo was captured. The white flash overlay provides immediate visual confirmation that the shutter fired, mimicking real camera behavior.

### Why Camera Flip?

Mobile devices have front and back cameras. Users may need to:
- Capture documents using the rear camera (higher quality)
- Take patient photos using either camera
- Quickly switch without closing and reopening the camera

---

## Testing Guide

### Desktop Testing

1. Open `prehospital_form.php` in a desktop browser
2. Navigate to **Section 2 (Patient)** > Patient Documentation
3. Click **"Open Camera"**
4. Verify: Dark-themed camera card appears inline with:
   - Top bar: X close button (left), "Patient Photo" label (center)
   - Viewport: Live camera feed
   - Bottom bar: Circular shutter button (center), flip button (right)
5. Click the **shutter button** - verify flash effect and shutter animation
6. Verify photo preview appears with GPS overlay (if location enabled)
7. Click **"Remove"** and verify camera controls reappear

### Mobile Testing (or Chrome DevTools Responsive Mode)

1. Set viewport to 768px or narrower (e.g., iPhone 14 at 390px)
2. Navigate to Patient Documentation > Click **"Open Camera"**
3. Verify: Camera slides up to fill entire screen
4. Verify: Video fills the viewport, controls are at bottom with larger sizes
5. Click **flip button** - verify camera switches (if multiple cameras available)
6. Click **shutter button** - verify flash, then camera slides down and preview appears
7. Press **Escape** or the **X button** - verify camera slides down smoothly
8. Verify body scroll is restored after camera closes

### Both Camera Instances

Repeat the above tests for the **Endorsement Attachment** camera in **Section 6 (Team)**. The behavior should be identical except:
- Label says "Endorsement Document" instead of "Patient Photo"
- No GPS stamping on endorsement photos

### Both PHP Files

Test on both:
- `prehospital_form.php` (new record creation)
- `edit_record.php` (editing existing records)

### Edge Cases

| Scenario | Expected Behavior |
|----------|-------------------|
| Camera permission denied | Error notification with instructions |
| No camera device found | Error notification suggesting file upload |
| Camera already in use | Error notification about camera conflict |
| Only one camera (no flip) | Flip button shows notification "Your device may only have one camera" |
| Escape key while camera open | Camera closes with animation |
| Browser back button on mobile | Camera closes (popstate listener) |
| Resize window while camera open | Camera continues to work |

---

## Dependencies

No new dependencies were added. The feature uses:

- **Bootstrap Icons** (`bi-x-lg`, `bi-arrow-repeat`) - already loaded
- **Notiflix** - already loaded (for error/success notifications)
- **WebRTC `getUserMedia` API** - browser-native
- **CSS transitions/animations** - browser-native

---

## Performance Considerations

- **No additional network requests** - all changes are CSS/JS/HTML
- **CSS animations use `transform` and `opacity`** - GPU-accelerated, no layout thrashing
- **Camera stream is properly stopped** on close (prevents memory/battery drain)
- **Flash overlay uses `pointer-events: none`** - doesn't interfere with touch/click events
- **Facing mode state is reset** on close to prevent stale state across sessions
