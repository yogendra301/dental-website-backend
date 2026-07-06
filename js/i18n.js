let currentLang = 'en';
let translations = {};
let langChangeCallbacks = [];

export async function initI18n(defaultLang) {
    // Priority: user's saved choice > clinic default > built-in fallback
    currentLang = localStorage.getItem('lang') || defaultLang || 'en';
    await loadTranslations(currentLang);
    applyTranslations();
    setupLangToggles();
    return translations;
}

export function getTranslations() {
    return translations;
}

export function onLangChange(callback) {
    langChangeCallbacks.push(callback);
}

async function loadTranslations(lang) {
    try {
        const res = await fetch(`/i18n/${lang}.json`);
        if (!res.ok) throw new Error('Failed to load translations');
        translations = await res.json();
        currentLang = lang;
        localStorage.setItem('lang', lang);
        // Trigger callbacks
        langChangeCallbacks.forEach(cb => cb(translations));
    } catch (e) {
        console.error(e);
    }
}

export function applyTranslations() {
    document.querySelectorAll('[data-i18n]').forEach(el => {
        const key = el.getAttribute('data-i18n');
        if (!translations[key]) return;

        if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
            if (el.placeholder) {
                el.placeholder = translations[key];
            } else {
                el.value = translations[key];
            }
            return;
        }

        // For elements with children, preserve child elements and replace text nodes
        if (el.children.length > 0) {
            el.childNodes.forEach(node => {
                if (node.nodeType === Node.TEXT_NODE) {
                    node.textContent = translations[key];
                }
            });
            return;
        }

        el.textContent = translations[key];
    });

    // Handle placeholder translations
    document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
        const key = el.getAttribute('data-i18n-placeholder');
        if (translations[key]) {
            el.placeholder = translations[key];
        }
    });
}

function setupLangToggles() {
    const toggles = document.querySelectorAll('.lang-toggle-btn');
    toggles.forEach(btn => {
        // Remove existing listener to avoid double triggers
        const newBtn = btn.cloneNode(true);
        btn.parentNode.replaceChild(newBtn, btn);
        
        newBtn.addEventListener('click', async () => {
            const nextLang = currentLang === 'en' ? 'hi' : 'en';
            await loadTranslations(nextLang);
            applyTranslations();
            updateToggleUI();
        });
    });
    updateToggleUI();
}

function updateToggleUI() {
    const toggles = document.querySelectorAll('.lang-toggle-btn');
    toggles.forEach(btn => {
        btn.innerHTML = currentLang === 'en' 
            ? '<i class="fa-solid fa-language mr-1.5"></i>हिन्दी' 
            : '<i class="fa-solid fa-language mr-1.5"></i>English';
    });
}
