import { resolveClinicConfig } from './resolve-config.js';
import { initI18n, applyTranslations, onLangChange } from './i18n.js';
import clientConfig from './client-config.js';

let clinicConfig = null;
let translations = {};
const API_BASE = clientConfig.apiBase;

// Resolves stored root-relative asset paths to full backend URLs.
// All img.src / video.src / poster assignments must go through this.
// Pattern: stored as /uploads/assets/clinic_001/logo.png → https://backend.com/uploads/assets/clinic_001/logo.png
const ASSET_ORIGIN = API_BASE.replace(/\/index\.php.*$/, '');
function assetUrl(path) {
    if (!path) return '';
    if (path.startsWith('http')) return path;
    return ASSET_ORIGIN + path;
}

document.addEventListener('DOMContentLoaded', async () => {
    // Resolve dynamic config
    try {
        clinicConfig = await resolveClinicConfig();
    } catch (e) {
        console.error('Resolve failed:', e);
        return;
    }

    // 1. Initialize Page Theme and Meta
    initTheme();
    
    // Initialize Translation switch (await ensures translations load before first render)
    translations = await initI18n(clinicConfig?.theme?.defaultLanguage);
    
    // Register callback to re-render dynamic content on language change
    onLangChange((newTranslations) => {
        translations = newTranslations;
        renderContactAndHours();
        renderDoctor();
        renderGallery();
    });
    
    // 2. Render Page Sections
    renderHeader();
    renderHero();
    
    // 3. Sparkle background glints in Hero
    initSparkles();

    renderServices();
    renderDoctor();
    if (clinicConfig.visibility_settings?.show_gallery !== false) {
        await renderGallery();
    }
    renderReviews();
    renderContactAndHours();
    applyVisibilitySettings();
    // Note: initI18n already calls applyTranslations internally once translations are loaded
    
    // 4. Initialize Booking Widget Logic
    initBookingWidget();

    // 5. Lightbox logic
    initLightbox();

    // 6. Animations and Polish
    initNavScroll();
    initMobileMenu();
    initScrollReveal();

    // 7. Lead capture mini-form logic
    initLeadForm();
    populateLeadServiceDropdown();
});

