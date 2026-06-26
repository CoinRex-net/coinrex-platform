/**
 * CoinRex Homepage Background Animations v2
 * Galaxy Night Sky + Shooting Stars + Broken Stars + Nebula Orbs
 * 
 * Generates:
 * - 120 particles (desktop) / 50 (mobile) with varied shapes
 * - Broken star debris clusters (one by one, random direction)
 * - Shooting stars
 * - Galaxy band rotation
 * - Static starfield for depth
 * - Floating nebula orbs
 */

(function() {
    'use strict';

    const hero = document.getElementById('hero');
    if (!hero) return;

    // Respect reduced motion
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (prefersReducedMotion) return;

    // Detect mobile
    const isMobile = window.innerWidth < 768;
    const particleCount = isMobile ? 50 : 120;
    const shootingStarCount = isMobile ? 2 : 4;

    // Create background layer container
    const bgLayer = document.createElement('div');
    bgLayer.className = 'cr-hero-bg-layer';
    hero.prepend(bgLayer);

    // ---- Galaxy Band (milky way) ----
    const galaxyBand = document.createElement('div');
    galaxyBand.className = 'cr-galaxy-band';
    bgLayer.appendChild(galaxyBand);

    // ---- Static Starfield (background depth stars) ----
    const starfieldCount = isMobile ? 30 : 80;
    const starfield = document.createElement('div');
    starfield.className = 'cr-starfield';
    bgLayer.appendChild(starfield);

    for (let i = 0; i < starfieldCount; i++) {
        const star = document.createElement('div');
        star.className = 'cr-starfield-star';
        const size = 0.5 + Math.random() * 1.5;
        star.style.cssText = [
            'left: ' + (Math.random() * 100) + '%',
            'top: ' + (Math.random() * 100) + '%',
            'width: ' + size + 'px',
            'height: ' + size + 'px',
            'opacity: ' + (0.05 + Math.random() * 0.15)
        ].join('; ');
        starfield.appendChild(star);
    }

    // ---- Create Floating Orbs ----
    const orbCount = isMobile ? 2 : 3;
    for (let i = 1; i <= orbCount; i++) {
        const orb = document.createElement('div');
        orb.className = 'cr-orb cr-orb-' + i;
        orb.style.setProperty('--orb-duration', (16 + Math.random() * 12) + 's');
        orb.style.setProperty('--orb-delay', (Math.random() * 4) + 's');
        bgLayer.appendChild(orb);
    }

    // ---- Color Palette (cosmic) ----
    const colors = [
        '#ffffff', '#ffffff', '#ffffff', '#ffffff',
        '#93C5FD', '#60a5fa', '#a78bfa',
        '#ffe08a', '#D4AF37', '#fbbf24',
        '#fca5a5', '#fb923c', '#e879f9'
    ];

    // ---- Create Particle Stars (slower: 8-16s) ----
    for (let i = 0; i < particleCount; i++) {
        const particle = document.createElement('div');
        particle.className = 'cr-particle';

        const size = 0.8 + Math.random() * 3.5;
        const left = Math.random() * 100;
        const top = Math.random() * 100;
        const opacity = 0.2 + Math.random() * 0.6;
        const duration = 8 + Math.random() * 8; // Slower: 8-16s
        const delay = Math.random() * 6;
        const color = colors[Math.floor(Math.random() * colors.length)];

        // Shape variety
        const shapeRand = Math.random();
        if (shapeRand < 0.12) {
            particle.classList.add('star-shape');
        } else if (shapeRand < 0.18) {
            particle.classList.add('diamond-shape');
        } else if (shapeRand < 0.22) {
            particle.classList.add('triangle-shape');
        }

        if (Math.random() < 0.25) {
            particle.classList.add('twinkle');
        }

        if (Math.random() < 0.10) {
            particle.classList.add('trail');
            particle.style.color = color;
        }

        particle.style.cssText = [
            'left: ' + left + '%',
            'top: ' + top + '%',
            'width: ' + size + 'px',
            'height: ' + size + 'px',
            'background: ' + color,
            '--particle-opacity: ' + opacity,
            '--particle-duration: ' + duration + 's',
            '--particle-delay: ' + delay + 's',
            'opacity: ' + opacity
        ].join('; ');

        bgLayer.appendChild(particle);
    }

    // ---- Broken Stars (Debris Burst) ----
    // Each burst spawns at a random position on the hero
    // Fragments fly outward in random directions using absolute positioning on bgLayer

    function spawnDebrisBurst() {
        // Get hero dimensions for pixel-based positioning
        const heroRect = hero.getBoundingClientRect();
        const heroW = heroRect.width;
        const heroH = heroRect.height;

        // Random spawn position in pixels
        const spawnX = Math.random() * heroW;
        const spawnY = Math.random() * heroH;

        // Number of fragments: 5-10
        const fragCount = 5 + Math.floor(Math.random() * 6);

        const fragments = [];

        for (let f = 0; f < fragCount; f++) {
            const frag = document.createElement('div');
            frag.className = 'cr-debris-fragment';

            const fragSize = 1.5 + Math.random() * 3.5;
            const fragColor = colors[Math.floor(Math.random() * colors.length)];

            // Random direction angle (0-360 degrees)
            const angle = Math.random() * 360;
            const radians = angle * Math.PI / 180;

            // Random distance: 80-350px outward
            const distance = 80 + Math.random() * 270;

            // End position in pixels (absolute on page)
            const endX = Math.cos(radians) * distance;
            const endY = Math.sin(radians) * distance;

            // Duration: 1-3s
            const duration = 1 + Math.random() * 2;

            // Stagger: 0-0.5s
            const delay = Math.random() * 0.5;

            // Rotation
            const rotation = Math.random() * 720 - 360;

            // Shape
            let shapeStyle = '';
            const shapeType = Math.random();
            if (shapeType < 0.25) {
                shapeStyle = 'clip-path: polygon(50% 0%, 0% 100%, 100% 100%); border-radius: 0;';
            } else if (shapeType < 0.40) {
                shapeStyle = 'clip-path: polygon(50% 0%, 100% 50%, 50% 100%, 0% 50%); border-radius: 0;';
            } else if (shapeType < 0.50) {
                shapeStyle = 'clip-path: polygon(50% 0%, 61% 35%, 98% 35%, 68% 57%, 79% 91%, 50% 70%, 21% 91%, 32% 57%, 2% 35%, 39% 35%); border-radius: 0;';
            }

            const glow = Math.random() < 0.3 ? 'box-shadow: 0 0 5px 2px ' + fragColor + ';' : '';

            // Position fragment absolutely on bgLayer at spawn point
            frag.style.cssText = [
                'position: absolute',
                'left: ' + spawnX + 'px',
                'top: ' + spawnY + 'px',
                'width: ' + fragSize + 'px',
                'height: ' + fragSize + 'px',
                'background: ' + fragColor,
                'border-radius: 50%',
                shapeStyle,
                glow,
                'opacity: 1',
                'pointer-events: none',
                'z-index: 0',
                'will-change: transform, opacity'
            ].join('; ');

            bgLayer.appendChild(frag);
            fragments.push({ el: frag, endX: endX, endY: endY, rotation: rotation, duration: duration * 1000, delay: delay * 1000 });
        }

        // Animate all fragments
        const startTime = performance.now();

        function animateBurst(now) {
            const elapsed = now - startTime;
            let allDone = true;

            for (let i = 0; i < fragments.length; i++) {
                const f = fragments[i];
                const localElapsed = elapsed - f.delay;

                if (localElapsed < 0) {
                    allDone = false;
                    continue;
                }

                const progress = Math.min(localElapsed / f.duration, 1);
                const eased = 1 - Math.pow(1 - progress, 3);

                const x = f.endX * eased;
                const y = f.endY * eased;
                const rot = f.rotation * eased;
                const opacity = 1 - (eased * 0.85);

                f.el.style.transform = 'translate(' + x + 'px, ' + y + 'px) rotate(' + rot + 'deg)';
                f.el.style.opacity = opacity;

                if (progress < 1) {
                    allDone = false;
                }
            }

            if (!allDone) {
                requestAnimationFrame(animateBurst);
            } else {
                // Remove all fragments
                for (let i = 0; i < fragments.length; i++) {
                    if (fragments[i].el.parentNode) {
                        fragments[i].el.parentNode.removeChild(fragments[i].el);
                    }
                }
            }
        }

        requestAnimationFrame(animateBurst);
    }

    // Spawn debris bursts one by one
    const debrisInterval = isMobile ? 4000 : 2500; // Every 2.5s desktop, 4s mobile

    // Spawn first one after a short delay
    setTimeout(function() {
        spawnDebrisBurst();
        setInterval(spawnDebrisBurst, debrisInterval);
    }, 1500);

    // ---- Create Shooting Stars ----
    for (let i = 0; i < shootingStarCount; i++) {
        const star = document.createElement('div');
        star.className = 'cr-shooting-star';
        const shootDuration = 2.5 + Math.random() * 2.5;
        const shootDelay = 5 + Math.random() * 15 + (i * 8);
        const startX = 60 + Math.random() * 40;
        const startY = Math.random() * 40;

        star.style.cssText = [
            'left: ' + startX + '%',
            'top: ' + startY + '%',
            '--shoot-duration: ' + shootDuration + 's',
            '--shoot-delay: ' + shootDelay + 's'
        ].join('; ');

        bgLayer.appendChild(star);
    }

    // ---- Handle resize ----
    let lastWidth = window.innerWidth;
    window.addEventListener('resize', function() {
        const newWidth = window.innerWidth;
        const wasMobile = lastWidth < 768;
        const nowMobile = newWidth < 768;
        if (wasMobile !== nowMobile) {
            lastWidth = newWidth;
            if (bgLayer.parentNode) {
                location.reload();
            }
        }
    });
})();
