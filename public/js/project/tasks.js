// public/js/project/tasks.js

function createTask(areaId) {
    const titleInput = document.getElementById(`taskTitle-${areaId}`);
    const deadlineInput = document.getElementById(`taskDeadline-${areaId}`);
    
    if (!titleInput || !titleInput.value.trim()) {
        showToast('Введите название задачи', 'warning');
        return;
    }
    
    const btn = titleInput.closest('.row').querySelector('.btn');
    const originalText = btn.textContent;
    btn.textContent = '⏳';
    btn.disabled = true;
    
    const title = titleInput.value.trim();
    const deadline = deadlineInput ? deadlineInput.value || null : null;
    
    fetch('/dashboard/task/create', { 
        method: 'POST', 
        headers: { 'Content-Type': 'application/json' }, 
        body: JSON.stringify({ 
            area_id: areaId, 
            title: title, 
            deadline: deadline
        })
    })
    .then(res => {
        if (!res.ok) {
            throw new Error('Server error: ' + res.status);
        }
        return res.json();
    })
    .then(data => {
        btn.textContent = originalText;
        btn.disabled = false;
        
        if (data.success && data.id) {
            showToast('Задача создана!', 'success');
            
            // Находим контейнер задач
            const tasksContainer = document.getElementById(`tasksList-${areaId}`);
            if (tasksContainer) {
                // Удаляем сообщение "Нет задач"
                const emptyMsg = tasksContainer.querySelector('.text-muted');
                if (emptyMsg) emptyMsg.remove();
                
                // Создаем HTML для новой задачи
                const taskHtml = createTaskHTML(data.id, title, deadline, areaId);
                tasksContainer.insertAdjacentHTML('beforeend', taskHtml);
                
                // Обновляем статистику
                if (window.areaStats && window.areaStats[areaId]) {
                    window.areaStats[areaId].totalTasks++;
                }
                if (window.projectStats) {
                    window.projectStats.totalTasks++;
                }
                
                // Обновляем прогресс
                const areaCard = tasksContainer.closest('.area-card');
                if (areaCard) {
                    updateAreaProgress(areaCard);
                }
                updateProjectProgress();

                // Инициализируем форму комментариев для новой задачи
                const newTask = document.getElementById(`task-node-${data.id}`);
                if (newTask) {
                    const form = newTask.querySelector('form[data-ajax="true"]');
                    if (form) {
                        form.removeEventListener('submit', handleCommentSubmit);
                        form.addEventListener('submit', handleCommentSubmit);
                    }
                }
            }
            
            titleInput.value = '';
            if (deadlineInput) deadlineInput.value = '';
        } else {
            showToast('Ошибка: ' + (data.error || 'Неизвестная ошибка'), 'error');
        }
    })
    .catch(err => {
        btn.textContent = originalText;
        btn.disabled = false;
        console.error('Error:', err);
        showToast('Ошибка при создании задачи', 'error');
    });
}

