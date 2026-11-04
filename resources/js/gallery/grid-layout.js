/**
 * Places the client gallery's photo cards for the grid style in #gallery-grid's data-layout:
 * "masonry" (shortest column first), "rows" (justified rows, each filling the width) or "square" (a tight grid).
 *
 * A plain script, not a module: public/gallery.blade.php inlines it so the grid is placed before the bundle
 * loads, and resources/js/gallery/modules/layout.js calls the same function afterwards.
 */
window.ifotosetGridLayout = function (grid) {
    var width = grid.clientWidth;
    if (!width) return;

    var style = grid.dataset.layout || 'masonry';
    var cards = Array.prototype.filter.call(grid.querySelectorAll('.photo-card'), function (card) {
        return !card.classList.contains('hidden');
    });
    var gap = width < 640 ? 4 : (width < 1024 ? 8 : 12);
    var height = 0;

    function aspect(card) {
        var w = parseInt(card.dataset.photoWidth || '1920', 10);
        var h = parseInt(card.dataset.photoHeight || '1080', 10);
        return (w && h) ? w / h : 1.5;
    }

    function place(card, x, y, w, h) {
        card.style.position = 'absolute';
        card.style.left = Math.round(x) + 'px';
        card.style.top = Math.round(y) + 'px';
        card.style.width = Math.round(w) + 'px';
        card.style.height = Math.round(h) + 'px';
        card.style.margin = '0';
    }

    if (style === 'square') {
        var squareCols = width < 640 ? 3 : (width < 1024 ? 4 : (width < 1700 ? 6 : 8));
        var squareGap = width < 640 ? 2 : 4;
        var side = (width - (squareCols - 1) * squareGap) / squareCols;
        cards.forEach(function (card, i) {
            place(card, (i % squareCols) * (side + squareGap), Math.floor(i / squareCols) * (side + squareGap), side, side);
        });
        var squareRows = Math.ceil(cards.length / squareCols);
        height = squareRows ? squareRows * side + (squareRows - 1) * squareGap : 0;
    } else if (style === 'rows') {
        var target = width < 640 ? 130 : (width < 1024 ? 180 : (width < 1700 ? 240 : 280));
        var row = [];
        var rowAspect = 0;
        var y = 0;
        var flush = function (rowHeight) {
            var x = 0;
            row.forEach(function (card) {
                var w = aspect(card) * rowHeight;
                place(card, x, y, w, rowHeight);
                x += w + gap;
            });
            y += rowHeight + gap;
            row = [];
            rowAspect = 0;
        };
        cards.forEach(function (card) {
            row.push(card);
            rowAspect += aspect(card);
            if (rowAspect * target + (row.length - 1) * gap >= width) {
                flush((width - (row.length - 1) * gap) / rowAspect);
            }
        });
        // The last row keeps the target height instead of stretching a few photos across the page
        if (row.length) flush(Math.min(target, (width - (row.length - 1) * gap) / rowAspect));
        height = Math.max(0, y - gap);
    } else {
        var cols = width < 640 ? 2 : (width < 1024 ? 3 : (width >= 1700 ? 5 : 4));
        var colWidth = (width - (cols - 1) * gap) / cols;
        var colHeights = [];
        for (var c = 0; c < cols; c++) colHeights.push(0);
        cards.forEach(function (card) {
            var col = 0;
            for (var i = 1; i < cols; i++) { if (colHeights[i] < colHeights[col]) col = i; }
            var h = colWidth / aspect(card);
            place(card, col * (colWidth + gap), colHeights[col], colWidth, h);
            colHeights[col] += h + gap;
        });
        height = cards.length ? Math.max.apply(null, colHeights) - gap : 0;
    }

    grid.style.position = 'relative';
    grid.style.height = Math.round(Math.max(0, height)) + 'px';
};
