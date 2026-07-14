// public/js/project/tasks.js
//  CRUD ОПЕРАЦИИ С ЗАДАЧАМИ 

function createTask(areaId) {
    const titleInput = document.getElementById(`taskTitle-${areaId}`);
    const deadlineInput = document.getElementById(`taskDeadline-${areaId}`);
    if (!titleInput.value.trim()) {
        showToast('Введите название задачи', 'warning');
        return;
    }
    
    const btn = titleInput.closest('.row').querySelector('.btn');
    const originalText = btn.textContent;
    btn.textContent = '⏳';
    btn.disabled = true;
    
    const title = titleInput.value.trim();
    const deadline = deadlineInput.value || null;
    
    fetch('/dashboard/task/create', { 
        method: 'POST', 
        headers: { 'Content-Type': 'application/json' }, 
        body: JSON.stringify({ area_id: areaId, title: title, deadline: deadline })
    })
    .then(res => res.json())
    .then(data => {
        btn.textContent = originalText;
        btn.disabled = false;
        
        if (data.success && data.id) {
            showToast('Задача создана!', 'success');
            location.reload();
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
        body: JSON.stringify({ task_id: taskId, title: title })
    })
    .then(res => res.json())
    .then(data => {
        btn.textContent = originalText;
        btn.disabled = false;
        
        if (data.success && data.id) {
            showToast('Подзадача создана!', 'success');
            location.reload();
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

// public/js/project/tasks.js

function updateTaskParam(taskId, paramName, paramValue) {
    const select = event.target;
    const originalBg = select.style.backgroundColor;
    select.style.backgroundColor = '#fef3c7';
    select.disabled = true;
    
    // Сохраняем старый статус перед отправкой
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
    .then(res => res.json())
    .then(data => {
        select.style.backgroundColor = originalBg;
        select.disabled = false;
        
        if (data.success) {
            showToast('Параметр обновлен!', 'success');
            
            // Обновляем визуальное состояние задачи
            updateTaskVisual(taskId, paramName, paramValue);
            
            // Если изменился статус, обновляем прогресс
            if (paramName === 'status' && oldStatus !== null) {
                // Обновляем статистику
                updateTaskStats(taskId, paramValue, oldStatus);
            }
        } else {
            showToast('Ошибка: ' + (data.error || 'Неизвестная ошибка'), 'error');
            // Возвращаем старое значение
            if (taskItem && paramName === 'status') {
                const indicator = taskItem.querySelector('.task-status-indicator');
                const currentStatus = indicator ? indicator.className.replace('task-status-indicator ', '') : 'todo';
                select.value = currentStatus;
            }
        }
    })
    .catch(err => {
        select.style.backgroundColor = originalBg;
        select.disabled = false;
        console.error('Error:', err);
        showToast('Ошибка при обновлении', 'error');
    });
}

// public/js/project/tasks.js

function updateSubtaskStatus(subtaskId, isChecked) {
    const checkbox = event.target;
    const label = checkbox.closest('.subtask-item').querySelector('.subtask-label');
    
    checkbox.disabled = true;
    
    fetch('/dashboard/subtask/update', { 
        method: 'POST', 
        headers: { 'Content-Type': 'application/json' }, 
        body: JSON.stringify({ subtask_id: subtaskId, status: isChecked ? 'done' : 'todo' })
    })
    .then(res => res.json())
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
            
            // Обновляем только прогресс задачи (чек-лист)
            const taskItem = checkbox.closest('.task-item');
            if (taskItem) {
                updateTaskProgress(taskItem);
                // НЕ обновляем прогресс области и проекта
            }
            
            showToast('Статус обновлен!', 'success');
        } else {
            showToast('Ошибка: ' + (data.error || 'Неизвестная ошибка'), 'error');
            checkbox.checked = !isChecked;
        }
    })
    .catch(err => {
        checkbox.disabled = false;
        console.error('Error:', err);
        showToast('Ошибка при обновлении', 'error');
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
            location.reload();
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