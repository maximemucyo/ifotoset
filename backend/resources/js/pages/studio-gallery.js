/**
 * Studio Gallery Manager (SortableJS + Uppy adapter)
 */
import Sortable from 'sortablejs';

window.initSortableGrid = function(containerElement, onUpdateCallback) {
    if (!containerElement) return null;

    return new Sortable(containerElement, {
        animation: 150,
        ghostClass: 'opacity-40',
        handle: '.drag-handle',
        onEnd: function(evt) {
            if (typeof onUpdateCallback === 'function') {
                onUpdateCallback(evt);
            }
        },
    });
};
