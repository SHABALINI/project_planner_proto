// public/js/app.js

// ===== TOAST =====
function showToast(message, type = 'success') {
    let container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        document.body.appendChild(container);
    }
    
    const icons = { success: '✅', error: '❌', warning: '⚠️', info: 'ℹ️' };
    
    const toast = document.createElement('div');
    toast.className = `toast-custom ${type}`;
    toast.innerHTML = `
        <span class="toast-icon">${icons[type] || 'ℹ️'}</span>
        <span class="toast-message">${message}</span>
        <button class="toast-close" onclick="this.parentElement.remove()">✕</button>
    `;
    
    container.appendChild(toast);
    
    setTimeout(() => {
        if (toast.parentElement) {
            toast.style.animation = 'slideOutRight 0.3s ease forwards';
            setTimeout(() => toast.remove(), 300);
        }
    }, 4000);
}

// ===== САЙДБАР =====
function toggleSidebar() {
    const body = document.body;
    if (window.innerWidth >= 768) {
        body.classList.toggle('sidebar-collapsed');
        const isCollapsed = body.classList.contains('sidebar-collapsed');
        document.cookie = `sidebarCollapsed=${isCollapsed}; path=/; max-age=31536000`;
        localStorage.setItem('sidebarCollapsed', isCollapsed);
    } else {
        body.classList.toggle('sidebar-mobile-open');
    }
}

// ===== УВЕДОМЛЕНИЯ =====
function refreshNotificationCounter() {
    const badgeId = 'sidebar-badge';
    const burgerBadgeId = 'burger-badge';
    
    fetch('/dashboard/notifications/unread-count-api', { method: 'GET' })
        .then(res => res.json())
        .then(data => {
            const count = data.count;
            
            const sidebarBadge = document.getElementById(badgeId);
            if (sidebarBadge) {
                if (count > 0) {
                    sidebarBadge.textContent = count;
                    sidebarBadge.classList.remove('d-none');
                } else {
                    sidebarBadge.classList.add('d-none');
                }
            }
            
            const burgerBadge = document.getElementById(burgerBadgeId);
            if (burgerBadge) {
                if (count > 0) {
                    burgerBadge.textContent = count;
                    burgerBadge.classList.remove('d-none');
                } else {
                    burgerBadge.classList.add('d-none');
                }
            }
        })
        .catch(() => {});
}

// ===== COMING SOON =====
function showComingSoon(section) {
    showToast('Раздел «' + section + '» находится в разработке 🚀', 'info');
}

// ===== ОБРАБОТКА ССЫЛОК В ТЕКСТЕ =====
function processLinksInText(selector = '.comment-text') {
    const urlPattern = /(\b(https?|ftp|file):\/\/[-A-Z0-9+&@#\/%?=~_|!:,.;]*[-A-Z0-9+&@#\/%=~_|])/ig;
    document.querySelectorAll(selector).forEach(el => {
        let rawText = el.innerHTML;
        let processedText = rawText.replace(urlPattern, 
            '<a href="$1" target="_blank" class="text-primary text-decoration-underline">$1</a>'
        );
        el.innerHTML = processedText;
    });
}

// ===== ИНИЦИАЛИЗАЦИЯ ПРИ ЗАГРУЗКЕ =====
document.addEventListener('DOMContentLoaded', () => {
    // Синхронизация состояния сайдбара
    const cookieMatch = document.cookie.match(/sidebarCollapsed=(true|false)/);
    if (cookieMatch) {
        const isCollapsed = cookieMatch[1] === 'true';
        if (window.innerWidth >= 768) {
            if (isCollapsed) {
                document.body.classList.add('sidebar-collapsed');
            } else {
                document.body.classList.remove('sidebar-collapsed');
            }
        }
        localStorage.setItem('sidebarCollapsed', isCollapsed);
    } else if (localStorage.getItem('sidebarCollapsed') !== null) {
        const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        if (window.innerWidth >= 768) {
            if (isCollapsed) {
                document.body.classList.add('sidebar-collapsed');
            } else {
                document.body.classList.remove('sidebar-collapsed');
            }
        }
        document.cookie = `sidebarCollapsed=${isCollapsed}; path=/; max-age=31536000`;
    }
    
    // Запуск счетчика уведомлений
    refreshNotificationCounter();
    setInterval(refreshNotificationCounter, 30000);
    
    // Обработка ссылок
    processLinksInText();
});

document.addEventListener('visibilitychange', () => {
    if (!document.hidden) {
        refreshNotificationCounter();
    }
});