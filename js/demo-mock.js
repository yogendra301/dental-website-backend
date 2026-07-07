/**
 * Vercel Static Demo Mode — window.fetch Interceptor
 * Uses localStorage to persist mock backend data (appointments, slots, gallery, etc.)
 * so the frontend runs as a fully interactive single-page app without any server.
 */

import clientConfigFallback from './client-config.js';

// Local storage keys
const APPOINTMENTS_KEY = '_demo_appointments';
const BLOCKED_SLOTS_KEY = '_demo_blocked_slots';
const LEADS_KEY = '_demo_leads';
const DOCUMENTS_KEY = '_demo_documents';
const GALLERY_KEY = '_demo_gallery';
const SETTINGS_KEY = '_demo_settings';

// Initialize localStorage databases with seed data if they don't exist
function initLocalStorage() {
    if (!localStorage.getItem(APPOINTMENTS_KEY)) {
        const today = new Date().toISOString().split('T')[0];
        // Seed some initial demo appointments
        const seedAppointments = [
            {
                id: 101,
                patient_name: "Rahul Sharma",
                patient_phone: "9876543210",
                service: "Tooth Pain & Checkup",
                date: today,
                time_slot: "10:30",
                status: "pending",
                source: "online",
                is_emergency: 0
            },
            {
                id: 102,
                patient_name: "Priya Patel",
                patient_phone: "9123456789",
                service: "Cleaning & Polishing",
                date: today,
                time_slot: "11:30",
                status: "confirmed",
                source: "phone",
                is_emergency: 0
            },
            {
                id: 103,
                patient_name: "Amit Kumar",
                patient_phone: "9988776655",
                service: "Root Canal Treatment (RCT)",
                date: today,
                time_slot: "14:00",
                status: "completed",
                source: "walkin",
                is_emergency: 1,
                treatment_performed: "Single sitting RCT completed",
                treatment_cost: 4000,
                amount_paid: 4000
            }
        ];
        localStorage.setItem(APPOINTMENTS_KEY, JSON.stringify(seedAppointments));
    }

    if (!localStorage.getItem(BLOCKED_SLOTS_KEY)) {
        localStorage.setItem(BLOCKED_SLOTS_KEY, JSON.stringify([]));
    }

    if (!localStorage.getItem(LEADS_KEY)) {
        localStorage.setItem(LEADS_KEY, JSON.stringify([]));
    }

    if (!localStorage.getItem(DOCUMENTS_KEY)) {
        localStorage.setItem(DOCUMENTS_KEY, JSON.stringify([]));
    }

    if (!localStorage.getItem(GALLERY_KEY)) {
        const seedGallery = [
            { id: 1, type: "single", image_url: "/assets/hero_card1.png", before_url: null, after_url: null, caption: "Premium Clinical Setup", sort_order: 1 },
            { id: 2, type: "single", image_url: "/assets/hero_card2.png", before_url: null, after_url: null, caption: "Modern Diagnostics", sort_order: 2 },
            { id: 3, type: "before_after", image_url: "", before_url: "/assets/Extreme_close-up_photorealistic_shot_of_202606281847.jpeg", after_url: "/assets/Same_lighting_style,_color_grading_202606281849.jpeg", caption: "Orthodontic Aligners Before/After", sort_order: 3 }
        ];
        localStorage.setItem(GALLERY_KEY, JSON.stringify(seedGallery));
    }

    if (!localStorage.getItem(SETTINGS_KEY)) {
        const seedSettings = {
            name: clientConfigFallback.name,
            contact_phone: clientConfigFallback.contact?.phone || "+919876543210",
            contact_address: clientConfigFallback.contact?.address || "123 Main St",
            map_embed_url: clientConfigFallback.contact?.mapEmbedUrl || "",
            whatsapp_number: clientConfigFallback.whatsapp?.number || "+919876543210",
            google_review_link: "https://g.page/r/smile-care-dental/review",
            visibility_settings: {
                show_stats_bar: true,
                show_ratings: true,
                show_doctor_section: true,
                show_gallery: true,
                show_pricing: true,
                show_lead_form: true,
                show_google_review_btn: true,
                show_whatsapp_fab: true,
                show_working_hours: true
            }
        };
        localStorage.setItem(SETTINGS_KEY, JSON.stringify(seedSettings));
    }
}