// =============================================
// NAV SCROLL SHADOW
// =============================================
function initNavScroll() {
    const nav = document.querySelector('nav');
    const onScroll = () => {
        if (window.scrollY > 30) {
            nav.classList.add('nav-scrolled');
        } else {
            nav.classList.remove('nav-scrolled');
        }
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
}

// =============================================
// MOBILE MENU TOGGLE
// =============================================
function initMobileMenu() {
    const btn = document.getElementById('mobile-menu-btn');
    const menu = document.getElementById('mobile-menu');
    if (!btn || !menu) return;

    btn.addEventListener('click', () => {
        const isOpen = menu.classList.toggle('open');
        // Animate hamburger bars into X
        const bars = btn.querySelectorAll('span');
        if (isOpen) {
            bars[0].style.transform = 'translateY(8px) rotate(45deg)';
            bars[1].style.opacity = '0';
            bars[2].style.transform = 'translateY(-8px) rotate(-45deg)';
        } else {
            bars[0].style.transform = '';
            bars[1].style.opacity = '';
            bars[2].style.transform = '';
        }
    });

    // Close menu when any link inside is clicked
    menu.querySelectorAll('a').forEach(a => {
        a.addEventListener('click', () => {
            menu.classList.remove('open');
            const bars = btn.querySelectorAll('span');
            bars[0].style.transform = '';
            bars[1].style.opacity = '';
            bars[2].style.transform = '';
        });
    });
}

// =============================================
// SCROLL REVEAL — Intersection Observer
// =============================================
function initScrollReveal() {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('revealed');
                // Trigger count-up when stats section reveals
                if (entry.target.classList.contains('stats-bar')) {
                    animateCountUps(entry.target);
                }
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

    document.querySelectorAll('.reveal, .reveal-stagger').forEach(el => observer.observe(el));
}

// =============================================
// COUNT-UP ANIMATION
// =============================================
function animateCountUps(container) {
    container.querySelectorAll('[data-count]').forEach(el => {
        const rawTarget = el.dataset.count;
        const isFloat = rawTarget.includes('.');
        const target = isFloat ? parseFloat(rawTarget) : parseInt(rawTarget, 10);
        const suffix = el.dataset.suffix || '';
        const duration = 1400;
        const start = performance.now();

        const tick = (now) => {
            const elapsed = now - start;
            const progress = Math.min(elapsed / duration, 1);
            // Ease out
            const eased = 1 - Math.pow(1 - progress, 3);
            const currentVal = eased * target;
            el.textContent = (isFloat ? currentVal.toFixed(1) : Math.round(currentVal)) + suffix;
            if (progress < 1) requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);
    });
}

// =============================================
// MAGNETIC CTA BUTTONS
// =============================================
function initMagneticButtons() {
    // Apply to all primary accent buttons that have the class
    document.querySelectorAll('.btn-magnetic').forEach(btn => {
        btn.addEventListener('mousemove', (e) => {
            const rect = btn.getBoundingClientRect();
            const cx = rect.left + rect.width / 2;
            const cy = rect.top + rect.height / 2;
            const dx = (e.clientX - cx) * 0.25;
            const dy = (e.clientY - cy) * 0.25;
            btn.style.transform = `translate(${dx}px, ${dy}px)`;
        });
        btn.addEventListener('mouseleave', () => {
            btn.style.transform = '';
        });
    });
}



// --- HELPER: Darken/Lighten Hex Color ---
function adjustColorBrightness(hex, percent) {
    hex = hex.replace(/^\s*#|\s*$/g, '');
    if(hex.length === 3) {
        hex = hex.replace(/(.)/g, '$1$1');
    }
    let r = parseInt(hex.substr(0, 2), 16),
        g = parseInt(hex.substr(2, 2), 16),
        b = parseInt(hex.substr(4, 2), 16);

    r = Math.min(255, Math.max(0, parseInt(r * (100 + percent) / 100)));
    g = Math.min(255, Math.max(0, parseInt(g * (100 + percent) / 100)));
    b = Math.min(255, Math.max(0, parseInt(b * (100 + percent) / 100)));

    const rHex = r.toString(16).padStart(2, '0');
    const gHex = g.toString(16).padStart(2, '0');
    const bHex = b.toString(16).padStart(2, '0');

    return `#${rHex}${gHex}${bHex}`;
}

// --- THEME INITIALIZATION ---
function initTheme() {
    document.title = `${clinicConfig.name} - ${clinicConfig.tagline}`;
    
    // Apply dynamic variables
    const root = document.documentElement;
    const accent = clinicConfig.theme.accentColor || '#2DD4BF';
    root.style.setProperty('--accent-color', accent);
    root.style.setProperty('--accent-hover', adjustColorBrightness(accent, -15));
    
    const radius = clinicConfig.theme.cardStyle === 'soft' ? '1rem' : '0.125rem';
    root.style.setProperty('--border-radius', radius);
    
    // Load google font dynamically
    const fontName = clinicConfig.theme.font || 'Outfit';
    const fontLink = document.createElement('link');
    fontLink.href = `https://fonts.googleapis.com/css2?family=${fontName.replace(/ /g, '+')}:wght@300;400;500;600;700&display=swap`;
    fontLink.rel = 'stylesheet';
    document.head.appendChild(fontLink);
    document.body.style.fontFamily = `'${fontName}', sans-serif`;
}

function applyVisibilitySettings() {
    const vis = clinicConfig?.visibility_settings || {};
    if (vis.show_stats_bar === false) {
        document.querySelectorAll('.stats-bar').forEach(el => el.classList.add('hidden'));
    }
    if (vis.show_services === false) {
        document.getElementById('services')?.classList.add('hidden');
    }
    if (vis.show_doctor_section === false) {
        document.getElementById('doctor')?.classList.add('hidden');
    }
    if (vis.show_gallery === false) {
        document.getElementById('gallery')?.classList.add('hidden');
    }
    if (vis.show_reviews === false) {
        document.getElementById('reviews')?.classList.add('hidden');
    }
    if (vis.show_booking_section === false) {
        document.getElementById('booking')?.classList.add('hidden');
    }
    if (vis.show_contact_section === false) {
        document.getElementById('contact')?.classList.add('hidden');
    }
    if (vis.show_lead_form === false) {
        document.getElementById('lead-form-section')?.classList.add('hidden');
    }
    if (vis.show_working_hours === false) {
        (document.getElementById('working-hours-card') || document.getElementById('working-hours-table'))?.classList.add('hidden');
    }
    if (vis.show_whatsapp_fab === false) {
        document.getElementById('whatsapp-fab')?.classList.add('hidden');
    }
    if (vis.show_pricing === false) {
        document.querySelectorAll('[data-price-display]').forEach(el => el.classList.add('hidden'));
    }

    const googleReviewBtnContainer = document.getElementById('google-review-btn-container');
    if (googleReviewBtnContainer) {
        if (vis.show_google_review_btn !== false && clinicConfig.google_review_link) {
            const googleReviewBtn = document.getElementById('google-review-btn');
            if (googleReviewBtn) {
                googleReviewBtn.href = clinicConfig.google_review_link;
            }
            googleReviewBtnContainer.classList.remove('hidden');
        } else {
            googleReviewBtnContainer.classList.add('hidden');
        }
    }
}

// --- HEADER RENDER ---
function renderHeader() {
    const navLogo = document.getElementById('nav-logo');
    const navTitle = document.getElementById('nav-title');
    const footerTitle = document.getElementById('footer-title');
    const footerTagline = document.getElementById('footer-tagline');
    const footerCopyrightName = document.getElementById('footer-copyright-name');
    const currentYear = document.getElementById('current-year');
    
    if (clinicConfig.logo) {
        navLogo.src = assetUrl(clinicConfig.logo);
        navLogo.classList.remove('hidden');
    }
    
    navTitle.textContent = clinicConfig.name;
    footerTitle.textContent = clinicConfig.name;
    footerTagline.textContent = clinicConfig.tagline;
    if (footerCopyrightName) footerCopyrightName.textContent = clinicConfig.name;
    currentYear.textContent = new Date().getFullYear();

    // Footer contact details
    const footerPhone = document.getElementById('footer-phone');
    const footerAddress = document.getElementById('footer-address');
    if (footerPhone) {
        footerPhone.textContent = clinicConfig.contact.phone;
        footerPhone.href = `tel:${clinicConfig.contact.phone}`;
    }
    if (footerAddress) {
        footerAddress.textContent = clinicConfig.contact.address;
    }

    // WhatsApp links (FAB + footer + contact btn)
    const waNumber = (clinicConfig.whatsapp?.clinicNumber || clinicConfig.contact.phone).replace(/\D/g, '');
    const waHref = `https://wa.me/${waNumber}`;
    const fab = document.getElementById('whatsapp-fab');
    const footerWa = document.getElementById('footer-whatsapp');
    if (fab) {
        fab.href = waHref;
        if (clinicConfig.visibility_settings?.show_whatsapp_fab === false) {
            fab.classList.add('hidden');
        } else {
            fab.classList.remove('hidden');
        }
    }
    if (footerWa) footerWa.href = waHref;
}

// --- HERO RENDER ---
function renderHero() {
    const heroContent = document.getElementById('hero-content');
    const layout = clinicConfig.theme.heroLayout || 'split';
    
    // Trust stats row shared across layouts
    const statsHTML = (dark = false) => `
        <div class="stats-bar ${dark ? 'stats-bar-dark' : ''} hero-reveal hero-reveal-delay-4 mt-2">
            ${(clinicConfig.hero.stats || []).map((stat, idx) => `
                ${idx > 0 ? '<div class="stat-divider"></div>' : ''}
                <div class="stat-item">
                    <span class="stat-number" data-count="${stat.value}" data-suffix="${stat.suffix}">${stat.value}${stat.suffix}</span>
                    <span class="stat-label">${stat.label}</span>
                </div>
            `).join('')}
        </div>
    `;

    let html = '';
    
    if (layout === 'split') {
        const rightMedia = clinicConfig.hero.heroVideo
          ? `<video 
               src="${assetUrl(clinicConfig.hero.heroVideo)}"
               autoplay muted loop playsinline
               poster="${assetUrl(clinicConfig.hero.heroImage)}"
               class="w-full h-[350px] sm:h-[480px] object-cover">
               <img src="${assetUrl(clinicConfig.hero.heroImage)}" 
                    class="w-full h-[350px] sm:h-[480px] object-cover">
             </video>`
          : `<img src="${assetUrl(clinicConfig.hero.heroImage)}" 
                  alt="${clinicConfig.name} Clinic" 
                  class="w-full h-[350px] sm:h-[480px] object-cover">`;

        html = `
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
                <div class="lg:col-span-6 space-y-6 sm:space-y-8 text-left">
                    ${clinicConfig.hero.badgeText ? `
                    <div class="hero-reveal hero-reveal-delay-1 inline-flex items-center gap-2 bg-teal-50 border border-teal-100 rounded-full px-4 py-1.5">
                        <span class="h-2 w-2 rounded-full bg-accent animate-pulse"></span>
                        <span class="text-xs font-bold text-accent uppercase tracking-wider">${clinicConfig.hero.badgeText}</span>
                    </div>` : ''}
                    <h1 class="hero-reveal hero-reveal-delay-2 text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight text-slate-900 leading-tight">
                        ${clinicConfig.hero.headline}
                    </h1>
                    <p class="hero-reveal hero-reveal-delay-3 text-lg sm:text-xl text-slate-600 max-w-xl">
                        ${clinicConfig.hero.subtext}
                    </p>
                    <div class="hero-reveal hero-reveal-delay-3 flex flex-wrap gap-4">
                        <a href="#booking" class="btn-magnetic bg-accent hover:bg-accentHover text-white px-8 py-3.5 rounded-theme font-bold shadow-lg shadow-teal-500/20 hover:shadow-teal-500/30 transition-theme text-base" data-i18n="hero_cta_booking">
                            Book Appointment
                        </a>
                        <a href="#services" class="border border-slate-200 hover:border-accent hover:text-accent text-slate-700 px-8 py-3.5 rounded-theme font-bold transition-theme text-base" data-i18n="hero_cta_services">
                            Our Services
                        </a>
                    </div>
                    ${statsHTML()}
                </div>
                <div class="lg:col-span-6 hero-reveal-img">
                    <div class="relative rounded-[2rem] overflow-hidden shadow-2xl">
                        ${rightMedia}
                        <!-- Floating badge overlay -->
                        ${clinicConfig.hero.floatingBadge ? `
                        <div class="absolute bottom-6 left-6 glass rounded-theme px-4 py-3 float-deco">
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-10 rounded-full bg-emerald-500 flex items-center justify-center text-white text-sm font-bold">✓</div>
                                <div>
                                    <p class="text-xs font-bold text-slate-900">${clinicConfig.hero.floatingBadge.title}</p>
                                    <p class="text-[10px] text-slate-500">${clinicConfig.hero.floatingBadge.subtitle}</p>
                                </div>
                            </div>
                        </div>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;
    } else if (layout === 'centered') {
        html = `
            <div class="text-center max-w-4xl mx-auto space-y-8">
                ${clinicConfig.hero.badgeText ? `
                <div class="hero-reveal hero-reveal-delay-1 inline-flex items-center gap-2 bg-teal-50 border border-teal-100 rounded-full px-4 py-1.5">
                    <span class="h-2 w-2 rounded-full bg-accent animate-pulse"></span>
                    <span class="text-xs font-bold text-accent uppercase tracking-wider">${clinicConfig.hero.badgeText}</span>
                </div>` : ''}
                <h1 class="hero-reveal hero-reveal-delay-2 text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight text-slate-900 leading-tight">
                    ${clinicConfig.hero.headline}
                </h1>
                <p class="hero-reveal hero-reveal-delay-3 text-lg sm:text-xl text-slate-600 max-w-2xl mx-auto">
                    ${clinicConfig.hero.subtext}
                </p>
                <div class="hero-reveal hero-reveal-delay-3 flex justify-center gap-4">
                    <a href="#booking" class="btn-magnetic bg-accent hover:bg-accentHover text-white px-8 py-3.5 rounded-theme font-bold shadow-lg shadow-teal-500/20 hover:shadow-teal-500/30 transition-theme text-base" data-i18n="hero_cta_booking">
                        Book Appointment
                    </a>
                </div>
                <div class="hero-reveal hero-reveal-delay-4 flex justify-center">
                    ${statsHTML()}
                </div>
                <div class="pt-4">
                    <div class="relative rounded-[2rem] overflow-hidden shadow-2xl max-w-5xl mx-auto hero-reveal-img">
                        <img src="${assetUrl(clinicConfig.hero.heroImage)}" alt="${clinicConfig.name} Clinic" class="w-full h-[300px] sm:h-[500px] object-cover">
                    </div>
                </div>
            </div>
        `;
    } else if (layout === 'fullbg') {
        html = `
            <div class="absolute inset-0 z-0">
                <img src="${assetUrl(clinicConfig.hero.heroImage)}" alt="${clinicConfig.name} Clinic" class="w-full h-full object-cover">
                <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-[2px]"></div>
            </div>
            <div class="relative z-10 text-center max-w-4xl mx-auto py-12 sm:py-20 text-white space-y-6 sm:space-y-8">
                <h1 class="hero-reveal hero-reveal-delay-1 text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight leading-tight">
                    ${clinicConfig.hero.headline}
                </h1>
                <p class="hero-reveal hero-reveal-delay-2 text-lg sm:text-xl text-slate-200 max-w-2xl mx-auto">
                    ${clinicConfig.hero.subtext}
                </p>
                <div class="hero-reveal hero-reveal-delay-3 pt-4">
                    <a href="#booking" class="btn-magnetic bg-accent hover:bg-accentHover text-white px-8 py-4 rounded-theme font-bold shadow-lg shadow-teal-500/20 hover:shadow-teal-500/30 transition-theme text-base inline-block" data-i18n="hero_cta_booking">
                        Book Appointment Now
                    </a>
                </div>
            </div>
        `;
    } else if (layout === 'journey') {
        const steps = clinicConfig.hero.journeySteps || [];
        const bgMedia = clinicConfig.hero.heroVideo
          ? `<video class="hero-journey-video" src="${assetUrl(clinicConfig.hero.heroVideo)}" autoplay muted loop playsinline></video>`
          : ``; // no video = pure dark background, no static fallback

        html = `
            ${bgMedia}
            <div class="hero-journey-overlay"></div>
            <div class="relative z-10 grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-12 items-start lg:items-center">
                <div id="journey-text-col" class="lg:col-span-7 space-y-6 sm:space-y-8 text-left">
                    ${clinicConfig.hero.badgeText ? `
                    <div class="hero-reveal hero-reveal-delay-1 inline-flex items-center gap-2 bg-white/10 border border-white/20 rounded-full px-4 py-1.5">
                        <span class="h-2 w-2 rounded-full bg-accent animate-pulse"></span>
                        <span class="text-xs font-bold text-accent uppercase tracking-wider">${clinicConfig.hero.badgeText}</span>
                    </div>` : ''}
                    <h1 class="hero-reveal hero-reveal-delay-2 text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight text-white leading-tight">
                        ${clinicConfig.hero.headline}
                    </h1>
                    <p class="hero-reveal hero-reveal-delay-3 text-lg sm:text-xl text-slate-200 max-w-xl">
                        ${clinicConfig.hero.subtext.includes('lifetime of care') ? 
                            '<span class="hidden sm:inline">From the first consultation</span><span class="inline sm:hidden">From the first visit</span><br class="block sm:hidden"> to a lifetime of care<span class="hidden sm:inline">' + clinicConfig.hero.subtext.substring(clinicConfig.hero.subtext.indexOf('lifetime of care') + 16) + '</span>' 
                            : clinicConfig.hero.subtext}
                    </p>
                    <div class="hero-reveal hero-reveal-delay-3 flex flex-wrap gap-4">
                        <a href="#booking" class="btn-magnetic bg-accent hover:bg-accentHover text-white px-8 py-3.5 rounded-theme font-bold shadow-lg shadow-teal-500/20 hover:shadow-teal-500/30 transition-theme text-base" data-i18n="hero_cta_booking">Book Appointment</a>
                        <a href="#services" class="border border-white/30 hover:border-accent hover:text-accent text-white px-8 py-3.5 rounded-theme font-bold transition-theme text-base" data-i18n="hero_cta_services">Our Services</a>
                    </div>
                    ${statsHTML(true)}
                </div>
                <div class="lg:col-span-5">
                    <div id="journey-track" class="relative space-y-3">
                        ${steps.map((s, i) => `
                            <div class="journey-card" data-index="${i}">
                                <div class="journey-card-icon">
                                    ${s.image ? `<img src="${assetUrl(s.image)}" alt="${s.label}">` : ''}
                                </div>
                                <span class="card-spark s1"></span><span class="card-spark alt s2"></span>
                                <span class="card-spark s3"></span><span class="card-spark alt s4"></span>
                                <span class="card-spark s5"></span><span class="card-spark alt s6"></span>
                                <span class="card-spark s7"></span><span class="card-spark alt s8"></span>
                                <span class="card-spark s9"></span><span class="card-spark s10"></span>
                                <span class="text-white font-bold text-sm tracking-wide uppercase">${i + 1}. ${s.label}</span>
                            </div>
                            ${i < steps.length - 1 ? `<div class="journey-connector"></div>` : ''}
                        `).join('')}
                    </div>
                </div>
            </div>
        `;
    }
    
    heroContent.innerHTML = html;

    // Wire up magnetic buttons after content is injected
    initMagneticButtons();

    if (layout === 'journey') {
        // Hero section: full-height dark bg while video loads
        const heroSection = document.getElementById('hero');
        heroSection.classList.remove('bg-white', 'pt-4', 'pb-16', 'py-16', 'sm:py-24', 'lg:py-32');
        heroSection.classList.add('lg:min-h-screen', 'flex', 'items-start', 'lg:items-center', 'pt-8', 'sm:pt-10', 'lg:pt-20', 'pb-10', 'lg:pb-8');
        heroSection.style.background = '#061520';
        document.querySelector('.hero-mesh').style.opacity = '0'; // blobs unneeded under video

        const syncMobileMediaHeight = () => {
            if (window.innerWidth >= 1024) {
                heroSection.style.removeProperty('--hero-media-h');
                return;
            }
            const textCol = document.getElementById('journey-text-col');
            if (textCol) {
                heroSection.style.setProperty('--hero-media-h', `${textCol.offsetHeight}px`);
            }
        };
        syncMobileMediaHeight();
        window.addEventListener('resize', syncMobileMediaHeight);

        // Card activation cycling — always runs
        requestAnimationFrame(() => requestAnimationFrame(() => initJourneyCardCycle()));

        const vid = heroContent.querySelector('video.hero-journey-video');
        if (vid) {
            vid.muted = true;
            vid.play().catch(e => console.warn('Video autoplay:', e));
        }
    }

    // Animate count-ups right away for hero stats (they're immediately visible)
    const heroStats = heroContent.querySelector('.stats-bar');
    if (heroStats) {
        // Delay slightly so the hero animation finishes first
        setTimeout(() => animateCountUps(heroStats), 700);
    }
}

function initJourneyCardCycle() {
    const track = document.getElementById('journey-track');
    if (!track) return;
    const cards = Array.from(track.querySelectorAll('.journey-card'));
    const connectors = Array.from(track.querySelectorAll('.journey-connector'));
    if (!cards.length) return;

    const CYCLE = 3500;
    const seg = CYCLE / cards.length;
    clearInterval(track._jInterval);
    let idx = 0;
    cards.forEach(c => c.classList.remove('active'));
    connectors.forEach(c => c.classList.remove('done'));

    track._jInterval = setInterval(() => {
        cards.forEach(c => c.classList.remove('active'));
        void track.offsetWidth; // force reflow so spark burst replays every cycle
        cards[idx].classList.add('active');
        connectors.forEach((c, i) => c.classList.toggle('done', i < idx));
        idx = (idx + 1) % cards.length;
    }, seg);
}



// --- SERVICES RENDER ---
function renderServices() {
    const servicesGrid = document.getElementById('services-grid');
    if (!servicesGrid) { console.warn('renderServices: #services-grid not found in DOM'); return; }

    const services = clinicConfig?.services || [];
    if (!services.length) return;

    servicesGrid.innerHTML = services.map((s, idx) => {
        const serviceNum = s.id ? s.id.replace(/\D/g, '') : (idx + 1);
        const imagePath = assetUrl(s.image || '');
        return `
            <div class="service-card rounded-2xl overflow-hidden shadow-sm hover:shadow-xl hover:-translate-y-1 transition-theme cursor-pointer border border-slate-100"
                 onclick="document.getElementById('booking').scrollIntoView({ behavior: 'smooth' })">
                <img src="${imagePath}" alt="${s.name}" class="w-full h-auto object-cover" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                <div class="w-full aspect-video hidden items-center justify-center bg-slate-100 text-slate-500 text-sm font-semibold px-4 text-center">${s.name}</div>
            </div>
        `;
    }).join('');
}

// --- DOCTOR RENDER (compact horizontal card) ---
function renderDoctor() {
    const doctorContainer = document.getElementById('doctor-container');
    if (!doctorContainer) { console.warn('renderDoctor: #doctor-container not found in DOM'); return; }
    if (!clinicConfig.doctors || clinicConfig.doctors.length === 0) return;
    
    const docs = clinicConfig.doctors || [];
    const isMulti = docs.length > 1;
    const doc = docs[0];
    
    doctorContainer.innerHTML = isMulti 
        ? `<div class="grid grid-cols-1 md:grid-cols-2 gap-6">${docs.map(d => `
            <div class="bg-white p-4 rounded-theme border border-slate-100 shadow-sm flex items-center gap-4">
                <img src="${assetUrl(d.photo)}" alt="${d.name}" class="w-20 h-20 rounded-full object-cover border-2 border-accent/20">
                <div>
                    <h4 class="font-bold text-slate-900">${d.name}</h4>
                    <p class="text-xs text-accent font-semibold">${d.qualification}</p>
                </div>
            </div>
        `).join('')}</div>`
        : `
        <!-- Single doctor layout with description -->
        <div class="flex flex-col lg:flex-row items-start gap-8 lg:gap-10 w-full">
            <!-- Left: Photo + Identity + Credentials -->
            <div class="w-full lg:w-2/5 max-w-[320px] mx-auto lg:mx-0 flex-shrink-0 space-y-4">
                <div class="relative rounded-theme overflow-hidden ring-4 ring-accent/20 shadow-xl shadow-accent/10">
                    <img src="${assetUrl(docs[0].photo)}" alt="${docs[0].name}" class="w-full aspect-[4/5] object-cover">
                    <!-- Floating stat card (compact) — now inside relative wrapper so absolute positioning works -->
                    <div class="absolute bottom-2 right-2 glass rounded-xl px-3 py-2.5 float-deco shadow-lg border border-white/60">
                        <div class="flex items-center gap-2">
                            <div class="h-8 w-8 rounded-full bg-accent flex items-center justify-center text-white text-xs font-bold">★</div>
                            <div>
                                <p class="text-[11px] font-black text-slate-900">${translations.doctor_patients || '500+ Patients'}</p>
                                <p class="text-[9px] text-slate-500">${translations.doctor_treated || 'Treated Successfully'}</p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Right: Bio only (long-form content) -->
            <div class="w-full lg:w-3/5 space-y-4">
                            <!-- Identity block (always visible, fills left column) -->
                <div class="space-y-1">
                    <span class="section-tag" data-i18n="section_doctor_tag">Meet Our Expert</span>
                    <h3 class="text-xl lg:text-2xl font-extrabold text-slate-900 tracking-tight">${docs[0].name}</h3>
                    <p class="text-accent font-semibold text-sm lg:text-base">${docs[0].qualification}</p>
                </div>
                <!-- Doctor description box (if any) -->
                ${doc.description ? `
                <div class="bg-white p-4 rounded-theme border border-slate-100 shadow-sm">
                    <p class="text-xs text-slate-600 leading-relaxed">${doc.description}</p>
                </div>
                ` : ''}
                <!-- Credentials (compact chips — belong with photo card, not under bio) -->
                <div class="border-t border-slate-100 pt-3 space-y-2">
                    ${(doc.credentials || []).map((cred) => `
                        <div class="flex items-center gap-2.5 text-slate-700 border-l-2 border-accent pl-3">
                            <span class="text-accent font-bold text-xs"><i class="fa-solid fa-circle-check"></i></span>
                            <span class="font-semibold text-sm">${cred}</span>
                        </div>
                    `).join('')}
                </div>    
            <div class="lg:hidden">
                    <span class="section-tag" data-i18n="section_doctor_tag">Meet Our Expert</span>
                    <h4 class="text-base font-bold text-slate-500" data-i18n="section_doctor_title">Principal Dentist</h4>
                </div>

                <!-- Bio -->
                <div class="relative">
                    <span class="absolute -top-3 -left-2 text-[4rem] font-black leading-none select-none pointer-events-none" style="color: var(--accent-color); opacity: 0.12;">&ldquo;</span>
                    <p class="relative text-base text-slate-600 leading-relaxed font-light pl-3">
                        ${doc.bio}
                    </p>
                </div>
            </div>
        </div>
    `;
}

// --- GALLERY RENDER ---
async function renderGallery() {
    const galleryGrid = document.getElementById('gallery-grid');
    if (!galleryGrid) { console.warn('renderGallery: #gallery-grid not found in DOM'); return; }
    const categories = [
        translations.gallery_category_treatment || 'Treatment Room',
        translations.gallery_category_reception || 'Reception',
        translations.gallery_category_waiting || 'Waiting Area',
        translations.gallery_category_sterilization || 'Sterilization Unit',
        translations.gallery_category_consultation || 'Consultation Room',
        translations.gallery_category_xray || 'X-Ray Room'
    ];
    const classes = ['gallery-item-large', 'gallery-item-sm-1', 'gallery-item-sm-2', 'gallery-item-wide'];
    let html = '';

    try {
        const res = await fetch(`${API_BASE}/api/gallery?clinic_username=${clinicConfig.username}`);
        let items = [];
        if (res.ok) {
            items = await res.json();
        }
        const galleryItems = items.length > 0 ? items : (clinicConfig.gallery || []);
        galleryItems.sort((a, b) => {
            const aVal = (a.type === 'before_after' || a.type === 'before-after') ? 0 : 1;
            const bVal = (b.type === 'before_after' || b.type === 'before-after') ? 0 : 1;
            if (aVal !== bVal) return aVal - bVal;
            return (a.sort_order || 999) - (b.sort_order || 999);
        });
        galleryItems.forEach((item, idx) => {
            const layoutClass = classes[idx] || '';
            const category = categories[idx] || `Gallery ${idx + 1}`;

            // Handle object style vs simple string string config compatibility
            const type = item.type || 'single';
            const imgUrl = assetUrl(item.image_url || item.image || (typeof item === 'string' ? item : ''));

            if (type === 'before_after' || type === 'before-after') {
                html += `
                    <div class="gallery-item ${layoutClass} before-after-container relative rounded-theme overflow-hidden shadow-md cursor-ew-resize hover:shadow-xl transition-theme min-h-[220px] select-none">
                        <!-- Before Image -->
                        <img src="${assetUrl(item.before_url || item.before)}" alt="Before" class="absolute inset-0 w-full h-full object-cover">

                        <!-- After Image Wrapper -->
                        <div class="after-container absolute inset-0 w-1/2 overflow-hidden border-r-2 border-white pointer-events-none">
                            <img src="${assetUrl(item.after_url || item.after)}" alt="After" class="absolute inset-0 w-full h-full object-cover max-w-none">
                        </div>

                        <!-- Label Badge -->
                        <div class="absolute bottom-4 left-4 z-10">
                            <span class="glass text-[10px] font-extrabold text-slate-900 px-3 py-1.5 rounded-full uppercase tracking-wider shadow-md">${item.caption || item.label || 'Before / After'}</span>
                        </div>

                        <!-- Center handle indicator -->
                        <div class="slider-handle absolute top-0 bottom-0 w-0.5 bg-white pointer-events-none left-1/2">
                            <span class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 h-6 w-6 rounded-full bg-white text-slate-800 flex items-center justify-center text-[10px] shadow border border-slate-200"><i class="fa-solid fa-arrows-left-right"></i></span>
                        </div>
                    </div>
                `;
            } else {
                html += `
                    <div class="gallery-item ${layoutClass} group relative rounded-theme overflow-hidden shadow-md cursor-pointer hover:shadow-xl transition-theme">
                        <img src="${imgUrl}" alt="${category}" class="w-full h-full object-cover">
                        <!-- Gradient overlay slides up on hover -->
                        <div class="gallery-overlay flex flex-col justify-end p-4">
                            <div class="flex items-center justify-between">
                                <!-- Category pill -->
                                <span class="glass text-xs font-bold text-slate-900 px-3 py-1 rounded-full">${category}</span>
                                <!-- View icon -->
                                <span class="bg-white/90 text-slate-900 h-9 w-9 rounded-full flex items-center justify-center shadow-md text-sm">
                                    <i class="fa-solid fa-magnifying-glass-plus"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                `;
            }
        });
    } catch (e) {
        console.warn('Gallery fetch failed, using fallback config', e);
            const galleryItems = clinicConfig.gallery || [];
        galleryItems.sort((a, b) => {
            const aVal = (a.type === 'before_after' || a.type === 'before-after') ? 0 : 1;
            const bVal = (b.type === 'before_after' || b.type === 'before-after') ? 0 : 1;
            if (aVal !== bVal) return aVal - bVal;
            return (a.sort_order || 999) - (b.sort_order || 999);
        });
        galleryItems.forEach((item, idx) => {
            const layoutClass = classes[idx] || '';
            const category = categories[idx] || `Gallery ${idx + 1}`;
            const type = item.type || 'single';
            const imgUrl = assetUrl(item.image_url || item.image || (typeof item === 'string' ? item : ''));
            if (type === 'before_after' || type === 'before-after') {
                html += `
                    <div class="gallery-item ${layoutClass} before-after-container relative rounded-theme overflow-hidden shadow-md cursor-ew-resize hover:shadow-xl transition-theme min-h-[220px] select-none">
                        <img src="${assetUrl(item.before_url || item.before)}" alt="Before" class="absolute inset-0 w-full h-full object-cover">
                        <div class="after-container absolute inset-0 w-1/2 overflow-hidden border-r-2 border-white pointer-events-none">
                            <img src="${assetUrl(item.after_url || item.after)}" alt="After" class="absolute inset-0 w-full h-full object-cover max-w-none">
                        </div>
                        <div class="absolute bottom-4 left-4 z-10">
                            <span class="glass text-[10px] font-extrabold text-slate-900 px-3 py-1.5 rounded-full uppercase tracking-wider shadow-md">${item.caption || item.label || 'Before / After'}</span>
                        </div>
                        <div class="slider-handle absolute top-0 bottom-0 w-0.5 bg-white pointer-events-none left-1/2">
                            <span class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 h-6 w-6 rounded-full bg-white text-slate-800 flex items-center justify-center text-[10px] shadow border border-slate-200"><i class="fa-solid fa-arrows-left-right"></i></span>
                        </div>
                    </div>
                `;
            } else {
                html += `
                    <div class="gallery-item ${layoutClass} group relative rounded-theme overflow-hidden shadow-md cursor-pointer hover:shadow-xl transition-theme">
                        <img src="${imgUrl}" alt="${category}" class="w-full h-full object-cover">
                        <div class="gallery-overlay flex flex-col justify-end p-4">
                            <div class="flex items-center justify-between">
                                <span class="glass text-xs font-bold text-slate-900 px-3 py-1 rounded-full">${category}</span>
                                <span class="bg-white/90 text-slate-900 h-9 w-9 rounded-full flex items-center justify-center shadow-md text-sm">
                                    <i class="fa-solid fa-magnifying-glass-plus"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                `;
            }
        });
    }

    galleryGrid.innerHTML = html;
    
    // Setup slider listeners
    document.querySelectorAll('.before-after-container').forEach(container => {
        const afterCont = container.querySelector('.after-container');
        const handle = container.querySelector('.slider-handle');
        const afterImg = afterCont.querySelector('img');
        
        const updateWidth = (clientX) => {
            const rect = container.getBoundingClientRect();
            let pct = ((clientX - rect.left) / rect.width) * 100;
            pct = Math.max(0, Math.min(100, pct));
            
            // Set clipper width
            afterCont.style.width = `${pct}%`;
            if (handle) handle.style.left = `${pct}%`;
            
            // Make sure the image keeps its full width bounding
            afterImg.style.width = `${rect.width}px`;
            afterImg.style.height = `${rect.height}px`;
        };

        // Resize observer to align image width inside clipper
        new ResizeObserver(entries => {
            for (let entry of entries) {
                afterImg.style.width = `${entry.contentRect.width}px`;
                afterImg.style.height = `${entry.contentRect.height}px`;
            }
        }).observe(container);

        container.addEventListener('mousemove', (e) => updateWidth(e.clientX));
        container.addEventListener('touchmove', (e) => {
            if (e.touches && e.touches[0]) {
                updateWidth(e.touches[0].clientX);
            }
        });
    });
}

// --- REVIEWS RENDER ---
function renderReviews() {
    const track = document.getElementById('reviews-track');
    if (!track) return;

    const reviews = clinicConfig.reviews || [
        { name: "Priya Sharma", text: "Excellent experience! Dr. Sharma was very gentle and explained everything clearly. My root canal was completely painless." },
        { name: "Rahul Mehta", text: "Best dental clinic in the area. The staff is friendly and the clinic is spotless. Highly recommend for families." },
        { name: "Anjali Verma", text: "I was terrified of dentists but they made me feel so comfortable. My smile transformation has boosted my confidence!" },
        { name: "Vikram Patel", text: "Very professional team. Got my teeth cleaned and they look amazing. Booking was super easy through the website." },
        { name: "Sunita Rao", text: "Wonderful clinic with modern equipment. My kids love coming here which says a lot! Great with children." },
        { name: "Arjun Nair", text: "Affordable prices with top quality care. The follow-up after my procedure showed they genuinely care about patients." },
        { name: "Deepika Singh", text: "Had cosmetic dentistry done here. The results exceeded my expectations. Very skilled doctors and caring staff." },
        { name: "Manoj Kumar", text: "Quick appointments, no long waits. The WhatsApp confirmation system is very convenient. Will keep coming back." }
    ];

    // Duplicate for infinite scroll
    const all = [...reviews, ...reviews];
    track.innerHTML = all.map(r => `
        <div class="review-card flex-shrink-0 w-72 bg-slate-50 border border-slate-100 rounded-2xl p-6 shadow-sm">
            <div class="flex gap-1 mb-3">
                ${[1, 2, 3, 4, 5].map(n => {
                    const active = n <= (r.rating || 5);
                    return `<svg class="w-4 h-4 ${active ? 'text-yellow-400 fill-yellow-400' : 'text-slate-200 fill-slate-200'}" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>`;
                }).join('')}
            </div>
            <p class="text-slate-600 text-sm leading-relaxed mb-4">"${r.text}"</p>
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-full flex items-center justify-center text-white font-bold text-sm" style="background: var(--accent-color)">${r.name.charAt(0)}</div>
                <span class="font-semibold text-slate-800 text-sm">${r.name}</span>
            </div>
        </div>
    `).join('');
}

// --- CONTACT & HOURS RENDER ---
function renderContactAndHours() {
    // Phone / WhatsApp
    const phone = clinicConfig.contact.phone;
    const waNumber = (clinicConfig.whatsapp?.clinicNumber || phone).replace(/\D/g, '');
    const waHref = `https://wa.me/${waNumber}?text=Hi%2C%20I%20would%20like%20to%20book%20an%20appointment`;
    const contactWaBtn = document.getElementById('contact-whatsapp-btn');
    const contactPhoneText = document.getElementById('contact-phone-text');
    if (contactWaBtn) { contactWaBtn.href = waHref; }
    if (contactPhoneText) { contactPhoneText.textContent = `WhatsApp us: ${phone}`; }

    document.getElementById('contact-address').textContent = clinicConfig.contact.address;
    
    // Map: set src from clinicConfig if available
    const mapEl = document.getElementById('contact-map');
    const mapUrl = clinicConfig.contact?.mapEmbedUrl || clinicConfig.contact_map_url || '';
    if (mapEl && mapUrl) mapEl.src = mapUrl;

    // Map overlay name card
    const mapName = document.getElementById('map-clinic-name');
    const mapAddr = document.getElementById('map-clinic-address');
    if (mapName) mapName.textContent = clinicConfig.name;
    if (mapAddr) mapAddr.textContent = clinicConfig.contact.address;
    
    let html = '';
    const hoursTable = document.getElementById('working-hours-table');
    if (!hoursTable) { console.warn('renderContactAndHours: #working-hours-table not found in DOM'); return; }
    
    const daysNameMap = {
        mon: translations.day_monday || 'Monday',
        tue: translations.day_tuesday || 'Tuesday',
        wed: translations.day_wednesday || 'Wednesday',
        thu: translations.day_thursday || 'Thursday',
        fri: translations.day_friday || 'Friday',
        sat: translations.day_saturday || 'Saturday',
        sun: translations.day_sunday || 'Sunday'
    };

    // Determine today's day key
    const DAY_KEYS = ['sun','mon','tue','wed','thu','fri','sat'];
    const todayKey = DAY_KEYS[new Date().getDay()];
    
    const orderedDays = ['mon','tue','wed','thu','fri','sat','sun'];
    orderedDays.forEach(day => {
        const hours = (clinicConfig.working_hours || clinicConfig.workingHours || {})[day];
        const dayLabel = daysNameMap[day] || day;
        const isToday = day === todayKey;
        
        let hoursLabel = '';
        let statusDot = '';
        if (hours) {
            const formatTime = (timeStr) => {
                const [h, m] = timeStr.split(':');
                const hour = parseInt(h);
                const ampm = hour >= 12 ? 'PM' : 'AM';
                const formattedHour = hour % 12 || 12;
                return `${formattedHour}:${m} ${ampm}`;
            };
            hoursLabel = `${formatTime(hours.open)} – ${formatTime(hours.close)}`;
            if (isToday) statusDot = `<span class="inline-flex items-center gap-1 text-emerald-600 text-[10px] font-bold"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse inline-block"></span>${translations.status_open_today || 'Open Today'}</span>`;
        } else {
            hoursLabel = `<span class="text-rose-500 font-semibold uppercase text-xs">${translations.status_closed || 'Closed'}</span>`;
            if (isToday) statusDot = `<span class="inline-flex items-center gap-1 text-rose-500 text-[10px] font-bold"><span class="h-1.5 w-1.5 rounded-full bg-rose-500 inline-block"></span>${translations.status_closed_today || 'Closed Today'}</span>`;
        }
        
        const todayClasses = isToday
            ? 'bg-accent/10 rounded-lg px-3 font-bold text-slate-900 border border-accent/20'
            : 'px-3 border-b border-slate-50 last:border-0 text-slate-600';

        html += `
            <div class="flex justify-between items-center py-2 ${todayClasses}">
                <div>
                    <span class="font-${isToday ? 'bold' : 'medium'}">${dayLabel}</span>
                    ${isToday ? `<div class="mt-0.5">${statusDot}</div>` : ''}
                </div>
                <span>${hoursLabel}</span>
            </div>
        `;
    });
    
    hoursTable.innerHTML = html;
}

// --- BOOKING WIDGET LOGIC ---
function initBookingWidget() {
    let currentStep = 1;
    let selectedService = null;
    let selectedDate = '';
    let selectedSlot = '';
    
    const prevBtn = document.getElementById('prev-btn');
    const nextBtn = document.getElementById('next-btn');

    const formatTime12 = (timeStr) => {
        const [h, m] = timeStr.split(':');
        const hour = parseInt(h);
        const ampm = hour >= 12 ? 'PM' : 'AM';
        const formattedHour = hour % 12 || 12;
        return `${formattedHour}:${m} ${ampm}`;
    };

    // Sidebar update helper
    function updateSidebar() {
        const svcBlock = document.getElementById('sidebar-service-block');
        const dateBlock = document.getElementById('sidebar-date-block');
        const slotBlock = document.getElementById('sidebar-slot-block');
        const priceBlock = document.getElementById('sidebar-price-block');

        if (selectedService) {
            document.getElementById('sidebar-service').textContent = selectedService.name;
            document.getElementById('sidebar-duration').textContent = `${selectedService.durationMin} mins`;
            document.getElementById('sidebar-price').textContent = selectedService.priceDisplay;
            svcBlock.classList.remove('opacity-40');
            priceBlock.classList.remove('opacity-40');
        } else {
            svcBlock.classList.add('opacity-40');
            priceBlock.classList.add('opacity-40');
        }

        if (selectedDate) {
            const d = new Date(selectedDate);
            document.getElementById('sidebar-date').textContent = d.toLocaleDateString('en-IN', { weekday:'short', day:'numeric', month:'short' });
            dateBlock.classList.remove('opacity-40');
        } else {
            document.getElementById('sidebar-date').textContent = '—';
            dateBlock.classList.add('opacity-40');
        }

        if (selectedSlot) {
            document.getElementById('sidebar-slot').textContent = formatTime12(selectedSlot);
            slotBlock.classList.remove('opacity-40');
        } else {
            document.getElementById('sidebar-slot').textContent = '—';
            slotBlock.classList.add('opacity-40');
        }
    }

    // Render Service List in step 1 - show exactly 3 specified services + custom dropdown
    const servicesList = document.getElementById('booking-services-list');
    let servicesHtml = '';
    
    // Find these three main services
    const svc1 = clinicConfig.services.find(s => s.id === 'svc_1') || { id: 'svc_1', name: 'Tooth Pain & Checkup', durationMin: 30 };
    const svc5 = clinicConfig.services.find(s => s.id === 'svc_5') || { id: 'svc_5', name: 'Root Canal Treatment (RCT)', durationMin: 60 };
    const svc7 = clinicConfig.services.find(s => s.id === 'svc_7') || { id: 'svc_7', name: 'Cleaning & Polishing', durationMin: 60 };
    const mainServices = [svc1, svc5, svc7];

    servicesHtml += `<div class="grid grid-cols-2 gap-3">`;
    mainServices.forEach(svc => {
        servicesHtml += `
            <div data-id="${svc.id}" class="booking-service-opt border border-slate-300 bg-white/95 hover:border-accent p-5 rounded-theme flex flex-col items-center justify-center cursor-pointer transition-theme text-center shadow-sm hover:shadow-md hover:bg-slate-50/50">
                <p class="font-bold text-slate-900 text-sm">${svc.name}</p>
                <p class="text-xs text-slate-500 mt-1">${svc.durationMin} mins</p>
                <div class="opt-indicator h-5 w-5 rounded-full border-2 border-slate-300 flex items-center justify-center mt-2">
                    <div class="h-2.5 w-2.5 rounded-full bg-accent scale-0 transition-transform"></div>
                </div>
            </div>
        `;
    });

    // 4th slot: More Services dropdown card
    servicesHtml += `
        <div id="booking-dropdown-service-card" class="relative border border-slate-300 bg-white/95 hover:border-accent p-5 rounded-theme flex flex-col items-center justify-center cursor-pointer transition-theme text-center shadow-sm hover:shadow-md hover:bg-slate-50/50">
            <p id="booking-dropdown-title" class="font-bold text-slate-900 text-sm">More Services</p>
            <p id="booking-dropdown-subtitle" class="text-xs text-slate-500 mt-1">View all our services</p>
            <div class="mt-2 text-slate-400">
                <i id="booking-dropdown-arrow" class="fa-solid fa-chevron-down text-xs transition-transform duration-200"></i>
            </div>
            <!-- Custom dropdown panel -->
            <div id="booking-services-dropdown-list" class="hidden absolute top-full left-0 right-0 mt-1.5 bg-white border border-slate-200 rounded-theme shadow-xl z-50 max-h-60 overflow-y-auto py-1 text-left">
    `;
    
    clinicConfig.services.forEach(svc => {
        servicesHtml += `
                <div data-id="${svc.id}" class="booking-dropdown-item px-4 py-2.5 hover:bg-teal-50/30 border-b border-slate-100 last:border-0 cursor-pointer flex justify-between items-center transition-colors">
                    <div>
                        <p class="font-semibold text-slate-800 text-sm">${svc.name}</p>
                        <p class="text-xs text-slate-400 mt-0.5">${svc.durationMin} mins</p>
                    </div>
                </div>
        `;
    });

    servicesHtml += `
            </div>
        </div>
    `;

    servicesHtml += `</div>`; // Close grid
    servicesHtml += `
        <div id="booking-custom-service" class="border border-dashed border-slate-400 bg-white/95 p-4 rounded-theme cursor-pointer transition-theme mt-3 shadow-sm hover:shadow-md hover:border-accent">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="font-bold text-slate-900">Other / Not Listed</p>
                    <p class="text-xs text-slate-500">Tell us what you need and we’ll help.</p>
                </div>
                <div class="opt-indicator h-5 w-5 rounded-full border-2 border-slate-300 flex items-center justify-center">
                    <div class="h-2.5 w-2.5 rounded-full bg-accent scale-0 transition-transform"></div>
                </div>
            </div>
            <input id="booking-custom-service-input" type="text" placeholder="Describe your need..." class="mt-3 hidden w-full border border-slate-200 rounded-theme px-3 py-2 text-sm text-slate-700 outline-none focus:border-accent">
        </div>
    `;
    servicesList.innerHTML = servicesHtml;

    // Set up step 1 service selector clicks
    const serviceOpts = document.querySelectorAll('.booking-service-opt');
    const customServiceCard = document.getElementById('booking-custom-service');
    const customServiceInput = document.getElementById('booking-custom-service-input');
    const dropdownCard = document.getElementById('booking-dropdown-service-card');
    const dropdownList = document.getElementById('booking-services-dropdown-list');
    const dropdownArrow = document.getElementById('booking-dropdown-arrow');
    const dropdownTitle = document.getElementById('booking-dropdown-title');
    const dropdownSubtitle = document.getElementById('booking-dropdown-subtitle');

    // Reset dropdown card visual state
    function resetDropdownCard() {
        if (dropdownCard) {
            dropdownCard.classList.remove('border-accent', 'bg-teal-50/20');
            dropdownTitle.textContent = 'More Services';
            dropdownSubtitle.textContent = 'View all our services';
        }
    }

    serviceOpts.forEach(opt => {
        opt.addEventListener('click', () => {
            serviceOpts.forEach(o => {
                o.classList.remove('border-accent', 'bg-teal-50/20');
                o.querySelector('.opt-indicator').classList.remove('border-accent');
                o.querySelector('.opt-indicator div').classList.add('scale-0');
            });
            if (customServiceCard) {
                customServiceCard.classList.remove('border-accent', 'bg-teal-50/20');
                customServiceCard.querySelector('.opt-indicator').classList.remove('border-accent');
                customServiceCard.querySelector('.opt-indicator div').classList.add('scale-0');
            }
            resetDropdownCard();

            opt.classList.add('border-accent', 'bg-teal-50/20');
            opt.querySelector('.opt-indicator').classList.add('border-accent');
            opt.querySelector('.opt-indicator div').classList.remove('scale-0');
            if (customServiceInput) {
                customServiceInput.classList.add('hidden');
                customServiceInput.value = '';
            }

            selectedService = clinicConfig.services.find(s => s.id === opt.dataset.id);
            updateSidebar();
            validateStep();
        });
    });

    if (customServiceCard && customServiceInput) {
        customServiceCard.addEventListener('click', () => {
            serviceOpts.forEach(o => {
                o.classList.remove('border-accent', 'bg-teal-50/20');
                o.querySelector('.opt-indicator').classList.remove('border-accent');
                o.querySelector('.opt-indicator div').classList.add('scale-0');
            });
            resetDropdownCard();

            customServiceCard.classList.add('border-accent', 'bg-teal-50/20');
            customServiceCard.querySelector('.opt-indicator').classList.add('border-accent');
            customServiceCard.querySelector('.opt-indicator div').classList.remove('scale-0');
            customServiceInput.classList.remove('hidden');
            customServiceInput.focus();
            selectedService = { id: 'custom', name: '', durationMin: 0, priceDisplay: 'Custom' };
            updateSidebar();
            validateStep();
        });
        customServiceInput.addEventListener('input', () => {
            selectedService.name = customServiceInput.value.trim();
            updateSidebar();
            validateStep();
        });
    }

    // Toggle dropdown card click
    if (dropdownCard) {
        dropdownCard.addEventListener('click', (e) => {
            if (e.target.closest('#booking-services-dropdown-list')) {
                return;
            }
            const isHidden = dropdownList.classList.contains('hidden');
            if (isHidden) {
                dropdownList.classList.remove('hidden');
                dropdownArrow.classList.add('rotate-180');
            } else {
                dropdownList.classList.add('hidden');
                dropdownArrow.classList.remove('rotate-180');
            }
        });

        // Dropdown item selection click
        const dropdownItems = document.querySelectorAll('.booking-dropdown-item');
        dropdownItems.forEach(item => {
            item.addEventListener('click', (e) => {
                e.stopPropagation();
                // Deselect standard options
                serviceOpts.forEach(o => {
                    o.classList.remove('border-accent', 'bg-teal-50/20');
                    o.querySelector('.opt-indicator').classList.remove('border-accent');
                    o.querySelector('.opt-indicator div').classList.add('scale-0');
                });
                // Deselect custom options
                if (customServiceCard) {
                    customServiceCard.classList.remove('border-accent', 'bg-teal-50/20');
                    customServiceCard.querySelector('.opt-indicator').classList.remove('border-accent');
                    customServiceCard.querySelector('.opt-indicator div').classList.add('scale-0');
                }
                if (customServiceInput) {
                    customServiceInput.classList.add('hidden');
                    customServiceInput.value = '';
                }

                // Select this service
                const svc = clinicConfig.services.find(s => s.id === item.dataset.id);
                if (svc) {
                    selectedService = svc;
                    dropdownTitle.textContent = svc.name;
                    dropdownSubtitle.textContent = `${svc.durationMin} mins`;
                    dropdownCard.classList.add('border-accent', 'bg-teal-50/20');
                }

                dropdownList.classList.add('hidden');
                dropdownArrow.classList.remove('rotate-180');
                updateSidebar();
                validateStep();
            });
        });

        // Close when clicking away
        document.addEventListener('click', (e) => {
            if (!dropdownCard.contains(e.target)) {
                dropdownList.classList.add('hidden');
                dropdownArrow.classList.remove('rotate-180');
            }
        });
    }
    
    // When "Book Now" clicked on services card grid above
    document.querySelectorAll('.service-book-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const svcId = btn.dataset.serviceId;
            const correspondingOpt = document.querySelector(`.booking-service-opt[data-id="${svcId}"]`);
            if (correspondingOpt) {
                correspondingOpt.click();
                document.getElementById('booking').scrollIntoView({ behavior: 'smooth' });
                goToStep(2);
            }
        });
    });

    // Date picker setup
    const dateInput = document.getElementById('booking-date');
    const getLocalDate = () => {
        const now = new Date();
        const offset = now.getTimezoneOffset();
        const local = new Date(now.getTime() - offset * 60000);
        return local.toISOString().split('T')[0];
    };
    const today = getLocalDate();
    dateInput.min = today;
    const quickDates = document.getElementById('booking-quick-dates');
    if (quickDates) {
        quickDates.querySelectorAll('button').forEach(btn => {
            btn.addEventListener('click', () => {
                const delta = parseInt(btn.dataset.delta || '0', 10);
                const d = new Date();
                d.setDate(d.getDate() + delta);
                const offset = d.getTimezoneOffset();
                const local = new Date(d.getTime() - offset * 60000);
                dateInput.value = local.toISOString().split('T')[0];
                selectedDate = dateInput.value;
                selectedSlot = '';
                updateSidebar();
                renderTimeSlots();
                validateStep();
            });
        });
    }
    
    dateInput.addEventListener('change', (e) => {
        selectedDate = e.target.value;
        selectedSlot = '';
        updateSidebar();
        renderTimeSlots();
        validateStep();
    });
    
    // Render time slots dynamically from API
    async function renderTimeSlots() {
        const slotsGrid = document.getElementById('booking-slots-grid');
        const noSlotsMsg = document.getElementById('no-slots-msg');
        slotsGrid.innerHTML = '';
        
        if (!selectedDate) {
            noSlotsMsg.classList.remove('hidden');
            return;
        }

        try {
            noSlotsMsg.classList.add('hidden');
            slotsGrid.innerHTML = `<p class="col-span-full text-center text-slate-400 py-4 text-xs">Loading available slots...</p>`;

            const response = await fetch(`${API_BASE}/api/slots/availability?clinic_username=${clinicConfig.username}&date=${selectedDate}`);
            if (!response.ok) throw new Error('Failed to load slots');
            const slots = await response.json();

            slotsGrid.innerHTML = '';

            if (slots.length === 0) {
                noSlotsMsg.classList.remove('hidden');
                return;
            }

            // Filter out past time slots when booking for today
            const now = new Date();
            const todayStr = getLocalDate();
            const slotsFiltered = selectedDate === todayStr
                ? slots.filter(slot => {
                    const h = parseInt(slot.time_slot.split(':')[0], 10);
                    const m = parseInt(slot.time_slot.split(':')[1], 10);
                    return h > now.getHours() || (h === now.getHours() && m > now.getMinutes());
                })
                : slots;

            if (slotsFiltered.length === 0 && selectedDate === todayStr) {
                noSlotsMsg.classList.remove('hidden');
                return;
            }

            slotsFiltered.forEach(slot => {
                const isBlocked = slot.status !== 'available';
                const btn = document.createElement('button');
                btn.className = `p-2.5 rounded-theme text-xs font-semibold text-center border transition-theme ${
                    isBlocked 
                    ? 'bg-slate-100 text-slate-400 border-slate-200 cursor-not-allowed line-through' 
                    : 'border-slate-200 text-slate-700 hover:border-accent hover:text-accent hover:bg-teal-50/10'
                }`;
                btn.textContent = formatTime12(slot.time_slot);
                
                if (!isBlocked) {
                    btn.addEventListener('click', () => {
                        document.querySelectorAll('#booking-slots-grid button').forEach(b => {
                            if (!b.classList.contains('bg-slate-100')) {
                                b.className = 'p-2.5 rounded-theme text-xs font-semibold text-center border border-slate-200 text-slate-700 hover:border-accent hover:text-accent hover:bg-teal-50/10 transition-theme';
                            }
                        });
                        btn.className = 'p-2.5 rounded-theme text-xs font-semibold text-center border bg-accent text-white border-accent transition-theme';
                        selectedSlot = slot.time_slot;
                        updateSidebar();
                        validateStep();
                    });
                } else {
                    btn.disabled = true;
                }
                slotsGrid.appendChild(btn);
            });
        } catch (err) {
            console.error(err);
            slotsGrid.innerHTML = `<p class="col-span-full text-center text-rose-500 py-4 text-xs">Error loading slots. Please try again.</p>`;
        }
    }
    
    // Input validation for details
    const nameInput = document.getElementById('patient-name');
    const phoneInput = document.getElementById('patient-phone');
    const bookingError = document.getElementById('booking-error-step3');
    
    [nameInput, phoneInput].forEach(inp => {
        inp.addEventListener('input', () => {
            validateStep();
        });
    });

    phoneInput.addEventListener('blur', async () => {
        const phone = phoneInput.value.trim();
        if (phone.length === 10) {
            try {
                const res = await fetch(`${API_BASE}/api/patients/lookup?clinic_username=${clinicConfig.username}&phone=${phone}`);
                if (res.ok) {
                    const data = await res.json();
                    if (data.visits > 0) {
                        const welcomeText = `You've visited us ${data.visits} time${data.visits > 1 ? 's' : ''}. Last visit: ${new Date(data.lastVisitDate).toLocaleDateString('en-IN', {day: 'numeric', month: 'short', year: 'numeric'})}.`;
                        document.getElementById('welcome-back-text').textContent = welcomeText;
                        document.getElementById('welcome-back-banner').classList.remove('hidden');
                    } else {
                        document.getElementById('welcome-back-banner').classList.add('hidden');
                    }
                }
            } catch (e) {
                console.warn('Patient lookup fail:', e);
            }
        }
    });
    
    // Enable/disable next button based on selection
    function validateStep() {
        let isValid = false;
        if (currentStep === 1 && selectedService) {
            if (selectedService.id === 'custom') {
                isValid = !!selectedService.name?.trim();
            } else {
                isValid = true;
            }
        }
        if (currentStep === 2 && selectedDate && selectedSlot) isValid = true;
        if (currentStep === 3 && nameInput.value.trim().length >= 3 && /^\d{10}$/.test(phoneInput.value.trim())) isValid = true;
        
        nextBtn.disabled = !isValid;
        nextBtn.className = isValid 
            ? 'bg-accent hover:bg-accentHover text-white px-6 py-2.5 rounded-theme font-semibold shadow-md shadow-teal-500/10 hover:shadow-teal-500/20 transition-theme text-sm cursor-pointer'
            : 'bg-slate-100 text-slate-400 border border-slate-200 px-6 py-2.5 rounded-theme font-semibold text-sm cursor-not-allowed';
    }
    
    function goToStep(step) {
        currentStep = step;
        
        // Update numbered stepper circles
        for (let i = 1; i <= 3; i++) {
            const stepEl = document.getElementById(`stepper-${i}`);
            if (!stepEl) continue;
            const circle = stepEl.querySelector('.step-circle');
            stepEl.classList.remove('active', 'done');
            if (i < currentStep) {
                stepEl.classList.add('done');
                circle.innerHTML = '<i class="fa-solid fa-check text-xs"></i>';
            } else if (i === currentStep) {
                stepEl.classList.add('active');
                circle.innerHTML = String(i);
            } else {
                circle.innerHTML = String(i);
            }
        }
        // Update connectors
        const conn12 = document.getElementById('connector-1-2');
        const conn23 = document.getElementById('connector-2-3');
        if (conn12) conn12.classList.toggle('done', currentStep > 1);
        if (conn23) conn23.classList.toggle('done', currentStep > 2);
        
        // Toggle view
        document.querySelectorAll('.booking-step').forEach(st => st.classList.add('hidden'));
        document.getElementById(`step-${currentStep}`).classList.remove('hidden');
        if (currentStep === 2 && dateInput) {
            requestAnimationFrame(() => dateInput.showPicker?.());
        }
        
        // Actions visibility
        if (currentStep === 4) {
            document.getElementById('stepper-actions').classList.add('hidden');
        } else {
            document.getElementById('stepper-actions').classList.remove('hidden');
            prevBtn.disabled = currentStep === 1;
            prevBtn.style.opacity = currentStep === 1 ? '0' : '1';
            nextBtn.innerHTML = currentStep === 3
                ? '<span data-i18n="booking_request">Request Booking</span>'
                : '<span data-i18n="booking_continue">Continue</span>';
            applyTranslations();
        }
        
        validateStep();
    }
    
    // Event listeners for footer buttons
    nextBtn.addEventListener('click', async () => {
        if (currentStep < 3) {
            goToStep(currentStep + 1);
        } else if (currentStep === 3) {
            // Book action (Submit)
            nextBtn.disabled = true;
            nextBtn.innerHTML = '<span>Submitting...</span>';

            const isEmergencyCheckbox = document.getElementById('is-emergency');
            const problemNoteInput = document.getElementById('problem-note');

            try {
                const response = await fetch(`${API_BASE}/api/appointments`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        clinic_username: clinicConfig.username,
                        patient_name: nameInput.value.trim(),
                        patient_phone: phoneInput.value.trim(),
                        service: selectedService.id === 'custom' ? selectedService.name : selectedService.name,
                        date: selectedDate,
                        time_slot: selectedSlot,
                        is_emergency: isEmergencyCheckbox ? isEmergencyCheckbox.checked : false,
                        problem_note: problemNoteInput ? problemNoteInput.value.trim() : ''
                    })
                });

                if (!response.ok) {
                    const errData = await response.json();
                    if (bookingError) {
                        bookingError.textContent = errData.error || 'Booking failed';
                        bookingError.classList.remove('hidden');
                        bookingError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    throw new Error(errData.error || 'Booking failed');
                }

                if (bookingError) {
                    bookingError.classList.add('hidden');
                }

                document.getElementById('summary-patient-name').textContent = nameInput.value.trim();
                document.getElementById('summary-service').textContent = selectedService.name;
                // Format date nicely (DD-MM-YYYY)
                const dateParts = selectedDate.split('-');
                document.getElementById('summary-date').textContent = `${dateParts[2]}/${dateParts[1]}/${dateParts[0]}`;
                document.getElementById('summary-time').textContent = formatTime12(selectedSlot);
                
                goToStep(4);
            } catch (err) {
                console.error(err);
                if (bookingError) {
                    bookingError.textContent = err.message || 'Error occurred while saving booking. Please try again.';
                    bookingError.classList.remove('hidden');
                    bookingError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                nextBtn.disabled = false;
                nextBtn.textContent = 'Request Booking';
            }
        }
    });
    
    prevBtn.addEventListener('click', () => {
        if (currentStep > 1) {
            goToStep(currentStep - 1);
        }
    });
    
    // Book another button
    document.getElementById('book-another-btn').addEventListener('click', () => {
        selectedService = null;
        selectedDate = '';
        selectedSlot = '';
        nameInput.value = '';
        phoneInput.value = '';
        const isEmergencyCheckbox = document.getElementById('is-emergency');
        const problemNoteInput = document.getElementById('problem-note');
        if (isEmergencyCheckbox) isEmergencyCheckbox.checked = false;
        if (problemNoteInput) problemNoteInput.value = '';
        document.getElementById('welcome-back-banner').classList.add('hidden');
        
        // Reset Service options UI
        serviceOpts.forEach(o => {
            o.classList.remove('border-accent', 'bg-teal-50/20');
            o.querySelector('.opt-indicator').classList.remove('border-accent');
            o.querySelector('.opt-indicator div').classList.add('scale-0');
        });
        dateInput.value = '';
        document.getElementById('booking-slots-grid').innerHTML = '';
        document.getElementById('no-slots-msg')?.classList.remove('hidden');
        
        goToStep(1);
    });
    
    goToStep(1); // init first step
}