// Вспомогательная функция для создания HTML задачи
function createTaskHTML(taskId, title, deadline, areaId) {
    const deadlineHtml = deadline ? `<span class="task-deadline">📅 ${deadline}</span>` : '';
    
    return `
        <div class="task-item" id="task-node-${taskId}">
            <div class="task-main" onclick="toggleTask(${taskId})">
                <span class="task-status-indicator todo"></span>
                <span class="task-title">${escapeHtml(title)}</span>
                <div class="task-meta">
                    <span class="task-priority medium">medium</span>
                    ${deadlineHtml}
                </div>
                <div class="task-actions" onclick="event.stopPropagation();">
                    <select class="form-select form-select-sm" style="width: 120px; border-radius: 8px; font-size: 12px;" onchange="updateTaskParam(${taskId}, 'status', this.value)">
                        <option value="todo" selected>⭕ Не выполнено</option>
                        <option value="progress">🔄 В работе</option>
                        <option value="done">✅ Выполнено</option>
                    </select>
                    <select class="form-select form-select-sm" style="width: 100px; border-radius: 8px; font-size: 12px;" onchange="updateTaskParam(${taskId}, 'priority', this.value)">
                        <option value="low">🟢 Низкий</option>
                        <option value="medium" selected>🟡 Средний</option>
                        <option value="high">🔴 Высокий</option>
                    </select>
                    <button class="btn-icon danger" onclick="deleteElement('task', ${taskId})">🗑</button>
                </div>
            </div>
            <div class="subtask-list" id="task-body-${taskId}" style="display: none;">
                <div class="task-description mb-3 p-2 bg-white rounded border" style="font-size: 13px; color: var(--text-gray);">
                    <strong class="text-dark">📝 Описание:</strong>
                    <span>Нет описания</span>
                </div>
                <div class="mb-3">
                    <div class="d-flex gap-2 align-items-center">
                        <input type="text" id="taskDescription-${taskId}" 
                               class="form-control form-control-sm" 
                               placeholder="Добавить описание..." 
                               style="border-radius: 10px;"
                               onblur="updateTaskParam(${taskId}, 'description', this.value)"
                               onkeydown="if(event.key==='Enter') { this.blur(); }">
                        <button class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('taskDescription-${taskId}').focus()">✏️</button>
                    </div>
                </div>
                <div class="d-flex gap-2 mb-2">
                    <input type="text" id="subtaskTitle-${taskId}" class="form-control form-control-sm" placeholder="+ Подзадача..." style="border-radius: 10px; max-width: 300px;">
                    <button class="btn btn-purple-outline btn-sm" onclick="createSubtask(${taskId})">Добавить</button>
                </div>
                <div id="subtasksList-${taskId}"></div>
                <div class="mt-3 pt-3" style="border-top: 1px solid #f0f0f0;">
                    <div class="comments-container" id="comments-container-${taskId}">
                        <div class="comments-empty">Нет комментариев</div>
                    </div>
                    <form action="/dashboard/comment/create" method="POST" enctype="multipart/form-data" class="mt-2" data-ajax="true">
                        <input type="hidden" name="task_id" value="${taskId}">
                        <div class="d-flex gap-2">
                            <input type="text" name="text" class="form-control form-control-sm" placeholder="Напишите комментарий..." style="border-radius: 10px;">
                            <button type="submit" class="btn btn-purple btn-sm">Отправить</button>
                        </div>
                        <div class="mt-1">
                            <input type="file" name="file" class="form-control form-control-sm" style="border-radius: 10px; max-width: 250px;">
                        </div>
                    </form>
                </div>
            </div>
        </div>
    `;
}

function createSubtask(taskId) {
    const titleInput = document.getElementById(`subtaskTitle-${taskId}`);
    
    if (!titleInput || !titleInput.value.trim()) {
        showToast('Введите название подзадачи', 'warning');
        return;
    }
    
    const btn = titleInput.nextElementSibling;
    const originalText = btn.textContent;
    btn.textContent = '⏳';
    btn.disabled = true;
    
    const title = titleInput.value.trim();
    
    fetch('/dashboard/subtask/create', { 
        method: 'POST', 
        headers: { 'Content-Type': 'application/json' }, 
        body: JSON.stringify({ 
            task_id: taskId, 
            title: title
        })
    })
    .then(res => {
        if (!res.ok) {
            throw new Error('Server error: ' + res.status);
        }
        return res.json();
    })
    .then(data => {
        btn.textContent = originalText;
        btn.disabled = false;
        
        if (data.success && data.id) {
            showToast('Подзадача создана!', 'success');
            
            // Находим контейнер для подзадач
            let subtasksContainer = document.getElementById(`subtasksList-${taskId}`);
            if (!subtasksContainer) {
                const taskBody = document.getElementById(`task-body-${taskId}`);
                if (taskBody) {
                    // Проверяем, есть ли блок комментариев после подзадач
                    const commentsBlock = taskBody.querySelector('.mt-3.pt-3');
                    if (commentsBlock) {
                        // Создаем контейнер перед комментариями
                        subtasksContainer = document.createElement('div');
                        subtasksContainer.id = `subtasksList-${taskId}`;
                        commentsBlock.parentNode.insertBefore(subtasksContainer, commentsBlock);
                    }
                }
            }
            
            if (subtasksContainer) {
                // Удаляем сообщение "Нет подзадач" если есть
                const emptyMsg = subtasksContainer.querySelector('.text-muted');
                if (emptyMsg) emptyMsg.remove();
                
                // Добавляем новую подзадачу
                const subtaskHtml = `
                    <div class="subtask-item" id="subtask-${data.id}">
                        <div class="d-flex align-items-center gap-2 flex-grow-1">
                            <input class="subtask-checkbox" type="checkbox" onchange="updateSubtaskStatus(${data.id}, this.checked)">
                            <span class="subtask-label">${title}</span>
                        </div>
                        <div class="d-flex align-items-center gap-1 flex-grow-1 ms-2" style="max-width: 200px;">
                            <input type="text" id="subtaskDescription-${data.id}" 
                                   class="form-control form-control-sm" 
                                   placeholder="Описание..." 
                                   style="border-radius: 6px; font-size: 12px;"
                                   onkeydown="if(event.key==='Enter') updateSubtaskDescription(${data.id}, this.value)">
                            <button class="btn btn-sm btn-outline-secondary" 
                                    onclick="updateSubtaskDescription(${data.id}, document.getElementById('subtaskDescription-${data.id}').value)"
                                    style="padding: 2px 6px; font-size: 12px;">
                                💾
                            </button>
                        </div>
                        <div class="d-flex align-items-center gap-1">
                            <button class="btn btn-sm text-muted" style="border: none; background: none; font-size: 12px;" onclick="deleteElement('subtask', ${data.id})">✕</button>
                        </div>
                    </div>
                `;
                subtasksContainer.insertAdjacentHTML('beforeend', subtaskHtml);
                
                // Обновляем прогресс задачи
                const taskItem = subtasksContainer.closest('.task-item');
                if (taskItem) {
                    updateTaskProgress(taskItem);
                }
            }
            
            titleInput.value = '';
        } else {
            showToast('Ошибка: ' + (data.error || 'Неизвестная ошибка'), 'error');
        }
    })
    .catch(err => {
        btn.textContent = originalText;
        btn.disabled = false;
        console.error('Error:', err);
        showToast('Ошибка при создании подзадачи', 'error');
    });
}

