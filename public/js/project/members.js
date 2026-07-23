// public/js/project/members.js
//  УПРАВЛЕНИЕ УЧАСТНИКАМИ 

let currentSelectedUserId = null;

function openMembersModal() {
    const settingsZone = document.getElementById('memberSettingsZone');
    const searchInput = document.getElementById('userSearchInput');
    const list = document.getElementById('usersModalList');
    
    if (settingsZone) settingsZone.classList.add('d-none');
    if (searchInput) searchInput.value = '';
    if (list) list.innerHTML = '<div class="text-muted small p-2">⏳ Загрузка...</div>';
    
    const isOwner = window.projectOwnerId === window.currentUserId;
    
    fetch(`/dashboard/project/${window.projectId}/members`)
        .then(res => {
            if (!res.ok) throw new Error('HTTP error! status: ' + res.status);
            return res.json();
        })
        .then(users => {
            renderUsersList(users, isOwner);
        })
        .catch(err => {
            console.error('Error loading users:', err);
            if (list) list.innerHTML = '<div class="text-muted small p-2 text-danger">❌ Ошибка загрузки</div>';
        });
}

function renderUsersList(users, isOwner) {
    const list = document.getElementById('usersModalList');
    if (!list) return;
    list.innerHTML = '';
    
    if (!users || users.length === 0) {
        list.innerHTML = '<div class="text-muted small p-2">Нет зарегистрированных пользователей.</div>';
        return;
    }
    
    const currentEmail = window.currentUserEmail;
    const filteredUsers = users.filter(u => u.email !== currentEmail);

    if (filteredUsers.length === 0) {
        list.innerHTML = '<div class="text-muted small p-2">Нет других зарегистрированных пользователей.</div>';
        return;
    }
    
    filteredUsers.forEach(user => {
        const btn = document.createElement('div');
        const isMember = user.isMember;
        const isAdmin = user.role === 'admin';
        
        let statusText = isMember ? user.role : 'не в проекте';
        let statusClass = isMember ? 'bg-success' : 'bg-secondary';
        
        if (isAdmin && !isOwner) {
            statusText = '🔒 Админ (только владелец)';
            statusClass = 'bg-danger';
        }
        
        // Добавляем аватар в список пользователей
        const hasAvatar = user.avatar && user.avatar !== '';
        let avatarHtml;
        if (hasAvatar) {
            avatarHtml = `<img src="${user.avatar}" alt="${user.email}" style="width: 24px; height: 24px; border-radius: 50%; object-fit: cover; margin-right: 8px;">`;
        } else {
            avatarHtml = `<span style="display: inline-block; width: 24px; height: 24px; border-radius: 50%; background: #6d28d9; color: white; text-align: center; line-height: 24px; font-size: 12px; font-weight: bold; margin-right: 8px;">${user.email[0].toUpperCase()}</span>`;
        }
        
        btn.className = `user-search-item p-2 mb-1 rounded border d-flex justify-content-between align-items-center ` + 
                        `${isMember ? 'bg-success-subtle border-success' : 'bg-white border-secondary'}`;
        btn.setAttribute('data-email', user.email.toLowerCase());
        btn.setAttribute('data-user-id', user.id);
        btn.style.fontSize = '13px';
        btn.style.cursor = isAdmin && !isOwner ? 'not-allowed' : 'pointer';
        btn.style.opacity = isAdmin && !isOwner ? '0.7' : '1';
        btn.style.transition = 'all 0.2s';
        
        const displayName = user.fullName || user.email;
        
        btn.innerHTML = `
            <span style="display: flex; align-items: center;">
                ${avatarHtml}
                ${displayName}
            </span>
            <span class="badge ${statusClass}">${statusText}</span>
        `;
        
        btn.onclick = () => {
            if (isAdmin && !isOwner) {
                showToast('Только владелец может редактировать админа', 'warning');
                return;
            }
            selectUserForEdition(user);
        };
        list.appendChild(btn);
    });
}

