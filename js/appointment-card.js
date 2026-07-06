/**
 * Shared appointment card renderer used by both admin dashboard and doctor dashboard.
 * Keeping this in one place means fixes here apply everywhere automatically.
 */

const API_BASE = window.location.origin;

/**
 * Convert 24-hour "HH:MM" to "H:MM AM/PM"
 */
export function formatTime12(timeStr) {
    if (!timeStr) return '';
    const [h, m] = timeStr.split(':').map(Number);
    const ampm = h >= 12 ? 'PM' : 'AM';
    const displayH = h % 12 || 12;
    return `${displayH}:${String(m).padStart(2, '0')} ${ampm}`;
}

const STATUS_LABELS = {
    pending: 'Pending',
    confirmed: 'Confirmed',
    completed: 'Completed',
    cancelled: 'Cancelled',
    no_show: "Didn't Come"
};

/**
 * Build and return an appointment card DOM element.
 *
 * @param {object} appt  - Appointment data row from API
 * @param {number} idx   - Card index (used for staggered animation delay)
 * @param {object} opts
 *   opts.readOnly       {boolean}  - Hide all action buttons (doctor view)
 *   opts.isFollowupView {boolean}  - Show Mark-Done button instead of Complete/No-Show
 *   opts.onRefresh      {Function} - Callback fired after a status mutation
 */
