# ArtFlow File Uploader - Critical Bugs & Fixes

**Date:** 2026-01-23  
**Testing:** Playwright browser testing completed  
**Status:** ROOT CAUSES IDENTIFIED

## Critical Bugs Found

### BUG #1: File Dialog Opens Automatically (5+ times on page load!)
**Severity:** CRITICAL  
**Confirmed:** ✅ YES - Playwright detected 5 file choosers opening automatically on page load

**Root Cause:**
- The `init()` method in Alpine.js calls `window.AF_Cropper.setupDropzone(fileInput)` 
- Something in the setup is triggering `input.click()` multiple times
- This causes the file chooser dialog to open without user interaction

**Evidence:**
```
### Modal state
- [File chooser]: can be handled by the "browser_file_upload" tool
- [File chooser]: can be handled by the "browser_file_upload" tool
- [File chooser]: can be handled by the "browser_file_upload" tool
- [File chooser]: can be handled by the "browser_file_upload" tool
- [File chooser]: can be handled by the "browser_file_upload" tool
```

**Fix Required:**
- Remove auto-click trigger from `setupDropzone`
- Only open file dialog when user explicitly clicks the uploader area
- Add proper event handler that prevents multiple triggers

---

### BUG #2: Auto-Upload (Not FilePond Style)
**Severity:** HIGH  
**Confirmed:** ✅ Code analysis confirms auto-upload behavior

**Root Cause:**
In `uploader.blade.php` lines 150-200, the `handleSelection()` method immediately calls:
```javascript
this.$wire.upload(this.wireModel, uploadTarget, ...)
```

This is different from FilePond which:
1. Shows file preview immediately
2. Waits for user to click "Upload" or submit form
3. Uses `@entangle` for two-way binding
4. Only uploads when explicitly triggered

**FilePond Pattern (from vendor/spatie/livewire-filepond/upload.blade.php):**
```blade
x-init="
    pond = FilePond.create($refs.input);
    pond.server = {
        process: (fieldName, file, metadata, load, error, progress, abort, transfer, options) => {
            @this.upload(...)
        },
        revert: () => { ... },
        remove: () => { ... }
    }
"
```

**Current ArtFlow Pattern:**
```javascript
onFileSelected(e) {
    // Immediately triggers upload
    this.$wire.upload(this.wireModel, uploadTarget, ...)
}
```

**Fix Required:**
1. Add file preview state WITHOUT uploading
2. Add "Upload" button or auto-upload on form submit only
3. Show close button to remove selected file before upload
4. Implement FilePond-style `process` server method pattern

---

### BUG #3: Instance Isolation Still Broken
**Severity:** HIGH  
**User Reported:** ✅ "second instance shows same upload status"

**Root Cause:**
While unique IDs are generated, the event scoping may not be working correctly:
```javascript
@af-status-update-{{ $id }}.window="onStatusUpdate($event)"
```

The `onStatusUpdate` method checks:
```javascript
if (detail.id && detail.id !== this.id) return;
```

BUT the event is dispatched in JS as:
```javascript
window.dispatchEvent(new CustomEvent(eventName, { detail: { id: uniqueId } }));
```

**Potential Issues:**
- `uniqueId` may be `null` or undefined
- Alpine event listeners may catch all events despite scoping
- wire:ignore may not prevent Livewire morphing correctly

**Fix Required:**
- Ensure unique ID is ALWAYS passed in event detail
- Add additional isolation check for input element reference
- Test with 3+ instances simultaneously

---

### BUG #4: JSON Files Not Deleted
**Severity:** MEDIUM  
**User Reported:** ✅ "JSON file not deleted when click close"

**Root Cause:**
No revert/remove server method implemented.

**FilePond Has:**
```javascript
revert: (uniqueFileId, load, error) => {
    @this.removeUpload(...)
},
remove: (source, load, error) => {
    @this.deleteUpload(...)
}
```

**ArtFlow Needs:**
- Implement close button click handler
- Call Livewire method to delete temporary upload
- Clear Alpine state
- Reset UI to initial state

---

### BUG #5: No Upload Progress Indication
**Severity:** LOW  
**User Reported:** "no proper indication when upload starts"

**Current Implementation:**
- Has progress ring in CSS
- Has `isUploading` and `progress` state
- BUT no clear visual feedback

**Fix Required:**
- Show upload icon → pause icon → check icon states
- Position icon in top right corner like FilePond
- Add loading spinner or progress ring animation
- Clear success state after upload

---

## Recommended Fix Strategy

### Phase 1: Stop Auto-Dialog Bug (CRITICAL)
1. Remove/fix auto-click trigger in `setupDropzone`
2. Add explicit click handler to dropzone area
3. Test with multiple instances

### Phase 2: Implement Manual Upload Flow
1. Change `onFileSelected` to show preview only
2. Add upload state management (selected, uploading, uploaded)
3. Implement FilePond-style server methods
4. Add Upload button UI
5. Only call `$wire.upload()` when user clicks Upload or submits form

### Phase 3: Fix Instance Isolation
1. Ensure uniqueId is always passed
2. Add input element reference check
3. Test event isolation thoroughly

### Phase 4: Add File Removal
1. Implement close button
2. Add revert/remove server methods
3. Clean up temporary files

### Phase 5: Improve UI Feedback
1. Add icon states (upload → pause → check)
2. Position icon top right
3. Add smooth animations
4. Test all states

---

## Test Results

**Page:** http://127.0.0.1:5656/test-uploader  
**Instances:** 3 uploaders on same page  
**Browser:** Playwright (Chrome 143)

**Bugs Confirmed:**
- ✅ File dialog opens 5+ times on page load
- ✅ Auto-upload behavior confirmed in code
- ⚠️ Instance isolation needs live testing
- ⚠️ File removal needs implementation

**Next Steps:**
1. Fix auto-dialog bug first
2. Implement manual upload flow
3. Test instance isolation with actual uploads
4. Add file removal functionality
5. Polish UI/UX

---

## Files Modified (Ready for Fixes)

- `vendor/artflow-studio/file-uploader/resources/views/components/uploader.blade.php` - Alpine component
- `vendor/artflow-studio/file-uploader/public/js/index.js` - AF_Cropper class
- `vendor/artflow-studio/file-uploader/public/css/main.css` - Styles

**Test Page Created:**
- `app/Livewire/UploaderTest.php` - Test component
- `resources/views/livewire/uploader-test.blade.php` - Test view
- Route: `/test-uploader` ✅ Working
