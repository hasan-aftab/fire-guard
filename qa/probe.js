// In-page measurement probe. Read by qa/measure.js via `shot-scraper
// javascript -i`, one run per (state, width, theme).
//
// It measures only what is objectively checkable from the DOM. It cannot judge
// whether the result looks good — that is what the screenshots are for, and a
// machine is the wrong reviewer for it. So this file answers "is anything
// broken or clipped", never "is this a good design".

(() => {
    const MIN_TARGET = 24; // WCAG 2.2 AA, target size (minimum)
    const TOLERANCE = 1; // sub-pixel layout rounding

    const isScrollable = (el) => {
        for (let node = el.parentElement; node; node = node.parentElement) {
            const overflowX = getComputedStyle(node).overflowX;
            if (overflowX === 'auto' || overflowX === 'scroll') return true;
        }
        return false;
    };

    const label = (el) =>
        (el.getAttribute('aria-label') || el.innerText || el.textContent || '')
            .trim()
            .replace(/\s+/g, ' ')
            .slice(0, 40) || el.tagName.toLowerCase();

    const visible = (el) => {
        const style = getComputedStyle(el);
        if (style.display === 'none' || style.visibility === 'hidden')
            return false;
        return el.getClientRects().length > 0;
    };

    // Anything reaching past the viewport, unless a scrollable ancestor makes
    // that the intended reading.
    const overflowing = [...document.querySelectorAll('body *')]
        .filter((el) => visible(el))
        .filter((el) => {
            const rect = el.getBoundingClientRect();
            if (rect.width === 0 && rect.height === 0) return false;
            if (rect.right <= window.innerWidth + TOLERANCE) return false;
            return !isScrollable(el);
        })
        .map((el) => ({
            label: label(el),
            right: Math.round(el.getBoundingClientRect().right),
        }));

    const interactive = [
        'a[href]',
        'button',
        'input',
        'select',
        'textarea',
        '[role="button"]',
        '[role="tab"]',
    ].join(',');

    const smallTargets = [...document.querySelectorAll(interactive)]
        .filter(visible)
        .map((el) => {
            const rect = el.getBoundingClientRect();
            return {
                label: label(el),
                w: Math.round(rect.width),
                h: Math.round(rect.height),
            };
        })
        .filter(({ w, h }) => w < MIN_TARGET || h < MIN_TARGET);

    // Text cut off silently is a bug. Text cut off with an ellipsis is a
    // deliberate truncation, so it is reported separately rather than failed —
    // an ellipsis is a design decision, missing text is not.
    const leaves = [...document.querySelectorAll('body *')]
        .filter(visible)
        .filter((el) => el.children.length === 0 && el.textContent.trim())
        .filter((el) => !isScrollable(el))
        .filter((el) => el.scrollWidth > el.clientWidth + TOLERANCE)
        // Visually-hidden text is clipped to a 1px box on purpose; it is read
        // off the screen, not seen, so a clip there is not a layout fault.
        .filter((el) => !el.closest('.sr-only'));

    const ellipsised = leaves.filter(
        (el) => getComputedStyle(el).textOverflow === 'ellipsis',
    );
    const clippedText = leaves.filter(
        (el) => getComputedStyle(el).textOverflow !== 'ellipsis',
    );

    const strip = document.querySelector('[data-day-strip]');
    const dayCells = strip
        ? [...strip.querySelectorAll('[data-day]')].map((el) => {
              const rect = el.getBoundingClientRect();
              // aria-pressed lives on the button inside the cell, not the cell.
              const control = el.querySelector('button');
              return {
                  day: el.getAttribute('data-day'),
                  w: Math.round(rect.width),
                  h: Math.round(rect.height),
                  selected: control?.getAttribute('aria-pressed') === 'true',
              };
          })
        : [];

    const agenda = document.querySelector('[data-agenda]');
    const rows = agenda ? [...agenda.querySelectorAll('[data-row]')] : [];

    return {
        width: window.innerWidth,
        scrollWidth: document.documentElement.scrollWidth,
        docHeight: document.documentElement.scrollHeight,
        h1: document.querySelector('h1')?.innerText ?? null,
        dueDateLabel:
            document
                .querySelector('[aria-label^="Due date:"]')
                ?.getAttribute('aria-label') ?? null,
        cards: document.querySelectorAll('[data-tile]').length,
        rowCount: rows.length,
        rowKinds: [
            ...new Set(rows.map((row) => row.getAttribute('data-kind'))),
        ].sort((a, b) => a.localeCompare(b)),
        groups: [...document.querySelectorAll('[data-group]')].map((el) => {
            const heading = el.querySelector('h3');
            return {
                label: el.getAttribute('data-group'),
                count: el.querySelectorAll('[data-row]').length,
                headingHeight: heading
                    ? Math.round(heading.getBoundingClientRect().height)
                    : 0,
            };
        }),
        dayCells,
        emptyMessage: document.querySelector('[data-empty]')?.innerText ?? null,
        overflowing: overflowing.slice(0, 8),
        smallTargets: smallTargets.slice(0, 8),
        clippedText: clippedText.slice(0, 8).map((el) => ({
            label: label(el),
            textOverflow: getComputedStyle(el).textOverflow,
        })),
        ellipsisedCount: ellipsised.length,
        tabbable: document.querySelectorAll(interactive).length,
    };
})();
