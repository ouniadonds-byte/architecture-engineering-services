/* =====================================================================
   Projet  : Architecture & Engineering Services
   Auteur  : OUNIADON HAIROULAH (ID: 411030513)
   Fichier : assets/js/portfolio.js
   Rôle    : Portfolio interactif — filtres par catégorie et lightbox.
             Principe clé : la DÉLÉGATION D'ÉVÉNEMENTS. Au lieu d'attacher
             un écouteur à chaque bouton (5 filtres + 14 projets), on en
             attache UN seul sur le conteneur parent, puis on identifie
             l'élément cliqué avec event.target.closest().
   ===================================================================== */
'use strict';

const filterBar = document.getElementById('filters');
const grid = document.getElementById('portfolio-grid');
const emptyMessage = document.getElementById('portfolio-empty');
const filterStatus = document.getElementById('filter-status');

const lightbox = document.getElementById('lightbox');
const lightboxImg = document.getElementById('lightbox-img');
const lightboxTitle = document.getElementById('lightbox-title');
const lightboxDesc = document.getElementById('lightbox-desc');

let currentIndex = 0;      // projet affiché dans la lightbox
let lastTrigger = null;    // bouton à re-focaliser à la fermeture

/** Retourne les boutons des projets actuellement visibles (après filtrage). */
const getVisibleButtons = () =>
    [...grid.querySelectorAll('.portfolio-item:not([hidden]) .portfolio-item__btn')];

/* ---------------------------------------------------------------
   1. Filtres (délégation sur la barre de filtres)
   --------------------------------------------------------------- */
const applyFilter = (category) => {
    let visibleCount = 0;

    grid.querySelectorAll('.portfolio-item').forEach((item) => {
        const match = category === 'all' || item.dataset.category === category;
        item.hidden = !match;
        if (match) visibleCount += 1;
    });

    emptyMessage.hidden = visibleCount > 0;
    filterStatus.textContent = `${visibleCount} projet${visibleCount > 1 ? 's' : ''} affiché${visibleCount > 1 ? 's' : ''}`;
};

filterBar.addEventListener('click', (event) => {
    const button = event.target.closest('[data-filter]');
    if (!button) return;                       // clic hors d'un bouton : on ignore

    // Mise à jour de l'état visuel et accessible (aria-pressed)
    filterBar.querySelectorAll('[data-filter]').forEach((btn) => {
        btn.setAttribute('aria-pressed', String(btn === button));
    });

    applyFilter(button.dataset.filter);
});

/* ---------------------------------------------------------------
   2. Lightbox (délégation sur la grille)
   --------------------------------------------------------------- */
const showProject = (index) => {
    const buttons = getVisibleButtons();
    if (buttons.length === 0) return;

    // Navigation circulaire : après le dernier, on revient au premier
    currentIndex = (index + buttons.length) % buttons.length;
    const { full, title, desc } = buttons[currentIndex].dataset;   // déstructuration ES6

    lightboxImg.src = full;
    lightboxImg.alt = title;
    lightboxTitle.textContent = title;
    lightboxDesc.textContent = desc;
};

const openLightbox = (button) => {
    lastTrigger = button;
    showProject(getVisibleButtons().indexOf(button));
    lightbox.showModal();                      // <dialog> natif : gère Échap et le focus
};

const closeLightbox = () => {
    lightbox.close();
};

grid.addEventListener('click', (event) => {
    const button = event.target.closest('.portfolio-item__btn');
    if (button) openLightbox(button);
});

// Boutons précédent / suivant / fermer : encore une délégation
lightbox.addEventListener('click', (event) => {
    // Clic sur le fond sombre (en dehors du contenu) : fermeture
    if (event.target === lightbox) {
        closeLightbox();
        return;
    }

    const control = event.target.closest('[data-action]');
    if (!control) return;

    const actions = {
        prev: () => showProject(currentIndex - 1),
        next: () => showProject(currentIndex + 1),
        close: closeLightbox,
    };
    actions[control.dataset.action]?.();
});

// Navigation au clavier avec les flèches
lightbox.addEventListener('keydown', (event) => {
    if (event.key === 'ArrowLeft') showProject(currentIndex - 1);
    if (event.key === 'ArrowRight') showProject(currentIndex + 1);
});

// À la fermeture, le focus revient sur le projet d'origine (accessibilité)
lightbox.addEventListener('close', () => {
    lastTrigger?.focus();
});

applyFilter('all');