// --- LIGHTBOX GALLERY ---
function initLightbox() {
    const lightbox = document.getElementById('lightbox');
    const lightboxImg = document.getElementById('lightbox-img');
    const lightboxClose = document.getElementById('lightbox-close');
    
    document.querySelectorAll('.gallery-item').forEach(item => {
        if (item.classList.contains('before-after-container')) return; // skip slider
        item.addEventListener('click', () => {
            const img = item.querySelector('img');
            if (img) {
                lightboxImg.src = img.src;
                lightbox.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            }
        });
    });
    
    const closeLightbox = () => {
        lightbox.classList.add('hidden');
        document.body.style.overflow = '';
    };
    
    lightboxClose.addEventListener('click', closeLightbox);
    lightbox.addEventListener('click', (e) => {
        if (e.target === lightbox || e.target === lightboxClose) {
            closeLightbox();
        }
    });
}

// =============================================
// HERO SPARKLE PARTICLES
// =============================================
function initSparkles() {
    const canvas = document.getElementById('sparkle-canvas');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const accent = getComputedStyle(document.documentElement)
                   .getPropertyValue('--accent-color').trim() || '#2DD4BF';

    function resize() {
        canvas.width  = canvas.offsetWidth;
        canvas.height = canvas.offsetHeight;
    }
    resize();
    window.addEventListener('resize', resize, { passive: true });

    // Create sparkles
    const COUNT = 28;
    const sparkles = Array.from({ length: COUNT }, () => ({
        x:       Math.random() * canvas.width,
        y:       Math.random() * canvas.height,
        size:    Math.random() * 7 + 3,       // 3–10px
        speed:   Math.random() * 0.4 + 0.15,  // upward drift
        opacity: Math.random() * 0.4 + 0.1,   // 0.1–0.5
        twinkleSpeed: Math.random() * 0.02 + 0.005,
        twinkleDir: 1,
        drift:   (Math.random() - 0.5) * 0.3  // slight horizontal sway
    }));

    // Draw a 4-point star/sparkle
    function drawSparkle(x, y, size, opacity) {
        ctx.save();
        ctx.globalAlpha = opacity;
        ctx.fillStyle = accent;
        ctx.translate(x, y);

        ctx.beginPath();
        for (let i = 0; i < 4; i++) {
            const angle = (i * Math.PI) / 2;
            const outerX = Math.cos(angle) * size;
            const outerY = Math.sin(angle) * size;
            const innerAngle1 = angle - Math.PI / 4;
            const innerAngle2 = angle + Math.PI / 4;
            const innerSize = size * 0.2;

            if (i === 0) ctx.moveTo(outerX, outerY);
            else ctx.lineTo(outerX, outerY);

            ctx.lineTo(
                Math.cos(innerAngle2) * innerSize,
                Math.sin(innerAngle2) * innerSize
            );
        }
        ctx.closePath();
        ctx.fill();
        ctx.restore();
    }

    function animate() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        sparkles.forEach(s => {
            drawSparkle(s.x, s.y, s.size, s.opacity);

            // Float upward + sway
            s.y -= s.speed;
            s.x += s.drift;

            // Twinkle
            s.opacity += s.twinkleSpeed * s.twinkleDir;
            if (s.opacity > 0.55 || s.opacity < 0.05) s.twinkleDir *= -1;

            // Reset when off screen top
            if (s.y < -10) {
                s.y = canvas.height + 10;
                s.x = Math.random() * canvas.width;
            }
        });

        requestAnimationFrame(animate);
    }

    animate();
}

