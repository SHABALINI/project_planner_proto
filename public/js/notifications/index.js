function filterNotificationsBySelect(value) {
    document.querySelectorAll('.quick-filters .filter-btn').forEach(b => {
        b.classList.remove('active');
    });
    
    filterNotifications(value, null);
}

function filterNotifications(filter, btn) {
    if (btn) {
        document.querySelectorAll('.quick-filters .filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const select = document.getElementById('filterSelect');
        if (select) select.value = filter;
    }

    const items = document.querySelectorAll('.notification-item');
    let visibleCount = 0;

    items.forEach(item => {
        let show = false;
        
        if (filter === 'all') {
            show = true;
        } else if (filter === 'unread') {
            show = item.dataset.read === 'false';
        } else if (filter === 'read') {
            show = item.dataset.read === 'true';
        } else if (filter.startsWith('project-')) {
            const projectId = filter.replace('project-', '');
            show = item.dataset.project == projectId;
        }
        
        if (show) {
            item.style.display = '';
            visibleCount++;
        } else {
            item.style.display = 'none';
        }
    });

    const list = document.getElementById('notificationsList');
    let emptyMsg = list?.querySelector('.empty-notifications');
    
    if (visibleCount === 0) {
        if (!emptyMsg) {
            const msg = document.createElement('div');
            msg.className = 'empty-notifications';
            msg.innerHTML = `
                <span class="empty-icon">🔍</span>
                <h3>Нет уведомлений</h3>
                <p>По выбранному фильтру ничего не найдено</p>
            `;
            list?.appendChild(msg);
        }
    } else {
        if (emptyMsg) emptyMsg.remove();
    }
}

function markAsRead(id) {
    fetch(`/dashboard/notifications/read/${id}`, { method: 'POST' })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const item = document.getElementById(`notification-${id}`);
                if (item) {
                    item.classList.remove('unread');
                    item.dataset.read = 'true';
                    const badge = item.querySelector('.badge');
                    if (badge) badge.remove();
                    const markBtn = item.querySelector('.mark-read');
                    if (markBtn) markBtn.remove();
                }
                updateCounters();
            }
        })
        .catch(err => console.error('Error:', err));
}

function markAllAsRead() {
    const btn = document.getElementById('markAllReadBtn');
    if (!btn) return;
    
    const originalText = btn.textContent;
    btn.innerHTML = `${AppIcons.get('wait', 'icon-sm icon-spin')} Обновление...`;
    btn.disabled = true;

    fetch(`/dashboard/notifications/read-all/0`, { method: 'POST' })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.querySelectorAll('.notification-item.unread').forEach(item => {
                    item.classList.remove('unread');
                    item.dataset.read = 'true';
                    const badge = item.querySelector('.badge');
                    if (badge) badge.remove();
                    const markBtn = item.querySelector('.mark-read');
                    if (markBtn) markBtn.remove();
                });
                updateCounters();
                
                btn.innerHTML = `${AppIcons.get('check', 'icon-success')} Все прочитаны`;
                setTimeout(() => {
                    btn.textContent = originalText;
                    btn.disabled = false;
                }, 2000);
            } else {
                btn.textContent = originalText;
                btn.disabled = false;
                showToast('Ошибка при отметке', 'error');
            }
        })
        .catch(err => {
            btn.textContent = originalText;
            btn.disabled = false;
            console.error('Error:', err);
            showToast('Ошибка при отметке', 'error');
        });
}

function goToNotification(id, url) {
    fetch(`/dashboard/notifications/read/${id}`, { method: 'POST' })
        .then(() => {
            window.location.href = url;
        })
        .catch(() => {
            window.location.href = url;
        });
}

function updateCounters() {
    const unreadItems = document.querySelectorAll('.notification-item[data-read="false"]');
    const unreadCount = unreadItems.length;

    const unreadBadge = document.getElementById('unreadCount');
    if (unreadBadge) {
        if (unreadCount > 0) {
            unreadBadge.textContent = `${unreadCount} непрочитанных`;
        } else {
            unreadBadge.innerHTML = `Все прочитано ${AppIcons.get('check', 'icon-success')}`;
        }
    }

    const markAllBtn = document.getElementById('markAllReadBtn');
    if (markAllBtn) {
        markAllBtn.disabled = unreadCount === 0;
    }

    document.querySelectorAll('.filter-btn').forEach(btn => {
        const filter = btn.dataset.filter;
        if (filter === 'unread') {
            const countSpan = btn.querySelector('.count-unread');
            if (countSpan) {
                if (unreadCount > 0) {
                    countSpan.textContent = unreadCount;
                    countSpan.style.display = '';
                } else {
                    countSpan.style.display = 'none';
                }
            } else if (unreadCount > 0) {
                btn.innerHTML += ` <span class="count-unread">${unreadCount}</span>`;
            }
        }
    });

    if (typeof refreshNotificationCounter === 'function') {
        refreshNotificationCounter();
    }
}

function showToast(message, type = 'info') {
    const container = document.getElementById('toastContainer');
    if (!container) {
        const newContainer = document.createElement('div');
        newContainer.id = 'toastContainer';
        newContainer.style.position = 'fixed';
        newContainer.style.bottom = '20px';
        newContainer.style.right = '20px';
        newContainer.style.zIndex = '9999';
        newContainer.style.display = 'flex';
        newContainer.style.flexDirection = 'column';
        newContainer.style.gap = '10px';
        document.body.appendChild(newContainer);
    }
    
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.style.cssText = `
        padding: 12px 20px;
        border-radius: 8px;
        background: ${type === 'error' ? '#ef4444' : type === 'success' ? '#10b981' : type === 'warning' ? '#f59e0b' : '#6366f1'};
        color: white;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        animation: slideInRight 0.3s ease;
        font-size: 14px;
        max-width: 400px;
        display: flex;
        align-items: center;
        gap: 8px;
    `;
    toast.innerHTML = message;
    
    document.getElementById('toastContainer').appendChild(toast);
    
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(20px)';
        toast.style.transition = 'all 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

const toastStyle = document.createElement('style');
toastStyle.textContent = `
    @keyframes slideInRight {
        from { opacity: 0; transform: translateX(20px); }
        to { opacity: 1; transform: translateX(0); }
    }
`;
document.head.appendChild(toastStyle);

document.addEventListener('DOMContentLoaded', function() {
    updateCounters();

    setInterval(() => {
        fetch('/dashboard/notifications/unread-count-api', { method: 'GET' })
            .then(res => res.json())
            .then(data => {
                const currentUnread = document.querySelectorAll('.notification-item[data-read="false"]').length;
                if (currentUnread !== data.count) {
                }
            })
            .catch(() => {});
    }, 30000);
});