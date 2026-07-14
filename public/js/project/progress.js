// public/js/project/progress.js
// ===== ОБНОВЛЕНИЕ ПРОГРЕССОВ =====

// Обновление статистики после изменения статуса задачи
function updateTaskStats(taskId, newStatus, oldStatus) {
    const taskItem = document.getElementById(`task-node-${taskId}`);
    if (!taskItem) return;
    
    const areaCard = taskItem.closest('.area-card');
    if (!areaCard) return;
    
    const areaId = areaCard.id.replace('area-', '');
    
    // Обновляем статистику области
    if (window.areaStats && window.areaStats[areaId]) {
        const stats = window.areaStats[areaId];
        
        if (oldStatus === 'done') stats.totalDone--;
        else if (oldStatus === 'progress') stats.totalProgress--;
        
        if (newStatus === 'done') stats.totalDone++;
        else if (newStatus === 'progress') stats.totalProgress++;
    }
    
    // Обновляем общую статистику проекта
    if (window.projectStats) {
        if (oldStatus === 'done') window.projectStats.totalDone--;
        else if (oldStatus === 'progress') window.projectStats.totalProgress--;
        
        if (newStatus === 'done') window.projectStats.totalDone++;
        else if (newStatus === 'progress') window.projectStats.totalProgress++;
    }
    
    // Обновляем UI
    updateAreaProgress(areaCard);
    updateProjectProgress();
}

// Прогресс области
function updateAreaProgress(areaCard) {
    const areaId = areaCard.id.replace('area-', '');
    const stats = window.areaStats ? window.areaStats[areaId] : null;
    
    if (!stats) {
        calculateAreaProgressFromDOM(areaCard);
        return;
    }
    
    const total = stats.totalTasks;
    const done = stats.totalDone || 0;
    const progress = stats.totalProgress || 0;
    
    updateAreaProgressUI(areaCard, done, progress, total);
}

function calculateAreaProgressFromDOM(areaCard) {
    const tasks = areaCard.querySelectorAll('.task-item');
    let total = tasks.length;
    let done = 0;
    let progress = 0;
    
    tasks.forEach(task => {
        const status = task.querySelector('.task-status-indicator');
        if (status) {
            if (status.classList.contains('done')) done++;
            else if (status.classList.contains('progress')) progress++;
        }
    });
    
    updateAreaProgressUI(areaCard, done, progress, total);
}

function updateAreaProgressUI(areaCard, done, progress, total) {
    const progressBar = areaCard.querySelector('.area-progress-bar');
    const progressText = areaCard.querySelector('.area-progress-text');
    const areaIcon = areaCard.querySelector('.area-icon');
    
    if (total === 0) {
        if (progressBar) progressBar.style.width = '0%';
        if (progressText) progressText.textContent = '0%';
        if (areaIcon) areaIcon.textContent = '📋';
        return;
    }
    
    const percent = Math.round(((done + progress * 0.5) / total) * 100);
    if (progressBar) progressBar.style.width = percent + '%';
    if (progressText) progressText.textContent = percent + '%';
    if (areaIcon) areaIcon.textContent = percent === 100 ? '✅' : '📋';
}

// Прогресс проекта
function updateProjectProgress() {
    const stats = window.projectStats;
    
    if (!stats) {
        calculateProjectProgressFromDOM();
        return;
    }
    
    const total = stats.totalTasks;
    const done = stats.totalDone || 0;
    const progress = stats.totalProgress || 0;
    
    updateProjectProgressUI(done, progress, total);
}

function calculateProjectProgressFromDOM() {
    const tasks = document.querySelectorAll('.task-item');
    let total = tasks.length;
    let done = 0;
    let progress = 0;
    
    tasks.forEach(task => {
        const status = task.querySelector('.task-status-indicator');
        if (status) {
            if (status.classList.contains('done')) done++;
            else if (status.classList.contains('progress')) progress++;
        }
    });
    
    updateProjectProgressUI(done, progress, total);
}

function updateProjectProgressUI(done, progress, total) {
    const progressBar = document.querySelector('.project-progress-card .progress-fill');
    const progressText = document.querySelector('.project-progress-card .progress-value');
    
    if (total === 0) {
        if (progressBar) progressBar.style.width = '0%';
        if (progressText) progressText.textContent = '0%';
        return;
    }
    
    const percent = Math.round(((done + progress * 0.5) / total) * 100);
    if (progressBar) progressBar.style.width = percent + '%';
    if (progressText) progressText.textContent = percent + '%';
    
    const steps = document.querySelectorAll('.progress-steps .step');
    const thresholds = [0, 33, 66, 100];
    steps.forEach((step, index) => {
        if (percent >= thresholds[index]) {
            step.classList.add('active');
        } else {
            step.classList.remove('active');
        }
    });
}

function updateTaskProgress(taskItem) {
    const subtasks = taskItem.querySelectorAll('.subtask-item');
    const total = subtasks.length;
    const done = taskItem.querySelectorAll('.subtask-checkbox:checked').length;
    
    if (total === 0) return;
    
    const percent = Math.round((done / total) * 100);
    const progressBar = taskItem.querySelector('.progress-bar');
    const progressText = taskItem.querySelector('.text-muted[style*="font-size: 11px;"]');
    
    if (progressBar) progressBar.style.width = percent + '%';
    if (progressText) progressText.textContent = `Выполнено подзадач ${percent}%`;
}

function updateAllCounters() {
    const membersCount = document.querySelectorAll('#membersPanelBody .d-flex').length;
    const badge = document.querySelector('.card-header .badge');
    if (badge) badge.textContent = membersCount;
    updateProjectProgress();
}