function initLeadForm() {
    const leadForm = document.getElementById('lead-mini-form');
    if (leadForm) {
        leadForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const nameVal = document.getElementById('lead-name').value.trim();
            const phoneVal = document.getElementById('lead-phone').value.trim();
            const serviceVal = document.getElementById('lead-service').value;
            const submitBtn = document.getElementById('lead-submit-btn');
            const spinner = document.getElementById('lead-spinner');
            const statusMsg = document.getElementById('lead-status-msg');

            submitBtn.disabled = true;
            spinner.classList.remove('hidden');
            statusMsg.classList.add('hidden');

            try {
                const res = await fetch(`${API_BASE}/api/leads`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        clinic_slug: clinicConfig.slug,
                        name: nameVal,
                        phone: phoneVal,
                        interested_service: serviceVal
                    })
                });

                const data = await res.json();
                if (!res.ok) throw new Error(data.error || 'Failed to submit inquiry');

                statusMsg.textContent = data.message || 'Inquiry sent successfully!';
                statusMsg.className = 'text-xs font-semibold text-center text-emerald-600 mt-2';
                statusMsg.classList.remove('hidden');
                leadForm.classList.add('opacity-70');
                submitBtn.innerHTML = '<i class="fa-solid fa-circle-check"></i> Request Sent';
                submitBtn.disabled = true;
                leadForm.reset();
                setTimeout(() => {
                    leadForm.classList.remove('opacity-70');
                    submitBtn.innerHTML = '<i class="fa-solid fa-paper-plane mr-1.5"></i>Request Information';
                    submitBtn.disabled = false;
                    statusMsg.classList.add('hidden');
                }, 3000);
            } catch (err) {
                statusMsg.textContent = err.message || 'Submission failed. Please try again.';
                statusMsg.className = 'text-xs font-semibold text-center text-rose-500 mt-2';
                statusMsg.classList.remove('hidden');
            } finally {
                submitBtn.disabled = false;
                spinner.classList.add('hidden');
            }
        });
    }
}

// Populate lead form service dropdown from config so each clinic shows its own services
function populateLeadServiceDropdown() {
    const sel = document.getElementById('lead-service');
    if (!sel) return;

    const services = clinicConfig?.services || [];
    // Keep the first blank/placeholder option already in HTML, then fill from config
    sel.innerHTML = '<option value="">Select a service (Optional)</option>';

    services.forEach(s => {
        const opt = document.createElement('option');
        opt.value = s.name;
        opt.textContent = s.name;
        sel.appendChild(opt);
    });

    // Always add a generic fallback at the end
    const other = document.createElement('option');
    other.value = 'Other / General Inquiry';
    other.textContent = 'Other / General Inquiry';
    sel.appendChild(other);
}