// Intercept window.fetch
export function enableDemoMode() {
    initLocalStorage();
    
    // Store original fetch
    const originalFetch = window.fetch;

    window.fetch = async function(url, options = {}) {
        const urlStr = url.toString();
        
        // Only intercept /api/ calls
        if (!urlStr.includes('/api/')) {
            return originalFetch.apply(this, arguments);
        }

        const method = (options.method || 'GET').toUpperCase();
        const headers = options.headers || {};
        const body = options.body ? JSON.parse(options.body) : null;
        
        // Parse endpoint path
        const pathname = new URL(urlStr, window.location.origin).pathname;

        console.log(`[Demo Interceptor] ${method} ${pathname}`, body);

        // Helper: mock standard 200 JSON Response
        const jsonResponse = (data, status = 200) => {
            return new Response(JSON.stringify(data), {
                status,
                headers: { 'Content-Type': 'application/json' }
            });
        };

        // Helper: get database arrays
        const getDB = (key) => JSON.parse(localStorage.getItem(key));
        const setDB = (key, val) => localStorage.setItem(key, JSON.stringify(val));

        try {
            // 1. AUTH ROUTES
            if (pathname === '/api/auth/login') {
                return jsonResponse({
                    token: "mock-jwt-token-xyz",
                    clinic: {
                        username: clientConfigFallback.username,
                        name: getDB(SETTINGS_KEY).name
                    }
                });
            }
            if (pathname === '/api/auth/forgot-password') {
                return jsonResponse({ success: true, message: "OTP sent to registered number" });
            }
            if (pathname === '/api/auth/reset-password') {
                return jsonResponse({ success: true, message: "Password reset complete" });
            }

            // 2. CLINIC RESOLVE / DETAILS
            if (pathname === '/api/clinics/resolve') {
                const settings = getDB(SETTINGS_KEY);
                const resolvedConfig = {
                    ...clientConfigFallback,
                    name: settings.name,
                    contact_phone: settings.contact_phone,
                    contact_address: settings.contact_address,
                    contact_map_url: settings.map_embed_url,
                    whatsapp_number: settings.whatsapp_number,
                    google_review_link: settings.google_review_link,
                    visibility_settings: settings.visibility_settings,
                    contact: {
                        ...(clientConfigFallback.contact || {}),
                        phone: settings.contact_phone,
                        address: settings.contact_address,
                        mapEmbedUrl: settings.map_embed_url
                    }
                };
                return jsonResponse(resolvedConfig);
            }
            if (pathname.startsWith('/api/clinics/settings') && method === 'PATCH') {
                const settings = getDB(SETTINGS_KEY);
                const updatedSettings = {
                    ...settings,
                    name: body.name || settings.name,
                    contact_phone: body.contact_phone || settings.contact_phone,
                    contact_address: body.contact_address || settings.contact_address,
                    map_embed_url: body.map_embed_url !== undefined ? body.map_embed_url : settings.map_embed_url,
                    whatsapp_number: body.whatsapp_number || settings.whatsapp_number,
                    google_review_link: body.google_review_link || settings.google_review_link,
                    visibility_settings: {
                        ...settings.visibility_settings,
                        ...(body.visibility_settings || {})
                    }
                };
                setDB(SETTINGS_KEY, updatedSettings);
                return jsonResponse({ success: true, message: "Settings saved" });
            }

            // 3. SLOTS AVAILABILITY & BLOCKS
            if (pathname === '/api/slots/availability') {
                const urlParams = new URL(urlStr, window.location.origin).searchParams;
                const date = urlParams.get('date');
                
                // Get blocked slots & booked appointments
                const blocked = getDB(BLOCKED_SLOTS_KEY).filter(b => b.date === date).map(b => b.time_slot);
                const appts = getDB(APPOINTMENTS_KEY).filter(a => a.date === date && a.status !== 'cancelled');
                
                const bookedOnline = appts.filter(a => a.source === 'online').map(a => a.time_slot);
                const bookedPhone = appts.filter(a => a.source === 'phone' || a.source === 'walkin').map(a => a.time_slot);

                return jsonResponse({
                    blocked_slots: blocked,
                    booked_online: bookedOnline,
                    booked_phone: bookedPhone
                });
            }
            if (pathname === '/api/slots/block') {
                if (method === 'POST') {
                    const list = getDB(BLOCKED_SLOTS_KEY);
                    list.push({ id: Date.now(), date: body.date, time_slot: body.time_slot });
                    setDB(BLOCKED_SLOTS_KEY, list);
                    return jsonResponse({ success: true });
                }
                if (method === 'DELETE') {
                    const urlParams = new URL(urlStr, window.location.origin).searchParams;
                    const date = urlParams.get('date');
                    const time_slot = urlParams.get('time_slot');
                    const list = getDB(BLOCKED_SLOTS_KEY).filter(b => !(b.date === date && b.time_slot === time_slot));
                    setDB(BLOCKED_SLOTS_KEY, list);
                    return jsonResponse({ success: true });
                }
            }

            // 4. APPOINTMENTS MANAGEMENT
            if (pathname === '/api/appointments' && method === 'GET') {
                const urlParams = new URL(urlStr, window.location.origin).searchParams;
                const date = urlParams.get('date');
                let list = getDB(APPOINTMENTS_KEY);
                if (date) {
                    list = list.filter(a => a.date === date);
                }
                return jsonResponse(list);
            }
            if (pathname === '/api/appointments' && method === 'POST') {
                const list = getDB(APPOINTMENTS_KEY);
                const newAppt = {
                    id: Date.now(),
                    patient_name: body.patient_name,
                    patient_phone: body.patient_phone,
                    service: body.service,
                    date: body.date,
                    time_slot: body.time_slot,
                    status: 'pending',
                    source: body.source || 'online',
                    is_emergency: body.is_emergency ? 1 : 0,
                    problem_note: body.problem_note || ''
                };
                list.push(newAppt);
                setDB(APPOINTMENTS_KEY, list);
                return jsonResponse({ success: true, appointment: newAppt });
            }
            if (pathname === '/api/appointments/admin' && method === 'POST') {
                const list = getDB(APPOINTMENTS_KEY);
                const apptSource = body.source || 'phone';
                const isWalkin = apptSource === 'walkin';
                let time_slot_to_use = body.time_slot;

                if (isWalkin) {
                    const parts = body.time_slot.split(':').map(Number);
                    let rounded = parts[0] * 60 + parts[1];
                    let attempts = 0;
                    while (attempts < 60) {
                        const rH = String(Math.floor(rounded / 60) % 24).padStart(2, '0');
                        const rM = String(rounded % 60).padStart(2, '0');
                        const checkSlot = `${rH}:${rM}`;
                        
                        const exists = list.some(a => a.date === body.date && a.time_slot === checkSlot && a.status !== 'cancelled');
                        if (!exists) {
                            time_slot_to_use = checkSlot;
                            break;
                        }
                        rounded += 1;
                        attempts++;
                    }
                }

                const newAppt = {
                    id: Date.now(),
                    patient_name: body.patient_name,
                    patient_phone: body.patient_phone,
                    service: body.service,
                    date: body.date,
                    time_slot: time_slot_to_use,
                    status: 'confirmed',
                    source: apptSource,
                    is_emergency: body.is_emergency ? 1 : 0,
                    problem_note: body.problem_note || ''
                };
                list.push(newAppt);
                setDB(APPOINTMENTS_KEY, list);
                return jsonResponse({ success: true, appointment: newAppt });
            }
            if (pathname === '/api/appointments/history') {
                const list = getDB(APPOINTMENTS_KEY).filter(a => a.status === 'completed' || a.status === 'cancelled' || a.status === 'no_show');
                return jsonResponse(list);
            }
            if (pathname === '/api/appointments/followups') {
                const list = getDB(APPOINTMENTS_KEY).filter(a => a.follow_up_date && !a.follow_up_completed);
                return jsonResponse(list);
            }
            
            // PATCH routes for appointment state updates
            if (pathname.includes('/api/appointments/') && method === 'PATCH') {
                const parts = pathname.split('/');
                const id = parseInt(parts[3]);
                const action = parts[4];
                
                const list = getDB(APPOINTMENTS_KEY);
                const appt = list.find(a => a.id === id);
                if (!appt) return jsonResponse({ error: "Not found" }, 404);

                if (action === 'complete') {
                    appt.status = 'completed';
                    appt.treatment_performed = body.treatment_performed;
                    appt.doctor_notes = body.doctor_notes;
                    appt.medicines_instructions = body.medicines_instructions;
                    appt.treatment_cost = parseFloat(body.treatment_cost || 0);
                    appt.discount = parseFloat(body.discount || 0);
                    appt.amount_paid = parseFloat(body.amount_paid || 0);
                    if (body.follow_up_date) {
                        appt.follow_up_date = body.follow_up_date;
                        appt.follow_up_note = body.follow_up_note;
                        appt.follow_up_completed = 0;
                    }
                } else if (action === 'no-show') {
                    appt.status = 'no_show';
                } else if (action === 'followup-done') {
                    appt.follow_up_completed = 1;
                }
                setDB(APPOINTMENTS_KEY, list);
                return jsonResponse({ success: true });
            }
            
            if (pathname.includes('/api/appointments/') && method === 'DELETE') {
                const parts = pathname.split('/');
                const id = parseInt(parts[3]);
                const list = getDB(APPOINTMENTS_KEY);
                const appt = list.find(a => a.id === id);
                if (appt) {
                    appt.status = 'cancelled';
                    setDB(APPOINTMENTS_KEY, list);
                }
                return jsonResponse({ success: true });
            }

            // 5. PATIENTS LOOKUP / SEARCH
            if (pathname === '/api/patients/lookup') {
                const urlParams = new URL(urlStr, window.location.origin).searchParams;
                const phone = urlParams.get('phone');
                const appts = getDB(APPOINTMENTS_KEY).filter(a => a.patient_phone === phone);
                if (appts.length > 0) {
                    return jsonResponse({
                        exists: true,
                        patient_name: appts[0].patient_name,
                        visits_count: appts.length
                    });
                }
                return jsonResponse({ exists: false });
            }
            if (pathname === '/api/patients/search') {
                const urlParams = new URL(urlStr, window.location.origin).searchParams;
                const q = urlParams.get('q').toLowerCase();
                const appts = getDB(APPOINTMENTS_KEY);
                
                // Group by phone to return unique patient records
                const seen = new Set();
                const results = [];
                appts.forEach(a => {
                    if (seen.has(a.patient_phone)) return;
                    if (a.patient_name.toLowerCase().includes(q) || a.patient_phone.includes(q)) {
                        seen.add(a.patient_phone);
                        results.push({
                            patient_name: a.patient_name,
                            patient_phone: a.patient_phone,
                            last_visit: a.date,
                            visits_count: appts.filter(ap => ap.patient_phone === a.patient_phone).length
                        });
                    }
                });
                return jsonResponse(results);
            }

            // 6. LEADS / CALLBACK REQUESTS
            if (pathname === '/api/leads') {
                if (method === 'POST') {
                    const list = getDB(LEADS_KEY);
                    const newLead = {
                        id: Date.now(),
                        name: body.name,
                        phone: body.phone,
                        service: body.service,
                        status: 'pending',
                        notes: '',
                        created_at: new Date().toISOString()
                    };
                    list.push(newLead);
                    setDB(LEADS_KEY, list);
                    return jsonResponse({ success: true });
                }
                if (method === 'GET') {
                    const list = getDB(LEADS_KEY);
                    return jsonResponse(list);
                }
            }
            if (pathname.startsWith('/api/leads/') && method === 'PATCH') {
                const id = parseInt(pathname.split('/')[3]);
                const list = getDB(LEADS_KEY);
                const lead = list.find(l => l.id === id);
                if (lead) {
                    lead.status = body.status || lead.status;
                    lead.notes = body.notes || lead.notes;
                    setDB(LEADS_KEY, list);
                }
                return jsonResponse({ success: true });
            }

            // 7. PATIENT DOCUMENTS
            if (pathname === '/api/documents' && method === 'GET') {
                const urlParams = new URL(urlStr, window.location.origin).searchParams;
                const phone = urlParams.get('phone');
                const list = getDB(DOCUMENTS_KEY).filter(d => d.patient_phone === phone);
                return jsonResponse(list);
            }
            if (pathname === '/api/documents' && method === 'POST') {
                // Documents uploaded via FormData, mock it
                const list = getDB(DOCUMENTS_KEY);
                const newDoc = {
                    id: Date.now(),
                    patient_phone: "9876543210", // default mock lookup
                    file_name: "mock_xray.png",
                    file_path: "/assets/hero_card1.png",
                    uploaded_at: new Date().toISOString()
                };
                list.push(newDoc);
                setDB(DOCUMENTS_KEY, list);
                return jsonResponse({ success: true, document: newDoc });
            }
            if (pathname.startsWith('/api/documents/') && method === 'DELETE') {
                const id = parseInt(pathname.split('/')[3]);
                const list = getDB(DOCUMENTS_KEY).filter(d => d.id !== id);
                setDB(DOCUMENTS_KEY, list);
                return jsonResponse({ success: true });
            }

            // 8. GALLERY MANAGEMENT
            if (pathname === '/api/gallery' && method === 'GET') {
                const list = getDB(GALLERY_KEY);
                list.sort((a, b) => {
                    const aVal = (a.type === 'before_after' || a.type === 'before-after') ? 0 : 1;
                    const bVal = (b.type === 'before_after' || b.type === 'before-after') ? 0 : 1;
                    if (aVal !== bVal) return aVal - bVal;
                    return (a.sort_order || 999) - (b.sort_order || 999);
                });
                return jsonResponse(list);
            }
            if (pathname === '/api/gallery' && method === 'POST') {
                const list = getDB(GALLERY_KEY);
                const newItem = {
                    id: Date.now(),
                    type: "single",
                    image_url: "/assets/hero_card2.png",
                    before_url: null,
                    after_url: null,
                    caption: "Gallery Upload Mockup",
                    sort_order: list.length + 1
                };
                list.push(newItem);
                setDB(GALLERY_KEY, list);
                return jsonResponse({ success: true, item: newItem });
            }
            if (pathname === '/api/gallery/before-after' && method === 'POST') {
                const list = getDB(GALLERY_KEY);
                const newItem = {
                    id: Date.now(),
                    type: "before_after",
                    image_url: "",
                    before_url: "/assets/Extreme_close-up_photorealistic_shot_of_202606281847.jpeg",
                    after_url: "/assets/Same_lighting_style,_color_grading_202606281849.jpeg",
                    caption: "Before & After Upload Mockup",
                    sort_order: list.length + 1
                };
                list.push(newItem);
                setDB(GALLERY_KEY, list);
                return jsonResponse({ success: true, item: newItem });
            }
            if (pathname.startsWith('/api/gallery/') && method === 'DELETE') {
                const id = parseInt(pathname.split('/')[3]);
                const list = getDB(GALLERY_KEY).filter(g => g.id !== id);
                setDB(GALLERY_KEY, list);
                return jsonResponse({ success: true });
            }

            // Fallback for unhandled API endpoints
            return jsonResponse({ error: "Endpoint mockup not implemented" }, 404);

        } catch (err) {
            console.error('[Demo Interceptor Error]', err);
            return jsonResponse({ error: "Mockup handler crashed" }, 500);
        }
    };
}
