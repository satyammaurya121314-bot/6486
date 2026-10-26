/**
 * assets/js/script.js
 * MediToken Frontend Client Logic
 */

document.addEventListener('DOMContentLoaded', () => {
    initMobileNav();
    initSlotPicker();
    initQueuePolling();
    initNotificationPolling();
});

// Mobile Navbar Toggle
function initMobileNav() {
    const toggle = document.querySelector('.mobile-nav-toggle');
    const links = document.querySelector('.nav-links');
    if (toggle && links) {
        toggle.addEventListener('click', () => {
            links.classList.toggle('open');
        });
    }
}

// Slot Picker in Booking Page
function initSlotPicker() {
    const slotBtns = document.querySelectorAll('.slot-btn:not(.slot-booked)');
    const hiddenTimeInput = document.getElementById('selected_time_slot');

    slotBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            slotBtns.forEach(b => b.classList.remove('slot-selected'));
            btn.classList.add('slot-selected');
            if (hiddenTimeInput) {
                hiddenTimeInput.value = btn.dataset.time || btn.textContent.trim();
            }
        });
    });
}

// Live Queue Polling via AJAX / Fetch
let queuePollInterval = null;
function initQueuePolling() {
    const tracker = document.getElementById('liveQueueTracker');
    if (!tracker) return;

    const doctorId = tracker.dataset.doctorId || '';
    const appointmentId = tracker.dataset.appointmentId || '';
    const date = tracker.dataset.date || '';
    const baseUrl = tracker.dataset.baseUrl || '';

    function pollQueue() {
        let url = `${baseUrl}/api/queue_status.php?`;
        if (doctorId) url += `doctor_id=${encodeURIComponent(doctorId)}&`;
        if (appointmentId) url += `appointment_id=${encodeURIComponent(appointmentId)}&`;
        if (date) url += `date=${encodeURIComponent(date)}&`;
        
        fetch(url)
            .then(res => {
                if (!res.ok) throw new Error('Queue status network error');
                return res.json();
            })
            .then(data => {
                if (!data || data.error) return;

                // Update Currently Serving
                const servingEl = document.getElementById('servingTokenDisplay');
                if (servingEl) {
                    servingEl.textContent = data.current_token || 'None';
                }

                // Update Next Token
                const nextEl = document.getElementById('nextTokenDisplay');
                if (nextEl) {
                    nextEl.textContent = data.next_token || 'None';
                }

                // Update Patients Ahead
                const aheadEl = document.getElementById('patientsAheadDisplay');
                if (aheadEl) {
                    aheadEl.textContent = data.patients_ahead !== undefined ? data.patients_ahead : '-';
                }

                // Update Estimated Wait
                const waitEl = document.getElementById('estimatedWaitDisplay');
                if (waitEl) {
                    waitEl.textContent = data.estimated_wait || '-';
                }

                // Update Status Badge if on patient queue page
                const statusBadgeEl = document.getElementById('myStatusBadgeDisplay');
                if (statusBadgeEl && data.status) {
                    statusBadgeEl.innerHTML = data.status_html || `<span class="status-badge badge-${data.status.toLowerCase()}">${data.status}</span>`;
                }

                // Update Day Lineup Table dynamically
                const tableBody = document.querySelector('#dayQueueTable tbody');
                if (tableBody && Array.isArray(data.tokens_list)) {
                    tableBody.innerHTML = data.tokens_list.map(item => `
                        <tr style="${item.is_you ? 'background: rgba(5, 150, 105, 0.06); font-weight: 600;' : ''}">
                            <td><span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 700;">#${item.position}</span></td>
                            <td><span style="font-family: 'Space Grotesk', sans-serif; font-size: 1.25rem; font-weight: 800; color: ${item.is_you ? 'var(--primary)' : 'var(--dark)'};">${item.token_number}</span></td>
                            <td>${item.time}</td>
                            <td>${item.is_you ? '<span style="background: var(--primary); color: #ffffff; padding: 0.2rem 0.65rem; border-radius: var(--radius-full); font-size: 0.775rem; font-weight: 700;">★ Your Token</span>' : '<span style="color: var(--text-muted); font-size: 0.9rem;">Patient in line</span>'}</td>
                            <td>${item.status_html || item.status}</td>
                        </tr>
                    `).join('');
                }

                // If completed or cancelled, handle notice
                if (data.status === 'Completed' || data.status === 'Cancelled') {
                    const noticeEl = document.getElementById('queueActiveNotice');
                    if (noticeEl) {
                        noticeEl.innerHTML = `<div class="alert alert-info">Your appointment is marked as <strong>${data.status}</strong>.</div>`;
                    }
                }
            })
            .catch(err => {
                console.warn('Queue polling error:', err);
            });
    }

    // Immediate initial poll then periodic
    pollQueue();
    queuePollInterval = setInterval(pollQueue, 3500);
}

// Notification Toast System
let notifPollInterval = null;
function initNotificationPolling() {
    const notifContainer = document.getElementById('notificationHook');
    if (!notifContainer) return;

    const baseUrl = notifContainer.dataset.baseUrl || '';

    function checkNotifications() {
        fetch(`${baseUrl}/api/notifications.php`)
            .then(res => res.json())
            .then(data => {
                if (data && data.notifications && data.notifications.length > 0) {
                    data.notifications.forEach(notif => {
                        showToast(notif.message, notif.type);
                    });
                }
            })
            .catch(() => {});
    }

    checkNotifications();
    notifPollInterval = setInterval(checkNotifications, 10000);
}

function showToast(message, type = 'info') {
    let container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        container.className = 'toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <span>${message}</span>
        <button type="button" class="btn-close" style="font-size: 0.9rem;" onclick="this.parentElement.remove()">×</button>
    `;

    container.appendChild(toast);
    setTimeout(() => {
        if (toast.parentElement) toast.remove();
    }, 6000);
}