function selectUserForEdition(user) {
    currentSelectedUserId = user.id;
    const emailEl = document.getElementById('selectedUserEmail');
    const roleSelect = document.getElementById('memberRoleSelect');
    const settingsZone = document.getElementById('memberSettingsZone');
    const saveBtn = document.querySelector('#memberSettingsZone .btn-primary');
    
    if (emailEl) emailEl.innerText = `Настройка: ${user.email}`;
    if (roleSelect) roleSelect.value = user.role;
    if (settingsZone) settingsZone.classList.remove('d-none');
    
    const isOwner = window.projectOwnerId === window.currentUserId;
    
    // Удаляем старое предупреждение
    const oldWarning = document.getElementById('adminWarning');
    if (oldWarning) oldWarning.remove();
    
    if (user.role === 'admin' && !isOwner) {
        if (roleSelect) roleSelect.disabled = true;
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.textContent = '⛔ Только владелец может изменять админа';
            saveBtn.classList.add('btn-secondary');
            saveBtn.classList.remove('btn-primary');
        }
        
        const warning = document.createElement('div');
        warning.id = 'adminWarning';
        warning.className = 'alert alert-warning mt-2';
        warning.role = 'alert';
        warning.innerHTML = '⚠️ <strong>Внимание!</strong> Только владелец проекта может изменять права администратора.';
        if (roleSelect) roleSelect.parentElement.appendChild(warning);
    } else {
        if (roleSelect) roleSelect.disabled = false;
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Сохранить настройки участника';
            saveBtn.classList.remove('btn-secondary');
            saveBtn.classList.add('btn-primary');
        }
    }

    // Снимаем все галочки
    document.querySelectorAll('.form-check-input').forEach(cb => cb.checked = false);
    
    user.areas.forEach(id => {
        const c = document.getElementById(`cb-area-${id}`);
        if (c) c.checked = true;
    });
    
    user.tasks.forEach(id => {
        const c = document.getElementById(`cb-task-${id}`);
        if (c) c.checked = true;
    });

    toggleRoleInterface(user.role);
}

function saveMemberData() {
    const roleSelect = document.getElementById('memberRoleSelect');
    const role = roleSelect ? roleSelect.value : 'viewer';
    const isOwner = window.projectOwnerId === window.currentUserId;
    
    if (role === 'admin' && !isOwner) {
        showToast('Только владелец может назначать админов', 'warning');
        return;
    }
    
    if (role === 'admin' && !confirm('Внимание! Вы собираетесь назначить Администратора. Продолжить?')) return;

    const areas = Array.from(document.querySelectorAll('.area-cb:checked')).map(cb => parseInt(cb.value));
    const tasks = Array.from(document.querySelectorAll('.task-cb:checked')).map(cb => parseInt(cb.value));

    const saveBtn = document.querySelector('#memberSettingsZone .btn-primary');
    const originalText = saveBtn ? saveBtn.textContent : 'Сохранить';
    if (saveBtn) {
        saveBtn.textContent = '⏳ Сохранение...';
        saveBtn.disabled = true;
    }

    fetch(`/dashboard/project/${window.projectId}/member/save`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
            user_id: currentSelectedUserId, 
            role: role, 
            areas: areas, 
            tasks: tasks
        })
    })
    .then(res => res.json())
    .then(data => {
        if (saveBtn) {
            saveBtn.textContent = originalText;
            saveBtn.disabled = false;
        }
        
        if (data.success) {
            const settingsZone = document.getElementById('memberSettingsZone');
            if (settingsZone) settingsZone.classList.add('d-none');
            refreshMembersPanel();
            showToast('Права успешно обновлены!', 'success');
        } else {
            showToast('Ошибка: ' + (data.error || 'Неизвестная ошибка'), 'error');
        }
    })
    .catch(err => {
        if (saveBtn) {
            saveBtn.textContent = originalText;
            saveBtn.disabled = false;
        }
        console.error('Error:', err);
        showToast('Ошибка при сохранении', 'error');
    });
}

function removeMember(userId) {
    if (!confirm('Удалить участника?')) return;
    
    fetch(`/dashboard/project/${window.projectId}/member/remove/${userId}`, {
        method: 'POST'
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            refreshMembersPanel();
            showToast('Участник удален!', 'success');
        } else {
            showToast('Ошибка: ' + (data.error || 'Неизвестная ошибка'), 'error');
        }
    })
    .catch(err => {
        console.error('Error:', err);
        showToast('Ошибка при удалении участника', 'error');
    });
}

function refreshMembersPanel() {
    fetch(`/dashboard/project/${window.projectId}/members-panel`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                updateMembersPanel(data.members, data.total);
            }
        })
        .catch(err => console.error('Error refreshing members:', err));
}

