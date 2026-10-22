/**
 * Gives the stage a sense of depth: the photo, the concierge and the glass
 * panels drift by different amounts as the pointer moves, so she reads as
 * standing in front of the room rather than pasted on it.
 *
 * The pointer position is eased and published as --depth-x / --depth-y
 * (-1 to 1) on the stage; the layers read it in CSS. Only runs for a fine
 * pointer and when the client has not asked for reduced motion.
 */
const EASE = 0.08;
const SETTLED = 0.001;

function startDepth(stage) {
    let targetX = 0;
    let targetY = 0;
    let currentX = 0;
    let currentY = 0;
    let frame = null;

    const paint = () => {
        currentX += (targetX - currentX) * EASE;
        currentY += (targetY - currentY) * EASE;
        stage.style.setProperty('--depth-x', currentX.toFixed(4));
        stage.style.setProperty('--depth-y', currentY.toFixed(4));

        const settled = Math.abs(targetX - currentX) < SETTLED && Math.abs(targetY - currentY) < SETTLED;
        frame = settled ? null : requestAnimationFrame(paint);
    };

    const aim = (x, y) => {
        targetX = x;
        targetY = y;
        frame ??= requestAnimationFrame(paint);
    };

    window.addEventListener('pointermove', (event) => {
        aim((event.clientX / window.innerWidth) * 2 - 1, (event.clientY / window.innerHeight) * 2 - 1);
    }, { passive: true });
    document.documentElement.addEventListener('pointerleave', () => aim(0, 0));

    stage.classList.add('has-depth');
}

const stage = document.querySelector('.stage');
const canHover = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

if (stage && canHover && !reducedMotion) {
    startDepth(stage);
}
