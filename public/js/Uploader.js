/**
 * Uploader handles sending the cropped Blob/File to the server via XHR.
 * Designed to be compatible with Laravel's Livewire temporary upload flow.
 */
export class Uploader {
    /**
     * Uploads a Blob to a specified endpoint.
     * @param {Blob} blob 
     * @param {Object} options {endpoint, filename, fieldName, onProgress}
     * @returns {Promise<Object>} Response data
     */
    static upload(blob, options = {}) {
        const {
            endpoint = '/livewire/upload-file',
            filename = 'cropped-image.webp',
            fieldName = 'files[]',
            onProgress = () => {}
        } = options;

        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            const formData = new FormData();

            // Convert Blob to File if needed (Livewire often expects a File)
            const file = new File([blob], filename, { type: blob.type });
            formData.append(fieldName, file);

            xhr.open('POST', endpoint);

            // Add CSRF token if available (standard for Laravel)
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            if (csrfToken) {
                xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);
            }

            xhr.upload.onprogress = (e) => {
                if (e.lengthComputable) {
                    const percent = Math.round((e.loaded / e.total) * 100);
                    onProgress(percent);
                }
            };

            xhr.onload = () => {
                if (xhr.status >= 200 && xhr.status < 300) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        resolve(response);
                    } catch (e) {
                        resolve(xhr.responseText);
                    }
                } else {
                    reject(new Error(`Upload failed with status ${xhr.status}: ${xhr.statusText}`));
                }
            };

            xhr.onerror = () => reject(new Error('Network error during upload.'));

            xhr.send(formData);
        });
    }
}
