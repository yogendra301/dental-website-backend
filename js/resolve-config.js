import clientConfigFallback from './client-config.js';

export async function resolveClinicConfig() {

    // --- DEMO MODE: skip server, return local config and wire mock fetch ---
    if (clientConfigFallback.demoMode) {
        // Lazy-import the interceptor only when demo mode is on
        const { enableDemoMode } = await import('./demo-mock.js');
        enableDemoMode();

        // Build a resolved config using settings stored in localStorage (if any)
        const settingsRaw = localStorage.getItem('_demo_settings');
        const demoSettings = settingsRaw ? JSON.parse(settingsRaw) : {};

        const demoConfig = {
            ...clientConfigFallback,
            name: demoSettings.name || clientConfigFallback.name,
            contact_phone: demoSettings.contact_phone || clientConfigFallback.contact?.phone,
            contact_address: demoSettings.contact_address || clientConfigFallback.contact?.address,
            whatsapp_number: demoSettings.whatsapp_number || clientConfigFallback.whatsapp?.number,
            google_review_link: demoSettings.google_review_link || null,
            visibility_settings: demoSettings.visibility_settings || {},
            // Demo services derived from client-config services array
            services: clientConfigFallback.services || []
        };

        return demoConfig;
    }
    // --- END DEMO MODE ---

    const CACHE_TTL_MS = 60000;
    const cached = sessionStorage.getItem('_cc');
    if (cached) {
        try {
            const { data, cachedAt } = JSON.parse(cached);
            if (data?.services?.length && (Date.now() - cachedAt) < CACHE_TTL_MS) {
                return data;
            }
        } catch (e) {
            console.warn('Failed to parse cached clinic config', e);
        }
    }

    const host = window.location.hostname + (window.location.port ? ':' + window.location.port : '');
    const res = await fetch(`${clientConfigFallback.apiBase}/api/clinics/resolve?host=${encodeURIComponent(host)}`);
    if (!res.ok) {
        document.body.innerHTML = `
            <div style="font-family: system-ui, -apple-system, sans-serif; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 100vh; background: #0f172a; color: #cbd5e1; text-align: center; padding: 20px;">
                <div style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); padding: 40px; border-radius: 16px; max-width: 400px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.3);">
                    <div style="font-size: 48px; color: #f43f5e; margin-bottom: 20px;">⚠️</div>
                    <h1 style="font-size: 24px; font-weight: 800; color: #fff; margin-bottom: 10px;">Clinic Not Found</h1>
                    <p style="font-size: 14px; line-height: 1.6; color: #94a3b8; margin-bottom: 24px;">The clinic domain you requested does not match any registered records in our network.</p>
                    <a href="mailto:support@dentalportal.com" style="display: inline-block; background: #2dd4bf; color: #0f172a; font-weight: 700; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-size: 14px; transition: background 0.2s;">Contact Support</a>
                </div>
            </div>
        `;
        throw new Error('Clinic resolve failed');
    }
    const config = await res.json();

    sessionStorage.setItem('_cc', JSON.stringify({ data: config, cachedAt: Date.now() }));
    return config;
}