function refreshTasksList(areaId) {
    fetch(`/dashboard/area/${areaId}/tasks`)
        .then(res => res.json())
        .then(data => {
            const container = document.getElementById(`tasksList-${areaId}`);
            if (container && data.tasks) {
                // Сохраняем состояние открытых задач
                const openTasks = {};
                container.querySelectorAll('.task-item').forEach(item => {
                    const id = item.id.replace('task-node-', '');
                    const body = document.getElementById(`task-body-${id}`);
                    if (body && body.style.display !== 'none') {
                        openTasks[id] = true;
                    }
                });
                
                // Перерисовываем задачи
                container.innerHTML = '';
                data.tasks.forEach(task => {
                    // Здесь нужно рендерить задачу через Twig, но так как мы в JS,
                    // проще перезагрузить страницу или использовать AJAX-рендеринг
                    // Временное решение: перезагружаем страницу
                    location.reload();
                });
            }
        })
        .catch(err => console.error('Error refreshing tasks:', err));
}

function updateSubtaskDescription(subtaskId, description) {
    const input = document.getElementById(`subtaskDescription-${subtaskId}`);
    const btn = input ? input.nextElementSibling : null;
    
    if (btn) {
        const originalText = btn.textContent;
        btn.textContent = '⏳';
        btn.disabled = true;
    }
    
    fetch('/dashboard/subtask/update', { 
        method: 'POST', 
        headers: { 'Content-Type': 'application/json' }, 
        body: JSON.stringify({ 
            subtask_id: subtaskId, 
            field: 'description', 
            value: description || ''
        }) 
    })
    .then(res => {
        if (!res.ok) {
            throw new Error('Server error: ' + res.status);
        }
        return res.json();
    })
    .then(data => {
        if (btn) {
            btn.textContent = '💾';
            btn.disabled = false;
        }
        
        if (data.success) {
            showToast('Описание обновлено!', 'success');
            
            // Добавляем/убираем индикатор 📝
            const subtaskItem = document.getElementById(`subtask-${subtaskId}`);
            if (subtaskItem) {
                const label = subtaskItem.querySelector('.subtask-label');
                const existingIcon = subtaskItem.querySelector('.text-muted[title="Есть описание"]');
                
                if (description && description.trim()) {
                    if (!existingIcon) {
                        const icon = document.createElement('span');
                        icon.className = 'text-muted';
                        icon.style.cssText = 'font-size: 10px; margin-left: 4px;';
                        icon.title = 'Есть описание';
                        icon.textContent = '📝';
                        label.after(icon);
                    }
                } else {
                    if (existingIcon) existingIcon.remove();
                }
            }
        } else {
            showToast('Ошибка: ' + (data.error || 'Неизвестная ошибка'), 'error');
        }
    })
    .catch(err => {
        if (btn) {
            btn.textContent = '💾';
            btn.disabled = false;
        }
        console.error('Error:', err);
        showToast('Ошибка при обновлении описания', 'error');
    });
}

