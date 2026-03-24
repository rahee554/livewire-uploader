# AF Cropper & Uploader - Feature Roadmap

This document outlines the current capabilities of the V3.3 suite and the planned trajectory for future enhancements.

## ✅ Currently Implemented Features

### 📐 Precision & Physics
- **Physical Unit Support**: Define masks in `mm`, `cm`, or `in` for real-world printing precision.
- **Aspect Ratio Locking**: Lock users into specific ratios (1:1, 16:9, 4:3, etc.) via simple data attributes.
- **High-Resolution Pipeline**: Export images up to 4000px with selectable quality (0.1 - 1.0).
- **Iterative Compression**: Target specific file sizes (e.g., max 500KB) through automated quality reduction loops.

### 🎨 User Experience
- **Cinematic Modal**: Smooth scale-up and fade-in animations with real-time backdrop blurring.
- **Glassmorphism UI**: Minimalist dark-themed interface with translucent elements and sleek typography.
- **Mobile First**: Full touch support including pinch-to-zoom, single-finger drag, and expanded touch-targets for handles.
- **Livewire V3 Bridge**: Native integration that handles DOM morphing and server-side sync events automatically.

### 🛠 Tools & Interaction
- **Freeform Mode**: Drag corner handles to manually define crop areas (no fixed ratio).
- **Circle Masking**: Instant toggle for circular crops, perfect for modern profile picture workflows.
- **Circular Dropzone Theme**: Profile-pic style previews with centered clear icons and bottom-arc metadata.
- **Robust Initialization**: Safe attachment engine that handles missing UI elements without crashing.
- **90° Rotation Engine**: Lossless rotation with real-time degree feedback.
- **Auto-Fit Logic**: Intelligent one-click button to fit any image perfectly centered within the mask.
- **Metadata Overlays**: Real-time display of file names and sizes in KB/MB.

---

## 🚀 Future Roadmap (Planned Features)

### 📈 Phase 1: Advanced Editing
- [ ] **Brightness/Contrast Filters**: Simple sliders for basic image adjustment directly in the modal.
- [ ] **Flip Horizontal/Vertical**: Lossless mirroring tools.
- [ ] **Reset Button**: One-tap to revert all changes (zoom, pan, rotation) to initial state.

### 🎨 Phase 2: Design & Customization
- [ ] **Dynamic Themes**: Support for light mode and custom accent colors via CSS variables.
- [ ] **Custom Overlays**: Add watermark or grid-line support for specialized document scanning.
- [ ] **Multiple File Support**: Queue multiple images and process them one by one in the same modal.

### 🏗 Phase 3: Technical Power
- [ ] **WebWorker Offloading**: Move heavy export processing to a background thread to prevent UI freezing during 4K crops.
- [ ] **SVG Export**: Ability to crop and save as SVG if source is compatible.
- [ ] **Cloud Presets**: Integration with common cloud storage APIs for direct-to-S3 uploads.
