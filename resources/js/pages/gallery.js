/**
 * Public Gallery Viewer Master Script
 * Imports and initializes the modular gallery orchestrator.
 */
import { bootstrapGallery } from '../gallery/index.js';

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootstrapGallery);
} else {
    bootstrapGallery();
}