export function renderAppointmentCard(appt, idx = 0, opts = {}) {
    const { readOnly = false, isFollowupView = false, onRefresh } = opts;

    const time12 = formatTime12(appt.time_slot);
    const card = document.createElement('div');

    let badgeColor = 'bg-sky-50 text-sky-700 border-sky-100';
    if (appt.source === 'phone') {
        badgeColor = 'bg-indigo-50 text-indigo-700 border-indigo-100';
    } else if (appt.source === 'walkin') {
        badgeColor = 'bg-teal-50 text-teal-700 border-teal-100';
    }

    let statusColor = 'bg-slate-50 text-slate-500 border-slate-100';
    switch (appt.status) {
        case 'pending':   statusColor = 'bg-amber-50 text-amber-600 border-amber-100'; break;
        case 'confirmed': statusColor = 'bg-blue-50 text-blue-600 border-blue-100'; break;
        case 'completed': statusColor = 'bg-emerald-50 text-emerald-600 border-emerald-100'; break;
        case 'cancelled': statusColor = 'bg-rose-50 text-rose-600 border-rose-100'; break;
        case 'no_show':   statusColor = 'bg-rose-50 text-rose-600 border-rose-100'; break;
    }

    let borderClass = 'border-slate-100';
    if (appt.is_emergency) {
        borderClass = 'border-red-200 border-l-4 border-l-rose-500';
    }

    if (isFollowupView && appt.follow_up_date) {
        const todayStr = _getLocalDateString();
        const rawFollowDate = appt.follow_up_date.split('T')[0];
        if (rawFollowDate && rawFollowDate < todayStr) {
            borderClass = 'border-red-400 border-l-4 border-l-rose-500';
        }
    }

    const nameClass = appt.status === 'no_show'
        ? 'font-bold text-slate-400 text-sm line-through m-0 truncate'
        : 'font-bold text-slate-800 text-sm m-0 truncate';

    card.className = `appt-card p-3 rounded-theme border bg-white hover:border-accent/40 shadow-sm transition-theme cursor-pointer ${borderClass}`;
    card.style.animationDelay = `${idx * 60}ms`;
    card.innerHTML = `
        <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-2 min-w-0 flex-1">
                <span class="text-xs font-bold text-slate-900 shrink-0">${time12}</span>
                ${appt.is_emergency ? `<span class="text-rose-600 shrink-0" title="Emergency"><i class="fa-solid fa-triangle-exclamation text-[10px]"></i></span>` : ''}
            </div>
            <div class="flex items-center gap-1.5 shrink-0">
                <span class="px-2 py-0.5 border rounded-full text-[9px] font-bold uppercase ${badgeColor}">${appt.source}</span>
                <span class="px-2 py-0.5 border rounded-full text-[9px] font-bold uppercase ${statusColor}">${STATUS_LABELS[appt.status] || appt.status}</span>
            </div>
        </div>
        <p class="${nameClass} mt-1">${appt.patient_name}</p>
        <p class="text-xs text-slate-500 m-0 mt-1 truncate">${appt.service} <span class="text-slate-300">&middot;</span> ${appt.patient_phone}</p>
        ${appt.follow_up_date ? `<p class="text-[10px] text-teal-600 m-0 mt-1 font-semibold flex items-center gap-1"><i class="fa-solid fa-calendar-check"></i> Follow-up: ${new Date(appt.follow_up_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })} ${appt.follow_up_note ? `(${appt.follow_up_note})` : ''}</p>` : ''}
    `;

    if (!readOnly) {
        const actionsRow = document.createElement('div');
        actionsRow.className = 'mt-2 pt-2 border-t border-slate-100 flex items-center justify-between gap-2';

        const iconGroup = document.createElement('div');
        iconGroup.className = 'flex items-center gap-1.5';

        const btnGroup = document.createElement('div');
        btnGroup.className = 'flex items-center gap-1.5';

        const clinicName = window.clinicConfig?.name || '';
        const waMsg = encodeURIComponent(
            `Hi ${appt.patient_name}, this is ${clinicName}. We're reaching out regarding your ${appt.service} appointment on ${appt.date}. Please let us know if you have any questions.`
        );
        const waUrl = `https://wa.me/${appt.patient_phone.replace(/\D/g, '')}?text=${waMsg}`;

        iconGroup.innerHTML = `
            <a href="tel:${appt.patient_phone.replace(/\D/g, '')}" onclick="event.stopPropagation();" class="h-7 w-7 rounded-full bg-sky-500 hover:bg-sky-600 text-white flex items-center justify-center transition-all text-xs shadow-sm" title="Call Patient">
                <i class="fa-solid fa-phone"></i>
            </a>
            <a href="${waUrl}" target="_blank" onclick="event.stopPropagation();" class="h-7 w-7 rounded-full bg-emerald-500 hover:bg-emerald-600 text-white flex items-center justify-center transition-all text-xs shadow-sm" title="Open WhatsApp Chat">
                <i class="fa-brands fa-whatsapp text-sm"></i>
            </a>
        `;
        actionsRow.appendChild(iconGroup);
        actionsRow.appendChild(btnGroup);

        if (isFollowupView) {
            const doneBtn = document.createElement('button');
            doneBtn.className = 'text-[11px] font-bold text-teal-600 hover:text-teal-700 flex items-center gap-1 py-1 px-2 rounded-lg border border-teal-200 hover:bg-teal-50 transition-all';
            doneBtn.innerHTML = `<i class="fa-solid fa-check-double"></i> Mark Done`;
            doneBtn.addEventListener('click', async (e) => {
                e.stopPropagation();
                const token = _getAdminToken();
                try {
                    const res = await fetch(`${API_BASE}/api/appointments/${appt.id}/followup-done`, {
                        method: 'PATCH',
                        headers: { 'Authorization': `Bearer ${token}` }
                    });
                    if (!res.ok) throw new Error('Failed to mark followup done');
                    onRefresh?.();
                } catch (err) {
                    alert(err.message);
                }
            });
            btnGroup.appendChild(doneBtn);

        } else if (appt.status !== 'completed' && appt.status !== 'cancelled' && appt.status !== 'no_show') {
            const completeBtn = document.createElement('button');
            completeBtn.className = 'text-[11px] font-bold text-accent hover:text-accentHover flex items-center gap-1 py-1 px-2 rounded-lg border border-accent/20 hover:bg-accent/10 transition-all';
            completeBtn.innerHTML = `<i class="fa-solid fa-circle-check"></i> Complete`;
            completeBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                window._showCompleteModal?.(appt);
            });
            btnGroup.appendChild(completeBtn);

            const todayStr = _getLocalDateString();
            if (appt.date <= todayStr && (appt.status === 'pending' || appt.status === 'confirmed')) {
                const noShowBtn = document.createElement('button');
                noShowBtn.className = 'text-[11px] font-bold text-slate-400 hover:text-rose-500 flex items-center gap-1 py-1 px-2 rounded-lg border border-slate-200 hover:border-rose-200 hover:bg-rose-50 transition-all';
                noShowBtn.innerHTML = `<i class="fa-solid fa-user-slash"></i> Didn't Come`;
                noShowBtn.addEventListener('click', async (e) => {
                    e.stopPropagation();
                    const token = _getAdminToken();
                    try {
                        const res = await fetch(`${API_BASE}/api/appointments/${appt.id}/no-show`, {
                            method: 'PATCH',
                            headers: { 'Authorization': `Bearer ${token}` }
                        });
                        if (!res.ok) throw new Error('Failed to mark no-show');
                        onRefresh?.();
                    } catch (err) {
                        alert(err.message);
                    }
                });
                btnGroup.appendChild(noShowBtn);
            }
        }

        card.appendChild(actionsRow);
    }

    card.addEventListener('click', () => window._showDetailModal?.(appt));
    return card;
}

// --- Internal helpers ---

function _getLocalDateString(date = new Date()) {
    const offset = date.getTimezoneOffset();
    const localDate = new Date(date.getTime() - (offset * 60 * 1000));
    return localDate.toISOString().split('T')[0];
}

function _getAdminToken() {
    return localStorage.getItem('admin_token') || sessionStorage.getItem('admin_token');
}
