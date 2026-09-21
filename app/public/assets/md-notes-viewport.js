(() => {
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