function updateTaskParam(taskId, paramName, paramValue) {
    const select = event ? event.target : null;
    let originalBg = '';
    if (select) {
        originalBg = select.style.backgroundColor;
        select.style.backgroundColor = '#fef3c7';
        select.disabled = true;
    }
    
    // Сохраняем старый статус
    let oldStatus = null;
    const taskItem = document.getElementById(`task-node-${taskId}`);
    if (taskItem && paramName === 'status') {
        const indicator = taskItem.querySelector('.task-status-indicator');
        if (indicator) {
            oldStatus = indicator.className.replace('task-status-indicator ', '');
        }
    }
    
    fetch('/dashboard/task/update', { 
        method: 'POST', 
        headers: { 'Content-Type': 'application/json' }, 
        body: JSON.stringify({ task_id: taskId, field: paramName, value: paramValue })
    })
    .then(res => {
        if (!res.ok) {
            throw new Error('Server error: ' + res.status);
        }
        return res.json();
    })
    .then(data => {
        if (select) {
            select.style.backgroundColor = originalBg;
            select.disabled = false;
        }
        
        if (data.success) {
            showToast('Параметр обновлен!', 'success');
            
            // Обновляем визуальное состояние
            updateTaskVisual(taskId, paramName, paramValue);
            
            // Если это описание, обновляем отображение описания
            if (paramName === 'description') {
                updateTaskDescriptionDisplay(taskId, paramValue);
            }
            
            // Если изменился статус, обновляем прогресс
            if (paramName === 'status' && oldStatus !== null) {
                updateTaskStats(taskId, paramValue, oldStatus);
            }
        } else {
            showToast('Ошибка: ' + (data.error || 'Неизвестная ошибка'), 'error');
            if (select && taskItem && paramName === 'status') {
                const indicator = taskItem.querySelector('.task-status-indicator');
                const currentStatus = indicator ? indicator.className.replace('task-status-indicator ', '') : 'todo';
                select.value = currentStatus;
            }
        }
    })
    .catch(err => {
        if (select) {
            select.style.backgroundColor = originalBg;
            select.disabled = false;
        }
        console.error('Error:', err);
        showToast('Ошибка при обновлении', 'error');
    });
}

function updateSubtaskDescription(subtaskId, description) {
    const input = document.getElementById(`subtaskDescription-${subtaskId}`);
    const btn = input ? input.nextElementSibling : null;
    
    if (btn) {
        const originalText = btn.textContent;
        btn.textContent = '⏳';
        btn.disabled = true;
    }
    
    fetch('/dashboard/subtask/update', { 
        method: 'POST', 
        headers: { 'Content-Type': 'application/json' }, 
        body: JSON.stringify({ 
            subtask_id: subtaskId, 
            field: 'description', 
            value: description || ''
        }) 
    })
    .then(res => {
        if (!res.ok) {
            return res.json().then(data => {
                throw new Error(data.error || 'Server error: ' + res.status);
            });
        }
        return res.json();
    })
    .then(data => {
        if (btn) {
            btn.textContent = '💾';
            btn.disabled = false;
        }
        
        if (data.success) {
            showToast('Описание обновлено!', 'success');
            
            // Добавляем/убираем индикатор 📝
            const subtaskItem = document.getElementById(`subtask-${subtaskId}`);
            if (subtaskItem) {
                const label = subtaskItem.querySelector('.subtask-label');
                const existingIcon = subtaskItem.querySelector('.text-muted[title="Есть описание"]');
                
                if (description && description.trim()) {
                    if (!existingIcon) {
                        const icon = document.createElement('span');
                        icon.className = 'text-muted';
                        icon.style.cssText = 'font-size: 10px; margin-left: 4px;';
                        icon.title = 'Есть описание';
                        icon.textContent = '📝';
                        label.after(icon);
                    }
                } else {
                    if (existingIcon) existingIcon.remove();
                }
            }
        } else {
            showToast('❌ Ошибка: ' + (data.error || 'Неизвестная ошибка'), 'error');
        }
    })
    .catch(err => {
        if (btn) {
            btn.textContent = '💾';
            btn.disabled = false;
        }
        console.error('Error:', err);
        showToast('❌ Ошибка при обновлении описания: ' + err.message, 'error');
    });
}