function updateMembersPanel(members, total) {
    const panelBody = document.getElementById('membersPanelBody');
    if (!panelBody) return;
    
    const badge = document.querySelector('.card-header .badge');
    if (badge) badge.textContent = total || 0;
    
    panelBody.innerHTML = '';
    
    if (!members || members.length === 0) {
        panelBody.innerHTML = '<div class="text-center text-muted small p-3">Нет участников</div>';
        return;
    }
    
    const currentUserRole = window.userRole;
    const isOwner = window.projectOwnerId === window.currentUserId;
    
    members.forEach(member => {
        const isOwnerMember = member.isOwner;
        const firstLetter = member.email.charAt(0).toUpperCase();
        
        // Проверяем наличие аватара
        const hasAvatar = member.avatar && member.avatar !== '';
        
        // Генерируем HTML для аватара
        let avatarHtml;
        if (hasAvatar) {
            avatarHtml = `<img src="${member.avatar}" alt="${member.email}" class="rounded-circle member-avatar">`;
        } else {
            const bgColor = isOwnerMember ? 'bg-primary' : 
                        (member.role === 'admin' ? 'bg-danger' : 
                        (member.role === 'manager' ? 'bg-primary' : 
                        (member.role === 'executor' ? 'bg-success' : 'bg-secondary')));
            avatarHtml = `<div class="rounded-circle ${bgColor} text-white d-flex align-items-center justify-content-center member-avatar-placeholder">
                            ${firstLetter}
                        </div>`;
        }
        
        const roleBadge = isOwnerMember ? 
            '<span class="badge bg-warning text-dark">👑 Владелец</span>' :
            `<span class="badge bg-${member.role === 'admin' ? 'danger' : member.role === 'manager' ? 'primary' : member.role === 'executor' ? 'success' : 'secondary'}">${member.roleLabel}</span>`;
        
        const extraInfo = !isOwnerMember && member.role === 'manager' ?
            `<span class="badge bg-secondary small">Областей: ${member.areasCount}</span>` :
            (!isOwnerMember && member.role === 'executor' ?
            `<span class="badge bg-secondary small">Задач: ${member.tasksCount}</span>` : '');
        
        let showDeleteButton = false;
        if (!isOwnerMember && currentUserRole === 'admin') {
            if (isOwner) {
                showDeleteButton = true;
            } else {
                if (member.role !== 'admin') {
                    showDeleteButton = true;
                }
            }
        }
        
        const deleteButton = showDeleteButton ?
            `<button class="btn btn-sm btn-outline-danger ms-1" 
                    onclick="event.stopPropagation(); removeMember(${member.userId})" 
                    title="Удалить участника"
                    style="padding: 2px 6px; font-size: 12px;">
                ✕
            </button>` : '';
        
        // Создаем элемент с ссылкой на профиль
        const displayName = member.fullName || member.email;
        
        // Добавляем иконку перехода для всех кроме владельца
        const viewIcon = !isOwnerMember ? `<span class="view-icon">→</span>` : '';
        
        panelBody.innerHTML += `
            <div class="d-flex align-items-center p-2 mb-1 rounded hover-bg-light member-item" 
                 onclick="window.location.href='/dashboard/profile/${member.userId}'"
                 style="cursor: pointer; transition: all 0.2s ease;">
                <div class="position-relative">
                    ${avatarHtml}
                    <span class="position-absolute bottom-0 end-0" 
                        style="width: 12px; height: 12px; background: #23a55a; border: 2px solid white; border-radius: 50%;"></span>
                </div>
                <div class="ms-2 flex-grow-1" style="min-width: 0;">
                    <div class="fw-bold small text-truncate" style="max-width: 120px;">
                        ${displayName}
                        ${viewIcon}
                    </div>
                    <div class="text-muted small">
                        ${roleBadge}
                        ${extraInfo}
                    </div>
                </div>
                ${deleteButton}
            </div>
        `;
    });
}

function toggleRoleInterface(role) {
    const tree = document.getElementById('projectAccessTree');
    if (role === 'admin' || role === 'viewer') {
        if (tree) tree.classList.add('d-none');
        return;
    }
    
    if (tree) tree.classList.remove('d-none');
    
    if (role === 'manager') {
        document.querySelectorAll('.task-node').forEach(node => {
            node.style.display = 'none';
        });
        document.querySelectorAll('.subtask-cb').forEach(cb => {
            const item = cb.closest('.subtask-item');
            if (item) item.style.display = 'none';
        });
    } else if (role === 'executor') {
        document.querySelectorAll('.task-node').forEach(node => {
            node.style.display = 'block';
        });
        document.querySelectorAll('.subtask-cb').forEach(cb => {
            const item = cb.closest('.subtask-item');
            if (item) item.style.display = 'none';
        });
    }
}

function handleAreaCbChange(areaId, isChecked) {
    const roleSelect = document.getElementById('memberRoleSelect');
    const role = roleSelect ? roleSelect.value : 'viewer';
    
    if (role !== 'executor') return;
    
    document.querySelectorAll(`.task-cb[data-area="${areaId}"]`).forEach(taskCb => {
        taskCb.checked = isChecked;
    });
}

function handleTaskCbChange(taskId, isChecked) {
    const roleSelect = document.getElementById('memberRoleSelect');
    const role = roleSelect ? roleSelect.value : 'viewer';
    
    if (role !== 'executor') return;
    
    const taskCb = document.querySelector(`.task-cb[value="${taskId}"]`);
    if (!taskCb) return;
    
    const areaId = taskCb.dataset.area;
    const areaCb = document.getElementById(`cb-area-${areaId}`);
    if (!areaCb) return;
    
    const tasksInArea = document.querySelectorAll(`.task-cb[data-area="${areaId}"]`);
    const allChecked = Array.from(tasksInArea).every(cb => cb.checked);
    areaCb.checked = allChecked;
}