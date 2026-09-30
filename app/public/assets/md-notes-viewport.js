(() => {
    // Assign bounded UI values through CSSOM; never parse inline style attributes.
    const selector = '[data-progress-width], [data-choice-color], [data-tree-color], [data-parent-depth]';
    const applyValues = (root) => {
        const elements = [...(root.querySelectorAll?.(selector) || [])];
        if (root.matches?.(selector)) elements.unshift(root);
        for (const element of elements) {
            for (const [key, property] of [['choiceColor', '--folder-choice'], ['treeColor', '--folder-color']]) {
                if (/^#[0-9a-f]{6}$/i.test(element.dataset[key] || '')) element.style.setProperty(property, element.dataset[key]);
            }
            if (element.hasAttribute('data-progress-width')) {
                const width = Number(element.dataset.progressWidth);
                if (Number.isFinite(width)) element.style.width = `${Math.max(0, Math.min(100, width))}%`;
            }
            if (element.hasAttribute('data-parent-depth')) {
                const depth = Number(element.dataset.parentDepth);
                if (Number.isFinite(depth)) element.style.setProperty('--parent-depth', String(Math.max(0, Math.min(100, depth))));
            }
        }
    };
    applyValues(document);
    new MutationObserver(records => {
        for (const record of records) for (const node of record.addedNodes) applyValues(node);
    }).observe(document.body, { childList: true, subtree: true });

    const viewport = window.visualViewport;
    if (!viewport) return;
    let frame = null;
    const update = () => {
        frame = null;
        // Keep pinch zoom independent from the application's layout.
        if (Math.abs(viewport.scale - 1) > 0.05) return;
        document.documentElement.style.setProperty('--visible-height', `${viewport.height}px`);
        document.documentElement.style.setProperty('--visible-top', `${viewport.offsetTop}px`);
    };
    const schedule = () => {
        if (frame === null) frame = requestAnimationFrame(update);
    };
    viewport.addEventListener('resize', schedule);
    viewport.addEventListener('scroll', schedule);
    window.addEventListener('pageshow', schedule);
    update();
})();