function updateSubtaskStatus(subtaskId, isChecked) {
    const checkbox = event.target;
    const label = checkbox.closest('.subtask-item').querySelector('.subtask-label');
    
    checkbox.disabled = true;
    
    fetch('/dashboard/subtask/update', { 
        method: 'POST', 
        headers: { 'Content-Type': 'application/json' }, 
        body: JSON.stringify({ 
            subtask_id: subtaskId, 
            field: 'status',
            value: isChecked ? 'done' : 'todo' 
        })
    })
    .then(res => {
        if (!res.ok) {
            return res.json().then(data => {
                throw new Error(data.error || 'Server error: ' + res.status);
            });
        }
        return res.json();
    })
    .then(data => {
        checkbox.disabled = false;
        
        if (data.success) {
            if (isChecked) {
                label.classList.add('done');
                checkbox.checked = true;
            } else {
                label.classList.remove('done');
                checkbox.checked = false;
            }
            
            const taskItem = checkbox.closest('.task-item');
            if (taskItem) {
                updateTaskProgress(taskItem);
            }
            
            showToast('Статус обновлен!', 'success');
        } else {
            showToast('❌ Ошибка: ' + (data.error || 'Неизвестная ошибка'), 'error');
            checkbox.checked = !isChecked;
        }
    })
    .catch(err => {
        checkbox.disabled = false;
        console.error('Error:', err);
        showToast('❌ Ошибка при обновлении статуса: ' + err.message, 'error');
        checkbox.checked = !isChecked;
    });
}

function updateTaskVisual(taskId, paramName, paramValue) {
    const taskItem = document.getElementById(`task-node-${taskId}`);
    if (!taskItem) return;
    
    if (paramName === 'status') {
        const indicator = taskItem.querySelector('.task-status-indicator');
        const title = taskItem.querySelector('.task-title');
        
        indicator.className = `task-status-indicator ${paramValue}`;
        
        if (paramValue === 'done') {
            title.classList.add('done');
        } else {
            title.classList.remove('done');
        }
        
        const select = taskItem.querySelector('select[onchange*="status"]');
        if (select) select.value = paramValue;
    }
    
    if (paramName === 'priority') {
        const priorityBadge = taskItem.querySelector('.task-priority');
        if (priorityBadge) {
            priorityBadge.className = `task-priority ${paramValue}`;
            priorityBadge.textContent = paramValue;
        }
        const select = taskItem.querySelector('select[onchange*="priority"]');
        if (select) select.value = paramValue;
    }
    
    // Добавляем обработку описания
    if (paramName === 'description') {
        updateTaskDescriptionDisplay(taskId, paramValue);
    }
}

function deleteElement(type, id, confirmMessage = null) {
    if (confirmMessage && !confirm(confirmMessage)) return;
    
    const element = document.getElementById(`task-node-${id}`) || 
                   document.getElementById(`area-${id}`) ||
                   document.querySelector(`.subtask-item:has(button[onclick*="deleteElement('subtask', ${id})"])`);
    
    if (element) {
        element.style.opacity = '0.5';
        element.style.pointerEvents = 'none';
    }
    
    fetch(`/dashboard/delete/${type}/${id}`, { method: 'POST' })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast('Удалено!', 'success');
            if (element) {
                element.style.transition = 'all 0.3s ease';
                element.style.opacity = '0';
                setTimeout(() => {
                    element.remove();
                    // Обновляем прогресс
                    if (type === 'task') {
                        const areaCard = element.closest('.area-card');
                        if (areaCard) {
                            const areaId = areaCard.id.replace('area-', '');
                            if (window.areaStats && window.areaStats[areaId]) {
                                window.areaStats[areaId].totalTasks--;
                            }
                            updateAreaProgress(areaCard);
                        }
                        if (window.projectStats) {
                            window.projectStats.totalTasks--;
                        }
                        updateProjectProgress();
                    }
                }, 300);
            } else {
                location.reload();
            }
        } else {
            showToast('Ошибка: ' + (data.error || 'Неизвестная ошибка'), 'error');
            if (element) {
                element.style.opacity = '1';
                element.style.pointerEvents = 'auto';
            }
        }
    })
    .catch(err => {
        console.error('Error:', err);
        showToast('Ошибка при удалении', 'error');
        if (element) {
            element.style.opacity = '1';
            element.style.pointerEvents = 'auto';
        }
    });
}