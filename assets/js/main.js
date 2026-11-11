/* =====================================================================
   Projet  : Architecture & Engineering Services
   Auteur  : OUNIADON HAIROULAH (ID: 411030513)
   Fichier : assets/js/main.js
   Rôle    : Comportements communs à toutes les pages — menu mobile,
             en-tête au défilement, apparitions animées, compteurs,
             contrôle de la vidéo du hero. JavaScript Vanilla ES6+.
   ===================================================================== */
'use strict';

// Le JavaScript est actif : on retire la classe de repli « no-js »
document.documentElement.classList.remove('no-js');

/* ---------------------------------------------------------------
   1. Menu mobile (bouton hamburger)
   --------------------------------------------------------------- */
const initMobileNav = () => {
    const toggle = document.querySelector('.nav-toggle');
    const nav = document.getElementById('site-nav');
    if (!toggle || !nav) return;

    const setOpen = (isOpen) => {
        toggle.setAttribute('aria-expanded', String(isOpen));
        toggle.setAttribute('aria-label', isOpen ? 'Fermer le menu' : 'Ouvrir le menu');
        nav.classList.toggle('is-open', isOpen);
        document.body.classList.toggle('nav-open', isOpen);
    };

    toggle.addEventListener('click', () => {
        setOpen(toggle.getAttribute('aria-expanded') !== 'true');
    });

    // Délégation d'événements : un seul écouteur pour tous les liens du menu
    nav.addEventListener('click', (event) => {
        if (event.target.closest('a')) setOpen(false);
    });

    // Touche Échap pour fermer le menu
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && nav.classList.contains('is-open')) {
            setOpen(false);
            toggle.focus();
        }
    });

    // Repasser en desktop referme le menu mobile
    window.matchMedia('(min-width: 1024px)').addEventListener('change', (mq) => {
        if (mq.matches) setOpen(false);
    });
};

/* ---------------------------------------------------------------
   2. En-tête : fond opaque après défilement
   --------------------------------------------------------------- */
const initHeaderOnScroll = () => {
    const header = document.getElementById('site-header');
    if (!header) return;

    // Les pages sans hero vidéo gardent un en-tête opaque dès le départ
    if (!document.querySelector('.hero')) header.classList.add('is-solid');

    let ticking = false;
    const update = () => {
        header.classList.toggle('is-scrolled', window.scrollY > 40);
        ticking = false;
    };

    // requestAnimationFrame limite le nombre de calculs pendant le défilement
    window.addEventListener('scroll', () => {
        if (!ticking) {
            window.requestAnimationFrame(update);
            ticking = true;
        }
    }, { passive: true });

    update();
};

/* ---------------------------------------------------------------
   3. Compteur animé (ex. 0 → 13 000)
   --------------------------------------------------------------- */
const animateCounter = (element) => {
    const target = Number(element.dataset.count);
    const suffix = element.dataset.suffix ?? '';
    const duration = 1800;
    const formatter = new Intl.NumberFormat('fr-FR');
    const start = performance.now();

    const step = (now) => {
        const progress = Math.min((now - start) / duration, 1);
        const eased = 1 - Math.pow(1 - progress, 3);          // ralentit à la fin
        element.textContent = `${formatter.format(Math.round(target * eased))}${suffix}`;
        if (progress < 1) requestAnimationFrame(step);
    };

    requestAnimationFrame(step);
};

/* ---------------------------------------------------------------
   4. Apparition des éléments au défilement (IntersectionObserver)
   --------------------------------------------------------------- */
const initReveal = () => {
    const elements = document.querySelectorAll('.reveal');
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (!('IntersectionObserver' in window) || reduceMotion) {
        elements.forEach((el) => el.classList.add('is-visible'));
        return;
    }

    const observer = new IntersectionObserver((entries, obs) => {
        entries.forEach(({ isIntersecting, target }) => {
            if (!isIntersecting) return;
            target.classList.add('is-visible');
            target.querySelectorAll('[data-count]').forEach(animateCounter);
            obs.unobserve(target);                               // une seule fois
        });
    }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });

    elements.forEach((el) => observer.observe(el));
};

/* ---------------------------------------------------------------
   5. Vidéo du hero : bouton pause/lecture + préférences utilisateur
   --------------------------------------------------------------- */
const initHeroVideo = () => {
    const video = document.getElementById('hero-video');
    const button = document.getElementById('video-toggle');
    if (!video || !button) return;

    const iconPause = button.querySelector('.icon-pause');
    const iconPlay = button.querySelector('.icon-play');

    const render = () => {
        const paused = video.paused;
        iconPause.hidden = paused;
        iconPlay.hidden = !paused;
        button.setAttribute('aria-pressed', String(paused));
        button.setAttribute('aria-label', paused ? 'Lire la vidéo' : 'Mettre la vidéo en pause');
    };

    button.addEventListener('click', () => {
        video.paused ? video.play() : video.pause();
    });
    video.addEventListener('play', render);
    video.addEventListener('pause', render);

    // « Réduire les animations » : on reste sur l'image fixe (poster)
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        video.removeAttribute('autoplay');
        video.pause();
    }

    // Économie de batterie : pause quand la vidéo n'est plus visible
    if ('IntersectionObserver' in window) {
        let userPaused = false;
        button.addEventListener('click', () => { userPaused = video.paused; });

        new IntersectionObserver(([entry]) => {
            if (!entry.isIntersecting) video.pause();
            else if (!userPaused && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                video.play().catch(() => { /* lecture auto bloquée : le poster reste affiché */ });
            }
        }, { threshold: 0.1 }).observe(video);
    }

    render();
};

/* ---------------------------------------------------------------
   6. Année courante dans le pied de page
   --------------------------------------------------------------- */
const initYear = () => {
    document.querySelectorAll('[data-year]').forEach((el) => {
        el.textContent = new Date().getFullYear();
    });
};

/* ---------------------------------------------------------------
   Lancement (le script est chargé avec « defer » : le DOM est prêt)
   --------------------------------------------------------------- */
initMobileNav();
initHeaderOnScroll();
initReveal();
initHeroVideo();
initYear();
