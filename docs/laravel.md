# Laravel Livewire Integration

The **AF Cropper & Uploader** suite comes with a native Laravel/Livewire bridge. This allows you to use sophisticated cropping and uploading logic with standard `wire:model` binding.

## Installation

1. Copy the `src/` and `styles/` folders to your public directory (e.g., `public/vendor/af-uploader/`).
2. Add the assets directive to your layout:

```html
<head>
    @afUploaderAssets
</head>
```

## Basic Usage

Use the `<x-af-uploader>` component in your Livewire blade views.

### Standard Image Cropper
```html
<x-af-uploader 
    wire:model="photo" 
    variant="circled" 
    ratio="1" 
    isCircle="true" 
    label="Upload Profile Picture" 
/>
```

### General File Uploader (No Cropping)
```html
<x-af-uploader 
    wire:model="document" 
    cropper="false" 
    variant="inline" 
    label="Attach PDF or Doc" 
/>
```

## Component Arguments

| Argument | Default | Description |
| --- | --- | --- |
| `wire:model` | - | The Livewire property to bind to. |
| `cropper` | `true` | Set to `false` to use as a standard file uploader. |
| `variant` | `squared` | UI style: `squared`, `rect`, `circled`, `inline`. |
| `ratio` | `1` | Aspect ratio for the cropper (e.g., `16/9`, `1`). |
| `isCircle` | `false` | Enable circular mask (only works with 1:1 ratio). |
| `maxWidth` | `2000` | Max width for exported image. |
| `quality` | `0.92` | Export quality (0 to 1). |
| `preview` | `true` | Show local preview after success. |

## Handling Success on Server

You can listen for successful uploads in your Livewire component to show a "Synced" status.

```javascript
// In your Livewire Component
public function updatedPhoto()
{
    // Do your storage logic
    $this->photo->store('avatars');
    
    // Dispatch success to UI
    $this->dispatch('af-upload-success', inputId: 'my-input-id');
}
```

## Advanced Customization

The component uses Alpine.js internally to sync progress bars with Livewire's `$wire.upload` process. If you want to customize the progress bar appearance, you can override the `.af-progress-bar` class in your CSS.
