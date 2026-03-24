/**
 * ImageLoader handles decoding files into ImageBitmaps.
 * Using createImageBitmap is faster and more memory-efficient than <img> tags.
 */
export class ImageLoader {
    /**
     * Decodes a File or Blob into an ImageBitmap.
     * @param {File|Blob} file 
     * @returns {Promise<ImageBitmap>}
     */
    static async decode(file) {
        if (!file || !file.type.startsWith('image/')) {
            throw new Error('Invalid file type. Please provide an image.');
        }

        try {
            console.log(`Decoding image: ${file.name} (${file.size} bytes)`);
            const bitmap = await createImageBitmap(file, {
                resizeQuality: 'high',
                colorSpaceConversion: 'none'
            });
            console.log(`Decoded bitmap: ${bitmap.width}x${bitmap.height}`);
            return bitmap;
        } catch (error) {
            console.error('Failed to decode image:', error);
            throw error;
        }
    }
}
