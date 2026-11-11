/* =====================================================================
   Projet  : Architecture & Engineering Services
   Auteur  : OUNIADON HAIROULAH (ID: 411030513)
   Fichier : assets/js/form-validation.js
   Rôle    : Formulaire de demande —
             1. affichage dynamique de la section adaptée au type de demande ;
             2. validation STRICTE côté client (règles centralisées, messages
                en français, validation en temps réel) ;
             3. envoi asynchrone (fetch) vers api/submit-request.php.
   NB      : la validation client améliore l'expérience utilisateur mais ne
             remplace JAMAIS la validation serveur (Phase 4, en PHP).
   ===================================================================== */
'use strict';

/* ---------------------------------------------------------------
   1. Expressions régulières réutilisables
   --------------------------------------------------------------- */
const PATTERNS = {
    // Lettres (y compris accentuées, \p{L}), espaces, apostrophes, tirets, points
    name: /^[\p{L}][\p{L}\s'’.-]*[\p{L}.]$/u,
    // Format courant d'adresse e-mail : partie-locale@domaine.extension
    email: /^[^\s@]+@[^\s@]+\.[a-z]{2,}$/i,
    // Chiffres, espaces, tirets, parenthèses, « + » initial facultatif
    phone: /^\+?[\d\s().-]+$/,
    place: /^[\p{L}\d\s'’,.()-]+$/u,
};

/** Date du jour (heure locale) au format AAAA-MM-JJ, comparable avec un input date. */
const today = () => {
    const d = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
};

/* ---------------------------------------------------------------
   2. Règles de validation : une fonction par champ.
      Chaque fonction reçoit la valeur (nettoyée) et le formulaire,
      et retourne un message d'erreur ou une chaîne vide si tout va bien.
   --------------------------------------------------------------- */
const RULES = {
    request_type: (value) =>
        ['custom_project', 'plan_purchase', 'training_enrollment'].includes(value)
            ? '' : 'Veuillez choisir un type de demande.',

    full_name: (value) => {
        if (!value) return 'Le nom complet est obligatoire.';
        if (value.length < 3 || value.length > 120) return 'Le nom doit contenir entre 3 et 120 caractères.';
        if (!PATTERNS.name.test(value)) return 'Le nom ne doit contenir que des lettres, espaces, apostrophes ou tirets.';
        if (value.split(/\s+/).length < 2) return 'Veuillez indiquer votre prénom et votre nom.';
        return '';
    },

    email: (value) => {
        if (!value) return "L'adresse e-mail est obligatoire.";
        if (value.length > 254 || !PATTERNS.email.test(value)) return 'Adresse e-mail invalide (exemple : nom@domaine.com).';
        return '';
    },

    phone: (value) => {
        if (!value) return '';                                   // facultatif
        const digits = value.replace(/\D/g, '');
        if (!PATTERNS.phone.test(value) || digits.length < 8 || digits.length > 15) {
            return 'Numéro invalide : 8 à 15 chiffres, indicatif « + » accepté.';
        }
        return '';
    },

    country: (value) => (value && !PATTERNS.name.test(value) ? 'Nom de pays invalide.' : ''),
    city: (value) => (value && !PATTERNS.place.test(value) ? 'Nom de ville invalide.' : ''),

    /* --- Projet sur mesure --- */
    project_type: (value) => (value ? '' : 'Veuillez choisir une prestation.'),
    building_use: (value) => (value ? '' : "Veuillez choisir l'usage du bâtiment."),
    location: (value) => (value && !PATTERNS.place.test(value) ? 'Localisation invalide.' : ''),

    land_area_m2: (value) => {
        if (!value) return '';
        const n = Number(value);
        return Number.isFinite(n) && n > 0 && n <= 1_000_000 ? '' : 'Surface invalide (entre 1 et 1 000 000 m²).';
    },

    budget_min: (value) => {
        if (!value) return '';
        return Number(value) >= 0 ? '' : 'Le budget ne peut pas être négatif.';
    },

    // Validation croisée : le maximum doit être ≥ au minimum
    budget_max: (value, form) => {
        if (!value) return '';
        const max = Number(value);
        const min = Number(form.elements.budget_min.value);
        if (max < 0) return 'Le budget ne peut pas être négatif.';
        if (form.elements.budget_min.value && max < min) return 'Le budget maximum doit être supérieur ou égal au minimum.';
        return '';
    },

    desired_deadline: (value) => (value && value < today() ? 'La date doit être dans le futur.' : ''),

    /* --- Achat de plan --- */
    plan_id: (value) => (/^\d+$/.test(value) ? '' : 'Veuillez choisir un plan.'),
    license_type: (value) => (['single_build', 'multi_build'].includes(value) ? '' : 'Veuillez choisir une licence.'),

    /* --- Formation --- */
    training_id: (value) => (/^\d+$/.test(value) ? '' : 'Veuillez choisir une formation.'),
    delivery_mode: (value) => (['online', 'onsite'].includes(value) ? '' : 'Veuillez choisir un mode de suivi.'),
    current_level: (value) => (['beginner', 'intermediate', 'advanced'].includes(value) ? '' : 'Veuillez indiquer votre niveau.'),
    preferred_start: (value) => (value && value < today() ? 'La date doit être dans le futur.' : ''),

    /* --- Message : obligatoire (20 caractères min.) pour un projet sur mesure --- */
    message: (value, form) => {
        const required = form.elements.request_type.value === 'custom_project';
        if (required && value.length < 20) return 'Décrivez votre projet en au moins 20 caractères.';
        if (value.length > 2000) return 'Le message ne doit pas dépasser 2000 caractères.';
        return '';
    },

    consent_given: (_value, form) =>
        form.elements.consent_given.checked ? '' : 'Votre accord est nécessaire pour traiter la demande.',
};

/* ---------------------------------------------------------------
   3. Classe FormValidator (programmation orientée objet en ES6)
   --------------------------------------------------------------- */
class FormValidator {
    /**
     * @param {HTMLFormElement} form  Formulaire à piloter
     * @param {Object} rules          Règles de validation par nom de champ
     */
    constructor(form, rules) {
        this.form = form;
        this.rules = rules;
        this.status = form.querySelector('#form-status');
        this.submitBtn = form.querySelector('#submit-btn');
        this.touched = new Set();          // champs déjà visités par l'utilisateur

        this.bindEvents();
        this.switchType(this.getValue('request_type'));
        this.updateCharCount();
    }

    /** Valeur nettoyée d'un champ (gère les groupes de boutons radio). */
    getValue(name) {
        const element = this.form.elements[name];
        if (!element) return '';
        return String(element.value ?? '').trim();
    }

    /** Un champ est actif s'il n'est pas dans une section désactivée. */
    isActive(name) {
        const element = this.form.elements[name];
        const first = element instanceof RadioNodeList ? element[0] : element;
        return Boolean(first) && !first.closest('fieldset:disabled');
    }

    /* ---------- Événements (délégation sur le formulaire) ---------- */
    bindEvents() {
        // Un seul écouteur « change » pour tout le formulaire
        this.form.addEventListener('change', (event) => {
            const { name } = event.target;
            if (name === 'request_type') this.switchType(event.target.value);
            if (this.touched.has(name) || event.target.type === 'checkbox' || event.target.tagName === 'SELECT') {
                this.validateField(name);
            }
            if (name === 'budget_min' && this.touched.has('budget_max')) this.validateField('budget_max');
        });

        // Validation en temps réel, seulement après une première visite du champ
        this.form.addEventListener('input', (event) => {
            const { name } = event.target;
            if (name === 'message') this.updateCharCount();
            if (this.touched.has(name)) this.validateField(name);
        });

        // « focusout » remonte (bubbling) contrairement à « blur » : idéal pour la délégation
        this.form.addEventListener('focusout', (event) => {
            const { name } = event.target;
            if (!name || !(name in this.rules)) return;
            this.touched.add(name);
            this.validateField(name);
        });

        this.form.addEventListener('submit', (event) => this.handleSubmit(event));
    }

    /* ---------- Affichage de la section adaptée au type ---------- */
    switchType(type) {
        this.form.querySelectorAll('fieldset[data-type]').forEach((fieldset) => {
            const active = fieldset.dataset.type === type;
            fieldset.hidden = !active;
            fieldset.disabled = !active;   // les champs désactivés ne sont ni validés ni envoyés
        });

        // L'astérisque du message n'apparaît que pour un projet sur mesure
        const star = this.form.querySelector('#message-req');
        if (star) star.hidden = type !== 'custom_project';

        if (this.touched.has('message')) this.validateField('message');
        this.setStatus('', '');
    }

    /* ---------- Validation d'un champ ---------- */
    validateField(name) {
        const rule = this.rules[name];
        if (!rule || !this.isActive(name)) return true;

        const message = rule(this.getValue(name), this.form);
        this.showFieldState(name, message);
        return message === '';
    }

    showFieldState(name, message) {
        const element = this.form.elements[name];
        const input = element instanceof RadioNodeList ? element[0] : element;
        const field = input?.closest('.field');
        const errorEl = this.form.querySelector(`#${name}-error`);

        if (field) {
            field.classList.toggle('is-invalid', message !== '');
            field.classList.toggle('is-valid', message === '' && this.getValue(name) !== '');
        }
        if (input && !(element instanceof RadioNodeList)) {
            input.setAttribute('aria-invalid', String(message !== ''));
        }
        if (errorEl) errorEl.textContent = message;
    }

    /** Valide tous les champs actifs ; retourne la liste des champs en erreur. */
    validateAll() {
        return Object.keys(this.rules).filter((name) => {
            this.touched.add(name);
            return !this.validateField(name);
        });
    }

    /* ---------- Compteur de caractères du message ---------- */
    updateCharCount() {
        const counter = this.form.querySelector('#message-count');
        if (counter) counter.textContent = `${this.getValue('message').length} / 2000`;
    }

    setStatus(message, type) {
        this.status.textContent = message;
        this.status.classList.toggle('is-success', type === 'success');
        this.status.classList.toggle('is-error', type === 'error');
    }

    /* ---------- Soumission ---------- */
    async handleSubmit(event) {
        event.preventDefault();

        const invalid = this.validateAll();
        if (invalid.length > 0) {
            this.setStatus(`Veuillez corriger ${invalid.length} champ${invalid.length > 1 ? 's' : ''} avant l'envoi.`, 'error');
            const first = this.form.elements[invalid[0]];
            (first instanceof RadioNodeList ? first[0] : first)?.focus();
            return;
        }

        // Honeypot rempli : c'est un robot. On simule un succès sans rien envoyer.
        if (this.getValue('website') !== '') {
            this.setStatus('Merci ! Votre demande a bien été envoyée.', 'success');
            return;
        }

        this.submitBtn.disabled = true;
        this.submitBtn.textContent = 'Envoi en cours…';

        try {
            const response = await fetch(this.form.action, {
                method: 'POST',
                body: new FormData(this.form),           // les fieldsets désactivés sont exclus
                headers: { Accept: 'application/json' },
            });

            if (response.status === 404) {
                throw new Error('Le traitement serveur sera disponible en Phase 4 (api/submit-request.php).');
            }

            const data = await response.json();

            if (response.ok && data.success) {
                this.setStatus(data.message ?? 'Merci ! Votre demande a bien été envoyée. Réponse sous 48 h.', 'success');
                this.form.reset();
                this.touched.clear();
                this.form.querySelectorAll('.field').forEach((f) => f.classList.remove('is-valid', 'is-invalid'));
                this.switchType(this.getValue('request_type'));
                this.updateCharCount();
            } else {
                // Erreurs renvoyées par la validation serveur : { errors: { champ: message } }
                Object.entries(data.errors ?? {}).forEach(([name, message]) => this.showFieldState(name, message));
                this.setStatus(data.message ?? "L'envoi a échoué. Veuillez vérifier le formulaire.", 'error');
            }
        } catch (error) {
            this.setStatus(error.message.includes('Phase 4')
                ? error.message
                : 'Impossible de joindre le serveur. Vérifiez votre connexion et réessayez.', 'error');
        } finally {
            this.submitBtn.disabled = false;
            this.submitBtn.textContent = 'Envoyer ma demande';
        }
    }
}

/* ---------------------------------------------------------------
   4. Instanciation
   --------------------------------------------------------------- */
const requestForm = document.getElementById('request-form');
if (requestForm) {
    new FormValidator(requestForm, RULES);
}
